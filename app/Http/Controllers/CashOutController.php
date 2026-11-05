<?php

namespace App\Http\Controllers;

use App\Http\Requests\CashOutRequest;
use App\Models\CashIn;
use App\Models\CashPurpose;
use App\Models\CashOut;
use App\Support\Text;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/* REMARK
 * Tujuan       : Uang Keluar (KSK) modul Kas: daftar, formulir satu Uang Keluar wajib ditaut ke satu Uang Masuk (sumber dananya); kas asal ikut otomatis kas tujuan Uang Masuk itu. Siklus sama Uang Masuk / Purchase.
 *                Saat Approve, sisa saldo Uang Masuk dicek ulang (terkunci) supaya tidak terpakai dua kali oleh Uang Keluar lain yang disetujui bersamaan.
 * Sumber hulu  : k_cash_out, k_cash_in.
 * Output       : grid Uang Keluar, form Uang Keluar.
 * Dipakai oleh : routes/web.php (cash-out.*).
 * Status       : Aktif.
 */
class CashOutController extends Controller
{
    public function index()
    {
        $rows = CashOut::query()->with(['cashIn.cashAccount', 'creator'])->orderByDesc('doc_date')->orderByDesc('id')->get()->map(fn (CashOut $c) => [
            'id' => $c->id, 'doc_no' => $c->doc_no ?? '(draf)', 'doc_date' => $c->doc_date->format('Y-m-d'), 'cash_in' => $c->cashIn->number().' ('.$c->cashIn->source_name.')',
            'cash_account' => $c->cashIn->cashAccount->name, 'amount' => (float) $c->amount, 'purpose' => $c->purpose, 'status' => $c->statusLabel(), 'open' => in_array($c->status, ['approval', 'approved'], true),
        ])->all();

        return view('cash-out.index', ['rows' => $rows]);
    }

    public function create()
    {
        return view('cash-out.form', $this->formData(new CashOut(['doc_date' => today(), 'status' => 'draft'])));
    }

    public function show(CashOut $cashOut)
    {
        return view('cash-out.form', $this->formData($cashOut));
    }

    public function store(CashOutRequest $request): RedirectResponse
    {
        $cashOut = DB::transaction(function () use ($request) {
            $cashOut = new CashOut($request->safe()->only(['doc_date', 'cash_in_id', 'amount', 'purpose', 'purpose_id', 'description']) + ['status' => 'draft', 'created_by' => $request->user()->id]);
            if ($request->hasFile('attachment')) {
                $cashOut->attachment = $request->file('attachment')->store('out', 'kas');
            }
            $cashOut->save();

            return $cashOut;
        });

        return redirect()->route('cash-out.show', $cashOut)->with('status', 'Draf Uang Keluar disimpan. Nomor dibuat saat Confirm.');
    }

    public function update(CashOutRequest $request, CashOut $cashOut): RedirectResponse
    {
        $action = $request->input('_action', 'save');
        $user = $request->user();
        $reason = Text::of($request->input('_reason'));
        $owner = $user->id === $cashOut->created_by;

        $message = DB::transaction(function () use ($action, $request, $cashOut, $user, $reason, $owner) {
            $cashOut = CashOut::query()->lockForUpdate()->findOrFail($cashOut->id);
            $need = fn (array $from) => abort_unless(in_array($cashOut->status, $from, true), 422, 'Status Uang Keluar sudah berubah. Muat ulang halaman.');

            switch ($action) {
                case 'save':
                case 'confirm':
                    $need(['draft']);
                    abort_unless($owner || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang mengubah draf ini.');
                    $cashOut->fill($request->safe()->only(['doc_date', 'cash_in_id', 'amount', 'purpose', 'purpose_id', 'description']));
                    if ($request->hasFile('attachment')) {
                        $cashOut->attachment = $request->file('attachment')->store('out', 'kas');
                    }
                    if ($action === 'confirm') {
                        $cashOut->fill(['doc_no' => $cashOut->doc_no ?? CashOut::nextNo($cashOut->doc_date), 'status' => 'approval', 'submitted_at' => now()]);
                    }
                    $cashOut->save();

                    return $action === 'confirm' ? "{$cashOut->doc_no} dikonfirmasi dan menunggu persetujuan Supervisor." : 'Draf disimpan.';
                case 'approve':
                    $need(['approval']);
                    abort_unless($user->canApprove(), 403, 'Hanya Supervisor yang menyetujui atau menolak.');
                    abort_if($owner, 403, 'Pembuat tidak boleh menyetujui atau menolak Uang Keluar miliknya sendiri.');
                    $cashIn = CashIn::query()->lockForUpdate()->find($cashOut->cash_in_id);
                    abort_if(! $cashIn || (float) $cashOut->amount > $cashIn->remainingAmount(), 422, 'Sisa saldo Uang Masuk tidak cukup lagi (sudah terpakai transaksi lain).');
                    $cashOut->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);

                    return "{$cashOut->doc_no} disetujui.";
                case 'reject':
                    $need(['approval']);
                    abort_unless($user->canApprove(), 403, 'Hanya Supervisor yang menyetujui atau menolak.');
                    abort_if($owner, 403, 'Pembuat tidak boleh menyetujui atau menolak Uang Keluar miliknya sendiri.');
                    abort_if($reason === '', 422, 'Isi alasan penolakan.');
                    $cashOut->update(['status' => 'rejected', 'approved_by' => $user->id, 'approved_at' => now(), 'reason' => $reason]);

                    return "{$cashOut->doc_no} ditolak.";
                case 'cancel':
                    $need(['approval', 'approved']);
                    abort_unless($owner || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang membatalkan Uang Keluar ini.');
                    abort_if($reason === '', 422, 'Isi alasan pembatalan.');
                    $cashOut->update(['status' => 'cancelled', 'reason' => $reason]);

                    return "{$cashOut->doc_no} dibatalkan.";
                case 'delete':
                    $need(['draft']);
                    abort_unless($owner || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang menghapus draf ini.');
                    $cashOut->delete();

                    return 'Draf Uang Keluar dihapus.';
                case 'duplicate':
                    $need(['rejected', 'cancelled']);
                    $copy = $cashOut->replicate(['doc_no', 'status', 'submitted_at', 'approved_by', 'approved_at', 'reason', 'attachment']);
                    $copy->fill(['doc_date' => today(), 'status' => 'draft', 'created_by' => $user->id]);
                    $copy->save();
                    $this->copied = $copy;

                    return "Draf baru dibuat dari {$cashOut->doc_no}.";
                default:
                    abort(422, 'Aksi tidak dikenal.');
            }
        });

        if ($action === 'delete') {
            return redirect()->route('cash-out.index')->with('status', $message);
        }

        return redirect()->route('cash-out.show', $action === 'duplicate' ? $this->copied : $cashOut)->with('status', $message);
    }

    private ?CashOut $copied = null;

    public function file(CashOut $cashOut): StreamedResponse
    {
        abort_unless($cashOut->attachment && Storage::disk('kas')->exists($cashOut->attachment), 404);

        return Storage::disk('kas')->response($cashOut->attachment, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=0']);
    }

    private function formData(CashOut $cashOut): array
    {
        $log = [];
        if ($cashOut->exists) {
            $log[] = ['at' => $cashOut->created_at->format('Y-m-d H:i:s'), 'by' => $cashOut->creator->name, 'action' => 'Dibuat', 'note' => null];
            if ($cashOut->submitted_at) {
                $log[] = ['at' => $cashOut->submitted_at->format('Y-m-d H:i:s'), 'by' => $cashOut->creator->name, 'action' => 'Dikonfirmasi', 'note' => null];
            }
            if ($cashOut->approved_at) {
                $log[] = ['at' => $cashOut->approved_at->format('Y-m-d H:i:s'), 'by' => $cashOut->approver?->name ?? '-', 'action' => $cashOut->status === 'rejected' ? 'Ditolak' : 'Disetujui', 'note' => $cashOut->status === 'rejected' ? $cashOut->reason : null];
            }
            if ($cashOut->status === 'cancelled') {
                $log[] = ['at' => $cashOut->updated_at->format('Y-m-d H:i:s'), 'by' => '-', 'action' => 'Dibatalkan', 'note' => $cashOut->reason];
            }
        }
        $approved = CashIn::query()->where('status', 'approved')
            ->when($cashOut->cash_in_id, fn ($q) => $q->orWhere('id', $cashOut->cash_in_id))
            ->orderByDesc('doc_date')->get();
        $cashIns = $approved->mapWithKeys(fn (CashIn $c) => [$c->id => $c->number().' — '.$c->source_name.' — sisa Rp '.number_format($c->remainingAmount(), 0, ',', '.')])->all();
        // Sisa saldo per Uang Masuk (angka) untuk peringatan langsung di form bila Jumlah melebihi sisa.
        $remaining = $approved->mapWithKeys(fn (CashIn $c) => [$c->id => $c->remainingAmount()])->all();
        $accountNames = $approved->mapWithKeys(fn (CashIn $c) => [$c->id => $c->cashAccount?->name])->all();

        return ['cashOut' => $cashOut, 'log' => $log, 'cashIns' => $cashIns, 'remaining' => $remaining, 'accountNames' => $accountNames, 'purposes' => CashPurpose::query()->where('is_active', true)->orderBy('name')->pluck('name', 'name')->all()];
    }
}
