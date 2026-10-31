<?php

namespace App\Http\Controllers;

use App\Exceptions\StockCountRowChanged;
use App\Models\Category;
use App\Models\Batch;
use App\Models\Location;
use App\Models\StockCount;
use App\Services\CountSchedule;
use App\Services\StockCounts;
use App\Support\StockCountPrint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Layar Stok Opname (penghitungan fisik rutin): daftar, mulai, lembar input hasil hitung, tinjau selisih, setujui, cetak lembar hitung dan Berita Acara.
 *                Hak: lihat / isi hasil hitung / cetak lembar = akses modul SO; mulai, tinjau, setujui, kembalikan, batalkan, buka kunci = Supervisor (rute).
 * Sumber hulu  : StockCounts (logika), StockCount / StockCountLine.
 * Output       : stock-counts.index (grid), stock-counts.form (mulai), stock-counts.show (header + input / tinjau), cetakan.
 * Dipakai oleh : routes/web.php (stock-counts.*).
 * Status       : Aktif.
 */
class StockCountController extends Controller
{
    public function __construct(private readonly StockCounts $counts) {}

    public function index(Request $request)
    {
        $filled = fn ($q) => $q->where(fn ($w) => $w->where('skipped', true)->orWhereNotNull('counted_qty')->orWhereNotNull('recount_qty'));
        $rows = StockCount::query()->withCount(['lines', 'lines as filled_lines_count' => $filled])->with(['creator', 'adjustmentDocument'])->latest('count_date')->latest('id')->limit(1000)->get()
            ->map(fn (StockCount $c) => [
                'id' => $c->id, 'doc_no' => $c->doc_no, 'count_date' => $c->count_date->format('Y-m-d'), 'scope' => $c->scopeLabel(),
                'progress' => $c->filled_lines_count.' / '.$c->lines_count, 'adjustment' => $c->adjustmentDocument?->doc_no ?? '—',
                'status' => $c->statusLabel(), 'user' => $c->creator->name, 'active' => true,
            ])->all();

        return view('stock-counts.index', ['rows' => $rows, 'sup' => $request->user()->isSupervisor(), 'schedule' => CountSchedule::state(), 'cfg' => CountSchedule::config()]);
    }

    // Jadwal opname (Supervisor): frekuensi, hari, tanggal; dipakai pengingat di Beranda.
    public function schedule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'frequency' => ['required', Rule::in(['weekly', 'monthly'])], 'weekday' => ['nullable', 'integer', 'between:1,7'], 'day_of_month' => ['nullable', 'regex:/^(last|[1-9]|[12]\d|3[01])$/'],
        ], [], ['frequency' => 'frekuensi', 'weekday' => 'hari', 'day_of_month' => 'tanggal']);
        CountSchedule::save($data);

        return redirect()->route('stock-counts.index')->with('status', 'Jadwal opname disimpan: '.CountSchedule::describe().'. Pengingat tampil di Beranda.');
    }

    public function create()
    {
        return view('stock-counts.form', [
            'categories' => Category::query()->orderBy('code')->get()->mapWithKeys(fn (Category $c) => [$c->code => "{$c->code} · {$c->name}"])->all(),
            'locations' => Location::query()->where('is_active', true)->orderBy('code')->get()->mapWithKeys(fn (Location $l) => [$l->code => "{$l->code} · {$l->name}"])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'count_date' => ['required', 'date'],
            'scope_type' => ['required', Rule::in(array_keys(StockCount::SCOPE))],
            'category_code' => ['nullable', 'required_if:scope_type,category', 'exists:m_category,code'],
            'location_code' => ['nullable', 'required_if:scope_type,location', 'exists:m_location,code'],
            'threshold_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], ['category_code.required_if' => 'Pilih kategori untuk cakupan per Kategori.', 'location_code.required_if' => 'Pilih lokasi untuk cakupan per Lokasi.'], ['category_code' => 'kategori', 'location_code' => 'lokasi', 'count_date' => 'tanggal', 'scope_type' => 'cakupan', 'threshold_pct' => 'ambang selisih']);

        $count = $this->counts->start($request->user(), [
            'count_date' => $data['count_date'], 'scope_type' => $data['scope_type'], 'scope_value' => match ($data['scope_type']) {
                'category' => $data['category_code'], 'location' => $data['location_code'], default => null,
            },
            'blind' => $request->boolean('blind'), 'lock_transactions' => $request->boolean('lock_transactions'), 'threshold_pct' => $data['threshold_pct'], 'note' => $data['note'] ?? null,
        ]);

        return redirect()->route('stock-counts.show', $count)->with('status', "{$count->doc_no} dimulai: {$count->lines()->count()} batch masuk lembar hitung. Stok belum berubah sebelum opname disetujui.");
    }

    // Supervisor meninjau (Tinjau) dan membaca hasilnya (Selesai / Dibatalkan); selain itu lembar input (hanya bisa diisi saat Sedang dihitung).
    public function show(Request $request, StockCount $count)
    {
        $sup = $request->user()->isSupervisor();
        $review = $sup && $count->status !== 'counting';
        $data = ['count' => $count->load(['creator', 'unlocker', 'adjustmentDocument']), 'sup' => $sup, 'summary' => $this->counts->summary($count), 'review' => $review];

        return view('stock-counts.show', $data + ($review
            ? ['reviewRows' => $this->counts->reviewRows($count, true), 'moved' => $count->status === 'review' ? $this->counts->movedLines($count) : collect(), 'ownShare' => $count->status === 'review' ? $this->counts->enteredShare($count, $request->user()) : null]
            : ['entryRows' => $this->counts->entryRows($count)]));
    }

    // Simpan per sel dari lembar input (x-dl::lookup-grid auto-save): {changed: [baris]} -> {rows: seluruh baris}. Nama penghitung terakhir diingat per sesi dan mengisi baris berikutnya yang namanya kosong.
    public function saveLines(Request $request, StockCount $count): JsonResponse
    {
        $key = "stock-count.{$count->id}.counter";
        $compact = $request->boolean('compact');
        $ids = array_map(fn ($r) => (int) ($r['id'] ?? 0), (array) $request->input('changed', []));
        $overwrite = filled($request->input('reason'));   // layar menjawab peringatan "baris sudah diubah orang lain" dengan alasan = menimpa
        try {
            DB::transaction(function () use ($request, $count, $key, $overwrite) {
                foreach ((array) $request->input('changed', []) as $row) {
                    $line = $this->counts->saveLine($count, (int) ($row['id'] ?? 0), (array) $row, session($key), $request->user(), $overwrite);
                    if ($line->counted_by !== null) {
                        session([$key => $line->counted_by]);
                    }
                }
            });
        } catch (StockCountRowChanged $e) {
            return response()->json(['reason_required' => true, 'error' => $e->getMessage()] + ($compact ? ['row' => $this->phoneRows($count, $ids)[0] ?? null] : []), 409);
        }

        return response()->json(['rows' => $compact ? $this->phoneRows($count, $ids) : $this->counts->entryRows($count)]);
    }

    // Tombol Simpan di lembar input: isian sudah tersimpan per sel, tombol ini memastikannya. Layar mengirim jumlah baris terisi yang dilihatnya (seen); server membandingkannya dengan databasenya sendiri.
    public function save(Request $request, StockCount $count): JsonResponse
    {
        if ($count->status !== 'counting') {
            return response()->json(['error' => "{$count->doc_no} sudah tidak dalam penghitungan ({$count->statusLabel()})."], 422);
        }
        $entered = $this->counts->enteredCount($count);
        $seen = $request->integer('seen', -1);
        if ($seen !== $entered) {
            return response()->json(['error' => "Layar menunjukkan {$seen} baris terisi, di server {$entered}. Isian Anda mungkin belum tersimpan, atau orang lain sudah mengisi baris lain. Muat ulang halaman untuk melihat yang terbaru."], 409);
        }
        $summary = $this->counts->summary($count);

        return response()->json(['message' => "Semua isian tersimpan di server: {$summary['filled']} dari {$summary['lines']} baris sudah lengkap (pukul ".now()->format('H:i').').']);
    }

    // Tampilan HP menyimpan satu barang per permintaan dan hanya butuh barisnya sendiri (compact=1): jawabannya baris itu saja, lengkap dengan jejak siapa / jam.
    private function phoneRows(StockCount $count, array $ids): array
    {
        return array_values(array_filter($this->counts->entryRows($count, true), fn (array $r) => in_array($r['id'], $ids, true)));
    }

    // Tampilan HP: daftar opname sebagai kartu (tab Opname).
    public function phoneIndex(Request $request)
    {
        $filled = fn ($q) => $q->where(fn ($w) => $w->where('skipped', true)->orWhereNotNull('counted_qty')->orWhereNotNull('recount_qty'));
        $counts = StockCount::query()->withCount(['lines', 'lines as filled_lines_count' => $filled])->latest('count_date')->latest('id')->limit(30)->get()
            ->map(fn (StockCount $c) => [
                'id' => $c->id, 'doc_no' => $c->doc_no, 'date' => $c->count_date->format('d-m-Y'), 'scope' => $c->scopeLabel(), 'status' => $c->statusLabel(),
                'tone' => ['counting' => 'warn', 'review' => 'info', 'done' => 'success', 'cancelled' => 'danger'][$c->status] ?? 'neutral',
                'progress' => $c->filled_lines_count.' / '.$c->lines_count, 'counting' => $c->status === 'counting',
            ])->all();

        return view('hp.counts', ['counts' => $counts, 'sup' => $request->user()->isSupervisor()]);
    }

    // Tampilan HP: layar hitung satu barang per kartu. Hanya saat Sedang dihitung; selain itu kembali ke daftar. Pengendali, aturan, dan endpoint simpan sama dengan layar desktop.
    public function phone(Request $request, StockCount $count)
    {
        if ($count->status !== 'counting') {
            return redirect()->route('hp.counts')->with('status', "{$count->doc_no} sudah tidak dalam penghitungan ({$count->statusLabel()}).");
        }

        return view('hp.count', ['count' => $count, 'rows' => $this->counts->entryRows($count, true), 'me' => $request->user()->id]);
    }

    // Tambah barang / batch yang ditemukan fisik tetapi tidak ada di lembar.
    public function addItem(Request $request, StockCount $count): RedirectResponse
    {
        $data = $request->validate(['batch_id' => ['required', 'exists:i_batch,id']], ['batch_id.required' => 'Pilih barang / batch yang ditemukan.'], ['batch_id' => 'barang']);
        $line = $this->counts->addItem($count, Batch::query()->findOrFail($data['batch_id']));

        return redirect()->route('stock-counts.show', $count)->with('status', "{$line->batch->code} ditambahkan ke lembar hitung (stok sistem saat ini). Isi hasil hitungnya.");
    }

    // Cetak lembar hitung (A4): dipegang orang gudang di lapangan; stok sistem hanya tampil bila opname tidak buta.
    public function sheet(StockCount $count, StockCountPrint $print)
    {
        return view('stock-counts.print', ['count' => $count, 'data' => $print->sheet($count), 'kind' => 'sheet']);
    }

    // Berita Acara (Supervisor): setelah Selesai, dan sebagai pratinjau saat Tinjau.
    public function report(StockCount $count, StockCountPrint $print)
    {
        abort_unless(in_array($count->status, ['review', 'done'], true), 422, 'Berita Acara tersedia saat Tinjau (pratinjau) dan sesudah Selesai.');

        return view('stock-counts.print', ['count' => $count->load(['approver', 'adjustmentDocument']), 'data' => $print->report($count), 'kind' => 'report']);
    }

    // Tinjau (Supervisor): hitung ulang dan alasan per baris, disimpan per sel; balasan = seluruh baris dengan selisih terhitung ulang.
    public function reviewLines(Request $request, StockCount $count): JsonResponse
    {
        foreach ((array) $request->input('changed', []) as $row) {
            $this->counts->reviewLine($count, (int) ($row['id'] ?? 0), (array) $row);
        }

        return response()->json(['rows' => $this->counts->reviewRows($count, true)]);
    }

    public function refreshMoved(StockCount $count): RedirectResponse
    {
        $n = $this->counts->refreshMoved($count);

        return redirect()->route('stock-counts.show', $count)->with('status', "{$n} baris diperbarui ke stok sekarang dan dibuka untuk diisi ulang. Isi hasil hitungnya, lalu Selesai hitung lagi.");
    }

    // Perbarui satu baris saja dari daftar baris bergeser di layar tinjau.
    public function refreshOne(StockCount $count, int $line): RedirectResponse
    {
        $code = $count->lines()->with('batch')->findOrFail($line)->batch->code;
        $this->counts->refreshOne($count, $line);

        return redirect()->route('stock-counts.show', $count)->with('status', "{$code} diperbarui ke stok sekarang dan dibuka untuk diisi ulang. Isi hasil hitungnya, lalu Selesai hitung lagi.");
    }

    public function approve(Request $request, StockCount $count): RedirectResponse
    {
        $adj = $this->counts->approve($count, $request->user());

        return redirect()->route('stock-counts.show', $count)->with('status', $adj
            ? "{$count->doc_no} disetujui. Penyesuaian {$adj->doc_no} dibuat dan diposting: stok kini mengikuti hasil hitung fisik. Kunci transaksi dilepas."
            : "{$count->doc_no} disetujui. Tidak ada selisih, jadi tidak ada Penyesuaian. Kunci transaksi dilepas.");
    }

    public function reject(Request $request, StockCount $count): RedirectResponse
    {
        $this->counts->reject($count, (string) $request->input('note'));

        return redirect()->route('stock-counts.show', $count)->with('status', "{$count->doc_no} dikembalikan ke penghitungan. Perbaiki isian lalu Selesai hitung lagi.");
    }

    // Selesai hitung: semua baris harus terisi atau ditandai Tidak dihitung dengan catatan.
    public function finish(Request $request, StockCount $count): RedirectResponse
    {
        $this->counts->finish($count, $request->user());

        // hp=1: dikirim dari tampilan HP, kembali ke daftar opname HP (bukan layar desktop).
        return redirect()->route($request->boolean('hp') ? 'hp.counts' : 'stock-counts.show', $request->boolean('hp') ? [] : $count)->with('status', "Penghitungan {$count->doc_no} selesai. Menunggu tinjauan Supervisor; stok belum berubah.");
    }

    public function cancel(StockCount $count): RedirectResponse
    {
        $this->counts->cancel($count, request()->user());

        return redirect()->route('stock-counts.show', $count)->with('status', "{$count->doc_no} dibatalkan. Kunci transaksi dilepas, stok tidak berubah.");
    }

    public function lock(StockCount $count): RedirectResponse
    {
        $this->counts->lock($count, request()->user());

        return redirect()->route('stock-counts.show', $count)->with('status', "Transaksi dikunci kembali untuk {$count->doc_no}. Confirm / Approve Penerimaan, Pengeluaran, dan Penyesuaian ditolak sampai opname selesai atau dibatalkan.");
    }

    public function unlock(StockCount $count): RedirectResponse
    {
        $this->counts->unlock($count, request()->user());

        return redirect()->route('stock-counts.show', $count)->with('status', "Kunci transaksi {$count->doc_no} dibuka. Transaksi stok boleh diproses lagi; stok yang bergeser sejak cut-off akan ditolak saat persetujuan.");
    }
}
