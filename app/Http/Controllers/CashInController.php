<?php

namespace App\Http\Controllers;

use App\Http\Requests\CashInRequest;
use App\Models\CashAccount;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\CashSource;
use App\Support\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/* REMARK
 * Tujuan       : Uang Masuk (KSM) modul Kas: daftar, formulir satu Uang Masuk (kas tujuan, sumber dana, jumlah, lampiran), dan siklus: Simpan (draf), Konfirmasi (diajukan), Setujui / Tolak (Supervisor, bukan pembuatnya), Batalkan (alasan), Hapus (draf), Duplicate.
 *                Uang Masuk yang sudah punya Uang Keluar tertaut (diajukan / disetujui) tidak bisa dibatalkan. Setelah Disetujui, form menampilkan rincian Uang Keluar yang menautnya (terpakai / sisa).
 * Sumber hulu  : k_cash_in, m_cash_account.
 * Output       : grid Uang Masuk, form Uang Masuk.
 * Dipakai oleh : routes/web.php (cash-in.*).
 * Status       : Aktif.
 */
class CashInController extends Controller
{
    public function index()
    {
        $rows = CashIn::query()->with(['cashAccount', 'creator'])->orderByDesc('doc_date')->orderByDesc('id')->get()->map(fn (CashIn $c) => [
            'id' => $c->id, 'doc_no' => $c->doc_no ?? '(draf)', 'doc_date' => $c->doc_date->format('Y-m-d'), 'cash_account' => $c->cashAccount->name, 'source_name' => $c->source_name,
            'amount' => (float) $c->amount, 'used' => $c->status === 'approved' ? $c->usedAmount() : null, 'remaining' => $c->status === 'approved' ? $c->remainingAmount() : null,
            'status' => $c->statusLabel(), 'open' => in_array($c->status, ['approval', 'approved'], true),
        ])->all();

        return view('cash-in.index', ['rows' => $rows]);
    }

    public function create()
    {
        return view('cash-in.form', $this->formData(new CashIn(['doc_date' => today(), 'status' => 'draft'])));
    }

    public function show(CashIn $cashIn)
    {
        return view('cash-in.form', $this->formData($cashIn));
    }

    // Tambah cepat rekening bank dari form Uang Masuk (tombol plus): bank + no. rekening wajib, atas nama boleh kosong, saldo awal 0. Balasan JSON 201 {id,name}. Bank + no. rekening kembar ditolak.
    public function storeBank(Request $request): JsonResponse
    {
        $data = $request->validate(['bank_name' => ['required', 'string', 'max:60'], 'account_no' => ['required', 'string', 'max:30'], 'account_holder' => ['nullable', 'string', 'max:100']]);
        $data = array_map(fn ($v) => Text::of($v), $data);
        abort_if(CashAccount::query()->where('type', 'bank')->where('bank_name', $data['bank_name'])->where('account_no', $data['account_no'])->exists(), 422, 'Rekening itu sudah ada.');
        $name = $data['bank_name'].' · '.$data['account_no'].($data['account_holder'] !== '' ? ' · '.$data['account_holder'] : '');
        $account = CashAccount::create(['account_holder' => $data['account_holder'] ?: null] + $data + ['name' => mb_substr($name, 0, 100), 'type' => 'bank', 'opening_balance' => 0]);

        return response()->json($account->only(['id', 'name']), 201);
    }

    public function store(CashInRequest $request): RedirectResponse
    {
        $cashIn = DB::transaction(function () use ($request) {
            $cashIn = new CashIn($request->safe()->only(['doc_date', 'cash_account_id', 'source_name', 'amount', 'description']) + ['status' => 'draft', 'created_by' => $request->user()->id]);
            if ($request->hasFile('attachment')) {
                $cashIn->attachment = $request->file('attachment')->store('in', 'kas');
            }
            $cashIn->save();   // draf belum bernomor: nomor lahir saat Confirm

            return $cashIn;
        });

        return redirect()->route('cash-in.show', $cashIn)->with('status', 'Draf Uang Masuk disimpan. Nomor dibuat saat Confirm.');
    }

    // Siklus: save, confirm (draf -> diajukan), approve / reject (Supervisor bukan pembuat), cancel (alasan), delete (draf), duplicate (ditolak / dibatalkan -> draf baru).
    public function update(CashInRequest $request, CashIn $cashIn): RedirectResponse
    {
        $action = $request->input('_action', 'save');
        $user = $request->user();
        $reason = Text::of($request->input('_reason'));
        $owner = $user->id === $cashIn->created_by;

        $message = DB::transaction(function () use ($action, $request, $cashIn, $user, $reason, $owner) {
            $cashIn = CashIn::query()->lockForUpdate()->findOrFail($cashIn->id);
            $need = fn (array $from) => abort_unless(in_array($cashIn->status, $from, true), 422, 'Status Uang Masuk sudah berubah. Muat ulang halaman.');

            switch ($action) {
                case 'save':
                case 'confirm':
                    $need(['draft']);
                    abort_unless($owner || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang mengubah draf ini.');
                    $cashIn->fill($request->safe()->only(['doc_date', 'cash_account_id', 'source_name', 'amount', 'description']));
                    if ($request->hasFile('attachment')) {
                        $cashIn->attachment = $request->file('attachment')->store('in', 'kas');
                    }
                    if ($action === 'confirm') {
                        $cashIn->fill(['doc_no' => $cashIn->doc_no ?? CashIn::nextNo($cashIn->doc_date), 'status' => 'approval', 'submitted_at' => now()]);
                    }
                    $cashIn->save();

                    return $action === 'confirm' ? "{$cashIn->doc_no} dikonfirmasi dan menunggu persetujuan Supervisor." : 'Draf disimpan.';
                case 'approve':
                case 'reject':
                    $need(['approval']);
                    abort_unless($user->canApprove(), 403, 'Hanya Supervisor yang menyetujui atau menolak.');
                    abort_if($owner, 403, 'Pembuat tidak boleh menyetujui atau menolak Uang Masuk miliknya sendiri.');
                    abort_if($action === 'reject' && $reason === '', 422, 'Isi alasan penolakan.');
                    $cashIn->update(['status' => $action === 'approve' ? 'approved' : 'rejected', 'approved_by' => $user->id, 'approved_at' => now(), 'reason' => $action === 'reject' ? $reason : null]);

                    return $action === 'approve' ? "{$cashIn->doc_no} disetujui." : "{$cashIn->doc_no} ditolak.";
                case 'cancel':
                    $need(['approval', 'approved']);
                    abort_unless($owner || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang membatalkan Uang Masuk ini.');
                    abort_if($cashIn->cashOuts()->whereIn('status', ['approval', 'approved'])->exists(), 422, 'Uang Masuk ini sudah punya Uang Keluar tertaut: tidak bisa dibatalkan.');
                    abort_if($reason === '', 422, 'Isi alasan pembatalan.');
                    $cashIn->update(['status' => 'cancelled', 'reason' => $reason]);

                    return "{$cashIn->doc_no} dibatalkan.";
                case 'delete':
                    $need(['draft']);
                    abort_unless($owner || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang menghapus draf ini.');
                    $cashIn->delete();

                    return 'Draf Uang Masuk dihapus.';
                case 'duplicate':
                    $need(['rejected', 'cancelled']);
                    $copy = $cashIn->replicate(['doc_no', 'status', 'submitted_at', 'approved_by', 'approved_at', 'reason', 'attachment']);
                    $copy->fill(['doc_date' => today(), 'status' => 'draft', 'created_by' => $user->id]);
                    $copy->save();
                    $this->copied = $copy;

                    return "Draf baru dibuat dari {$cashIn->doc_no}.";
                default:
                    abort(422, 'Aksi tidak dikenal.');
            }
        });

        if ($action === 'delete') {
            return redirect()->route('cash-in.index')->with('status', $message);
        }

        return redirect()->route('cash-in.show', $action === 'duplicate' ? $this->copied : $cashIn)->with('status', $message);
    }

    private ?CashIn $copied = null;

    public function file(CashIn $cashIn): StreamedResponse
    {
        abort_unless($cashIn->attachment && Storage::disk('kas')->exists($cashIn->attachment), 404);

        return Storage::disk('kas')->response($cashIn->attachment, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=0']);
    }

    private function formData(CashIn $cashIn): array
    {
        $log = [];
        if ($cashIn->exists) {
            $log[] = ['at' => $cashIn->created_at->format('Y-m-d H:i:s'), 'by' => $cashIn->creator->name, 'action' => 'Dibuat', 'note' => null];
            if ($cashIn->submitted_at) {
                $log[] = ['at' => $cashIn->submitted_at->format('Y-m-d H:i:s'), 'by' => $cashIn->creator->name, 'action' => 'Dikonfirmasi', 'note' => null];
            }
            if ($cashIn->approved_at) {
                $log[] = ['at' => $cashIn->approved_at->format('Y-m-d H:i:s'), 'by' => $cashIn->approver?->name ?? '-', 'action' => $cashIn->status === 'rejected' ? 'Ditolak' : 'Disetujui', 'note' => $cashIn->status === 'rejected' ? $cashIn->reason : null];
            }
            if ($cashIn->status === 'cancelled') {
                $log[] = ['at' => $cashIn->updated_at->format('Y-m-d H:i:s'), 'by' => '-', 'action' => 'Dibatalkan', 'note' => $cashIn->reason];
            }
        }
        $cashOuts = $cashIn->exists ? $cashIn->cashOuts()->orderByDesc('doc_date')->get()->map(fn (CashOut $o) => [
            'id' => $o->id, 'doc_no' => $o->doc_no ?? '(draf)', 'doc_date' => $o->doc_date->format('Y-m-d'), 'purpose' => $o->purpose, 'amount' => (float) $o->amount, 'status' => $o->statusLabel(),
        ])->all() : [];

        return [
            'cashIn' => $cashIn, 'log' => $log, 'cashOuts' => $cashOuts,
            // Saklar Tunai / Rekening bank: dropdown hanya rekening bank (aktif + rekening dokumen ini); awal saklar mengikuti jenis kas dokumen.
            'banks' => CashAccount::query()->where('type', 'bank')->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $cashIn->cash_account_id))->orderBy('name')->pluck('name', 'id')->all(),
            'kind' => $cashIn->cashAccount?->type === 'bank' ? 'bank' : 'cash',
            'sources' => CashSource::query()->orderBy('name')->pluck('name', 'name')->all(),
        ];
    }
}
