<?php

namespace App\Services;

use App\Exceptions\StockCountRowChanged;
use App\Models\Category;
use App\Models\Batch;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockDocument;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/* REMARK
 * Tujuan       : Siklus Stok Opname (penghitungan fisik rutin): mulai (snapshot stok per batch) > Sedang dihitung > Selesai hitung (Tinjau) > Setujui (satu Penyesuaian otomatis) | Kembalikan | Batalkan.
 *                Stok TIDAK berubah sebelum persetujuan; saat disetujui, selisih diposting lewat StockDocuments::recordCountAdjustment (buku besar StockLedger, kunci baris item). Kunci transaksi dijaga StockLedger.
 * Sumber hulu  : i_batch (stok + biaya rata-rata saat cut-off), i_count, i_count_line.
 * Output       : StockCount beserta baris; baris untuk layar input (entryRows) dan layar tinjau (reviewRows).
 * Dipakai oleh : StockCountController.
 * Status       : Aktif.
 */
final class StockCounts
{
    private const EPS = 0.0005;

    public function __construct(private readonly StockDocuments $documents) {}

    /**
     * Mulai opname: snapshot satu baris per batch aktif berstok dalam cakupan (stok sistem + biaya rata-rata saat ini = cut-off).
     * Hanya SATU opname boleh berjalan. shortcut: dua Supervisor menekan Mulai di detik yang sama tanpa kunci database bisa lolos keduanya; di sini dijaga penjaga kirim ganda form dan cek di dalam transaksi.
     *
     * @param  array{count_date: string, scope_type: string, scope_value?: ?string, blind: bool, lock_transactions: bool, threshold_pct: float|int|string, note?: ?string}  $data
     */
    public function start(User $user, array $data): StockCount
    {
        return DB::transaction(function () use ($user, $data) {
            if ($active = StockCount::query()->active()->first()) {
                throw ValidationException::withMessages(['scope_type' => "Stok Opname {$active->doc_no} masih berjalan ({$active->statusLabel()}). Hanya satu opname boleh berjalan: selesaikan atau batalkan dulu sebelum memulai yang baru."]);
            }
            $type = $data['scope_type'];
            $value = $type === 'all' ? null : ($data['scope_value'] ?? null);
            $items = Batch::query()->where('is_active', true)->where('stock_qty', '>', 0)
                ->when($type === 'category', fn ($q) => $q->whereIn('category_id', Category::query()->where('code', $value)->select('id')))
                ->when($type === 'location', fn ($q) => $q->where('location', $value))->get(['id', 'stock_qty', 'avg_cost']);
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['scope_type' => 'Tidak ada batch berstok dalam cakupan ini. Pilih cakupan lain.']);
            }

            $count = $this->createNumbered([
                'count_date' => $data['count_date'], 'scope_type' => $type, 'scope_value' => $value, 'status' => 'counting', 'blind' => $data['blind'], 'lock_transactions' => $data['lock_transactions'],
                'threshold_pct' => $data['threshold_pct'], 'cutoff_at' => now(), 'created_by' => $user->id, 'note' => $data['note'] ?? null,
            ]);
            foreach ($items->chunk(500) as $chunk) {
                StockCountLine::query()->insert($chunk->map(fn (Batch $i) => [
                    'stock_count_id' => $count->id, 'batch_id' => $i->id, 'system_qty' => $i->stock_qty, 'unit_cost' => $i->avg_cost, 'skipped' => false, 'added_manually' => false, 'created_at' => now(), 'updated_at' => now(),
                ])->all());
            }

            return $count;
        });
    }

    /**
     * Simpan satu baris hasil hitung (dipanggil per perubahan sel). counted_qty kosong = belum dihitung; Tidak dihitung mengosongkan hasil, mengisi hasil melepas tanda.
     *
     * Penjaga lunak: bila $in['rev'] tidak sama dengan rev baris (orang lain menyimpannya sejak layar dimuat) dan $overwrite false, StockCountRowChanged dilempar (jangan menimpa diam-diam;
     * mode Replace: angka yang diketik = jumlah fisik sebenarnya, tidak dijumlahkan). Setiap simpan yang mengubah baris mencatat entered_by / entered_at dan menaikkan rev.
     *
     * @param  array{counted_qty?: mixed, counted_by?: ?string, skipped?: mixed, note?: ?string, rev?: mixed}  $in
     */
    public function saveLine(StockCount $count, int $lineId, array $in, ?string $rememberedCounter = null, ?User $enteredBy = null, bool $overwrite = false): StockCountLine
    {
        return DB::transaction(function () use ($count, $lineId, $in, $rememberedCounter, $enteredBy, $overwrite) {
            $this->lockAndAssert($count, ['counting']);
            $line = $count->lines()->with(['batch', 'enteredBy'])->lockForUpdate()->findOrFail($lineId);
            if (! $overwrite && isset($in['rev']) && (int) $in['rev'] !== $line->rev) {
                throw new StockCountRowChanged("Baris {$line->batch->code} sudah diubah oleh ".($line->enteredBy?->name ?? 'pengguna lain').' pada '.($line->entered_at?->format('H:i') ?? '-').'. Isi alasan lalu Simpan untuk MENIMPA, atau Batal untuk membuang isian Anda.');
            }
            $qty = $this->qty($in['counted_qty'] ?? null);
            $skip = filter_var($in['skipped'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($skip && ! $line->skipped && $qty === $line->counted_qty) {
                $qty = null;
            } elseif ($skip && $qty !== null) {
                $skip = false;
            }
            $by = mb_substr(trim((string) ($in['counted_by'] ?? '')), 0, 100);
            if ($by === '' && $qty !== null) {
                $by = (string) ($rememberedCounter ?? $line->counted_by);
            }
            $line->forceFill([
                'counted_qty' => $qty, 'skipped' => $skip, 'counted_by' => $by === '' ? null : $by,
                'note' => ($note = mb_substr(trim((string) ($in['note'] ?? '')), 0, 255)) === '' ? null : $note,
            ]);
            if ($line->isDirty()) {
                $line->forceFill(['entered_by' => $enteredBy?->id, 'entered_at' => now(), 'rev' => $line->rev + 1])->save();
            }

            return $line;
        });
    }

    // Barang / batch yang ditemukan fisik tetapi tidak ada di lembar: stok sistem = stok batch saat ini.
    public function addItem(StockCount $count, Batch $item): StockCountLine
    {
        return DB::transaction(function () use ($count, $item) {
            $this->lockAndAssert($count, ['counting']);
            abort_unless($item->is_active, 422, "Barang {$item->code} tidak aktif.");
            if ($count->lines()->where('batch_id', $item->id)->exists()) {
                throw ValidationException::withMessages(['batch_id' => "{$item->code} sudah ada di lembar hitung."]);
            }
            $item = Batch::query()->lockForUpdate()->findOrFail($item->id);

            return $count->lines()->create(['batch_id' => $item->id, 'system_qty' => $item->stock_qty, 'unit_cost' => $item->avg_cost, 'added_manually' => true]);
        });
    }

    // Selesai hitung: semua baris harus terisi (atau Tidak dihitung dengan catatan); yang belum ditampilkan sebagai daftar.
    public function finish(StockCount $count, User $user): void
    {
        DB::transaction(function () use ($count, $user) {
            $this->lockAndAssert($count, ['counting']);
            $pending = $count->lines()->with('batch')->get()->reject(fn (StockCountLine $l) => $l->isComplete());
            if ($pending->isNotEmpty()) {
                throw ValidationException::withMessages(['lines' => 'Belum bisa selesai hitung: '.$pending->count().' baris belum terisi / belum lengkap ('.$this->codes($pending).'). Isi hasil hitung, atau tandai Tidak dihitung dengan catatan.']);
            }
            $count->update(['status' => 'review', 'submitted_by' => $user->id, 'submitted_at' => now(), 'review_note' => null]);
        });
    }

    /**
     * Tinjau: hitung ulang (recount_qty, kosong = batal hitung ulang) dan alasan selisih.
     *
     * @param  array{recount_qty?: mixed, reason?: ?string}  $in
     */
    public function reviewLine(StockCount $count, int $lineId, array $in): StockCountLine
    {
        return DB::transaction(function () use ($count, $lineId, $in) {
            $this->lockAndAssert($count, ['review']);
            $line = $count->lines()->lockForUpdate()->findOrFail($lineId);
            $line->forceFill([
                'recount_qty' => $this->qty($in['recount_qty'] ?? null),
                'reason' => ($reason = mb_substr(trim((string) ($in['reason'] ?? '')), 0, 255)) === '' ? null : $reason,
            ])->save();

            return $line;
        });
    }

    // Perbarui baris: stok sistem dan biaya disegarkan ke keadaan sekarang dan baris dibuka untuk diisi ulang; opname kembali ke Sedang dihitung.
    public function refreshLine(StockCount $count, int $lineId): void
    {
        DB::transaction(function () use ($count, $lineId) {
            $this->lockAndAssert($count, ['counting', 'review']);
            $line = $count->lines()->lockForUpdate()->findOrFail($lineId);
            $item = Batch::query()->lockForUpdate()->findOrFail($line->batch_id);
            $line->forceFill(['system_qty' => $item->stock_qty, 'unit_cost' => $item->avg_cost, 'counted_qty' => null, 'recount_qty' => null, 'skipped' => false, 'reason' => null])->save();
            if ($count->status === 'review') {
                $count->update(['status' => 'counting', 'submitted_by' => null, 'submitted_at' => null]);
            }
        });
    }

    // Perbarui SATU baris yang bergeser (tombol per baris di layar tinjau); baris yang stoknya tidak bergeser ditolak, supaya hasil hitung yang sah tidak terhapus tanpa sebab.
    public function refreshOne(StockCount $count, int $lineId): void
    {
        abort_unless($this->movedLines($count)->contains('id', $lineId), 422, 'Baris ini tidak bergeser sejak cut-off, tidak perlu diperbarui.');
        $this->refreshLine($count, $lineId);
    }

    // Kunci kembali transaksi sesudah dibuka (tercatat siapa dan kapan). Stok yang sempat bergeser ditolak saat persetujuan, bukan di sini.
    public function lock(StockCount $count, User $by): void
    {
        DB::transaction(function () use ($count, $by) {
            $this->lockAndAssert($count, ['counting', 'review']);
            abort_if($count->lock_transactions, 422, 'Transaksi sudah dikunci.');
            $count->update(['lock_transactions' => true, 'locked_by' => $by->id, 'locked_at' => now()]);
        });
    }

    /**
     * Baris selisih yang stok sistemnya sudah bergeser sejak cut-off (kunci dibuka lalu ada transaksi): persetujuan akan menolaknya.
     *
     * @return Collection<int, StockCountLine>
     */
    public function movedLines(StockCount $count): Collection
    {
        return $count->lines()->with('batch')->get()->filter(fn (StockCountLine $l) => $l->diff() != 0.0 && abs($l->batch->stock_qty - $l->system_qty) > self::EPS)->values();
    }

    // Baris yang sudah berisi hasil hitung atau ditandai Tidak dihitung, menurut database (tombol Simpan di lembar input membandingkannya dengan yang tampak di layar).
    public function enteredCount(StockCount $count): int
    {
        return $count->lines()->where(fn ($w) => $w->where('skipped', true)->orWhereNotNull('counted_qty'))->count();
    }

    // Bagian baris yang diinput akun ini (0..1). Hanya peringatan pemisahan tugas di layar Setujui, tidak memblokir (gudang kecil boleh satu orang).
    public function enteredShare(StockCount $count, User $user): float
    {
        $total = $count->lines()->count();

        return $total === 0 ? 0.0 : $count->lines()->where('entered_by', $user->id)->count() / $total;
    }

    // Perbarui semua baris yang bergeser sekaligus (stok sistem = stok sekarang, baris dibuka untuk diisi ulang).
    public function refreshMoved(StockCount $count): int
    {
        $ids = $this->movedLines($count)->pluck('id');
        foreach ($ids as $id) {
            $this->refreshLine($count, $id);
        }

        return $ids->count();
    }

    /**
     * Setujui: semua baris final, alasan wajib di atas ambang, stok baris selisih belum bergeser sejak cut-off. Lalu SATU Penyesuaian approved diposting (tanpa selisih: tanpa dokumen).
     * Status Selesai (kunci lepas) ditulis lebih dulu di transaksi yang sama supaya posting Penyesuaian lolos penjaga kunci; kegagalan apa pun membatalkan semuanya.
     */
    public function approve(StockCount $count, User $approver): ?StockDocument
    {
        return DB::transaction(function () use ($count, $approver) {
            $this->lockAndAssert($count, ['review']);
            $lines = $count->lines()->orderBy('id')->get();
            $items = Batch::query()->whereIn('id', $lines->pluck('batch_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $open = $lines->reject(fn (StockCountLine $l) => $l->isComplete());
            if ($open->isNotEmpty()) {
                throw ValidationException::withMessages(['lines' => "Masih ada baris yang belum final: {$this->codes($open, $items)}."]);
            }
            $diffs = $lines->filter(fn (StockCountLine $l) => $l->diff() != 0.0);
            $moved = $diffs->filter(fn (StockCountLine $l) => abs($items[$l->batch_id]->stock_qty - $l->system_qty) > self::EPS);
            if ($moved->isNotEmpty()) {
                throw ValidationException::withMessages(['lines' => 'Stok bergeser sejak cut-off ('.$moved->map(fn (StockCountLine $l) => "{$items[$l->batch_id]->code}: sistem {$this->fmt($l->system_qty)}, kini {$this->fmt($items[$l->batch_id]->stock_qty)}")->implode('; ').'). Pakai Perbarui baris pada barang itu lalu hitung ulang.']);
            }

            $needReason = $lines->filter(fn (StockCountLine $l) => $l->overThreshold($count->threshold_pct) && blank($l->reason));
            if ($needReason->isNotEmpty()) {
                throw ValidationException::withMessages(['lines' => "Alasan wajib untuk selisih di atas {$this->fmt($count->threshold_pct)} persen: {$this->codes($needReason, $items)}."]);
            }
            $count->update(['status' => 'done', 'approved_by' => $approver->id, 'approved_at' => now()]);
            if ($diffs->isEmpty()) {
                return null;
            }
            $adj = $this->documents->recordCountAdjustment($count, $diffs->map(fn (StockCountLine $l) => [
                'batch_id' => $l->batch_id, 'qty' => $l->diff(), 'system_qty' => $l->system_qty, 'counted_qty' => $l->finalQty(), 'unit_cost' => $l->unit_cost,
            ])->values()->all(), $approver);
            $count->update(['adjustment_document_id' => $adj->id]);

            return $adj;
        });
    }

    // Kembalikan ke penghitungan (dengan catatan): baris tetap, isian bisa diperbaiki, lalu Selesai hitung lagi.
    public function reject(StockCount $count, string $note): void
    {
        $note = mb_substr(trim($note), 0, 255);
        abort_if(mb_strlen($note) < 3, 422, 'Isi catatan pengembalian (minimal 3 huruf).');
        DB::transaction(function () use ($count, $note) {
            $this->lockAndAssert($count, ['review']);
            $count->update(['status' => 'counting', 'submitted_by' => null, 'submitted_at' => null, 'review_note' => $note]);
        });
    }

    // Batalkan opname yang belum Selesai: kunci lepas, stok tidak berubah.
    public function cancel(StockCount $count, User $by): void
    {
        DB::transaction(function () use ($count, $by) {
            $this->lockAndAssert($count, ['counting', 'review']);
            $count->update(['status' => 'cancelled', 'cancelled_by' => $by->id, 'cancelled_at' => now()]);
        });
    }

    // Buka kunci transaksi (tercatat siapa dan kapan); opname tetap berjalan. Stok yang bergeser sesudahnya ditolak saat persetujuan.
    public function unlock(StockCount $count, User $by): void
    {
        DB::transaction(function () use ($count, $by) {
            $this->lockAndAssert($count, ['counting', 'review']);
            abort_unless($count->lock_transactions, 422, 'Kunci transaksi sudah dibuka atau tidak dipakai pada opname ini.');
            $count->update(['lock_transactions' => false, 'unlocked_by' => $by->id, 'unlocked_at' => now()]);
        });
    }

    /**
     * Baris lembar input (x-dl::sheet). Hitung buta: stok sistem TIDAK ikut dikirim ke browser. $trace (tampilan HP): ikut siapa (by_id, by) dan jam (at) terakhir yang mengisi baris, untuk peringatan lunak.
     *
     * @return array<int, array<string, mixed>>
     */
    public function entryRows(StockCount $count, bool $trace = false): array
    {
        return $this->ordered($count)->map(fn (StockCountLine $l) => [
            'id' => $l->id, 'code' => $l->batch->code, 'name' => $l->batch->name, 'batch' => $this->batch($l->batch), 'location' => $l->batch->location ?? '', 'unit' => $l->batch->unit,
            'counted_qty' => $l->counted_qty ?? '', 'counted_by' => $l->counted_by ?? '', 'skipped' => $l->skipped, 'note' => $l->note ?? '', 'filled' => $l->isComplete() ? 'yes' : 'no', 'rev' => $l->rev,
        ] + ($count->blind ? [] : ['system_qty' => $l->system_qty, 'diff' => $l->finalQty() === null ? '' : $l->diff(), 'tone' => $l->overThreshold($count->threshold_pct) ? 'danger' : ''])
            + ($trace ? ['by_id' => $l->entered_by, 'by' => $l->enteredBy?->name ?? '', 'at' => $l->entered_at?->format('H:i') ?? ''] : []))->values()->all();
    }

    /**
     * Baris layar tinjau. Nilai (selisih x biaya rata-rata snapshot) hanya untuk Supervisor.
     *
     * @return array<int, array<string, mixed>>
     */
    public function reviewRows(StockCount $count, bool $withValue): array
    {
        return $this->ordered($count)->map(fn (StockCountLine $l) => [
            'id' => $l->id, 'code' => $l->batch->code, 'name' => $l->batch->name, 'batch' => $this->batch($l->batch), 'unit' => $l->batch->unit, 'system_qty' => $l->system_qty,
            'physical' => $l->finalQty() ?? '', 'diff' => $l->diff(), 'pct' => $l->pct() ?? '', 'recount_qty' => $l->recount_qty ?? '', 'reason' => $l->reason ?? '', 'entered_by' => $l->enteredBy?->name ?? '',
            'state' => $l->skipped && $l->recount_qty === null ? 'Tidak dihitung' : ($l->recount_qty !== null ? 'Dihitung ulang' : ($l->added_manually ? 'Ditambah manual' : '')),
            'flag' => $l->overThreshold($count->threshold_pct) ? 'Besar' : '', 'tone' => $l->overThreshold($count->threshold_pct) ? 'danger' : '', 'has_diff' => $l->diff() != 0.0 ? 'yes' : 'no', 'big' => $l->overThreshold($count->threshold_pct) ? 'yes' : 'no',
        ] + ($withValue ? ['value' => $l->value()] : []))->values()->all();
    }

    /**
     * Ringkasan: jumlah baris, terisi, selisih, lebih, kurang, dan nilai lebih / kurang (bcmath, dibulatkan sekali).
     *
     * @return array{lines: int, filled: int, diffs: int, over_lines: int, under_lines: int, over_value: float, under_value: float, net_value: float, counted: int}
     */
    public function summary(StockCount $count): array
    {
        $lines = $count->lines()->get();
        $over = $lines->filter(fn (StockCountLine $l) => $l->diff() > 0);
        $under = $lines->filter(fn (StockCountLine $l) => $l->diff() < 0);
        $pairs = fn (Collection $c) => $c->map(fn (StockCountLine $l) => [$l->diff(), $l->unit_cost]);

        return [
            'lines' => $lines->count(), 'filled' => $lines->filter->isComplete()->count(), 'counted' => $lines->filter(fn (StockCountLine $l) => $l->finalQty() !== null)->count(),
            'diffs' => $over->count() + $under->count(), 'over_lines' => $over->count(), 'under_lines' => $under->count(),
            'over_value' => Decimal::total($pairs($over)), 'under_value' => Decimal::total($pairs($under)), 'net_value' => Decimal::total($pairs($over->concat($under))),
        ];
    }

    /** @return Collection<int, StockCountLine> urut lokasi (tanpa lokasi di akhir) lalu kode batch */
    public function ordered(StockCount $count): Collection
    {
        return $count->lines()->with(['batch', 'enteredBy'])->get()
            ->sortBy(fn (StockCountLine $l) => [$l->batch->location === null || $l->batch->location === '' ? 1 : 0, (string) $l->batch->location, $l->batch->code])->values();
    }

    private function batch(Batch $item): string
    {
        return $item->expiry_date ? $item->expiry_date->format('d-m-Y') : '—';
    }

    private function qty(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (! is_numeric($v) || (float) $v < 0 || (float) $v > 999999999999.999) {
            throw ValidationException::withMessages(['counted_qty' => 'Isi angka 0 atau lebih (kosong = belum dihitung).']);
        }

        return round((float) $v, 3);
    }

    // Daftar kode batch untuk pesan (maksimal 12, sisanya dihitung).
    private function codes(Collection $lines, ?Collection $items = null): string
    {
        $codes = $lines->map(fn (StockCountLine $l) => $items ? $items[$l->batch_id]->code : $l->batch->code);

        return $codes->take(12)->implode(', ').($codes->count() > 12 ? ' dan '.($codes->count() - 12).' lainnya' : '');
    }

    private function fmt(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
    }

    private function lockAndAssert(StockCount $count, array $allowed): void
    {
        $count->setRawAttributes(StockCount::query()->lockForUpdate()->findOrFail($count->id)->getAttributes(), true);
        abort_unless(in_array($count->status, $allowed, true), 422, "Aksi ini tidak diizinkan pada status Stok Opname sekarang ({$count->statusLabel()}).");
    }

    // Dua penyimpan bersamaan bisa membaca nomor terakhir yang sama: kolom unik menolak yang kalah, lalu nomor berikutnya diambil lagi (pola sama dengan nomor dokumen stok).
    private function createNumbered(array $attributes): StockCount
    {
        for ($try = 1; ; $try++) {
            $number = $this->nextNumber();
            try {
                return DB::transaction(fn () => StockCount::create($attributes + ['doc_no' => $number]));
            } catch (UniqueConstraintViolationException $e) {
                if ($try >= 5) {
                    throw $e;
                }
            }
        }
    }

    // SO-YYMM-NNNN, urut per bulan.
    private function nextNumber(): string
    {
        $stem = 'SO-'.now()->format('ym').'-';
        $last = StockCount::query()->where('doc_no', 'like', $stem.'%')->orderByDesc('doc_no')->value('doc_no');

        return $stem.str_pad((string) ($last ? (int) substr($last, -4) + 1 : 1), 4, '0', STR_PAD_LEFT);
    }
}
