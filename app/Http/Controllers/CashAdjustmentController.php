<?php

namespace App\Http\Controllers;

use App\Http\Requests\CashAdjustmentRequest;
use App\Models\CashAccount;
use App\Models\CashAdjustment;
use App\Services\CashLedger;
use App\Support\Text;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Penyesuaian Kas (KSA): daftar dan formulir koreksi saldo satu kas. Siklus sama Uang Keluar: draf -> Confirm -> Approve / Reject (Supervisor bukan pembuat) -> boleh dibatalkan (alasan).
 *                Baris buku besar kas (CashLedger) baru ditulis saat Approve; pembatalan menulis baris pembalik. Penyesuaian kurang ditolak bila saldo kas tidak cukup.
 * Sumber hulu  : k_cash_adjust, m_cash_account.
 * Output       : grid Penyesuaian Kas, form Penyesuaian Kas.
 * Dipakai oleh : routes/web.php (cash-adjust.*).
 * Status       : Aktif.
 */
class CashAdjustmentController extends Controller
{
    private ?CashAdjustment $copied = null;

    public function index()
    {
        $rows = CashAdjustment::query()->with(['account', 'creator'])->orderByDesc('doc_date')->orderByDesc('id')->get()->map(fn (CashAdjustment $c) => [
            'id' => $c->id, 'doc_no' => $c->doc_no ?? '(draf)', 'doc_date' => $c->doc_date->format('Y-m-d'), 'cash_account' => $c->account?->name ?? '—',
            'direction' => CashAdjustment::DIRECTIONS[$c->direction] ?? $c->direction, 'amount' => $c->signedAmount(), 'cause' => $c->cause, 'status' => $c->statusLabel(), 'open' => in_array($c->status, ['approval', 'approved'], true),
        ])->all();

        return view('cash-adjust.index', ['rows' => $rows]);
    }

    public function create()
    {
        return view('cash-adjust.form', $this->formData(new CashAdjustment(['doc_date' => today(), 'status' => 'draft', 'direction' => 'add'])));
    }

    public function show(CashAdjustment $adjustment)
    {
        return view('cash-adjust.form', $this->formData($adjustment));
    }

    public function store(CashAdjustmentRequest $request): RedirectResponse
    {
        $doc = CashAdjustment::create($request->safe()->only(['doc_date', 'cash_account_id', 'direction', 'amount', 'cause']) + ['status' => 'draft', 'created_by' => $request->user()->id]);

        return redirect()->route('cash-adjust.show', $doc)->with('status', 'Draf Penyesuaian Kas disimpan. Nomor dibuat saat Confirm.');
    }

    public function update(CashAdjustmentRequest $request, CashAdjustment $adjustment): RedirectResponse
    {
        $action = $request->input('_action', 'save');
        $user = $request->user();
        $reason = Text::of($request->input('_reason'));
        $owner = $user->id === $adjustment->created_by;

        $message = DB::transaction(function () use ($action, $request, $adjustment, $user, $reason, $owner) {
            $doc = CashAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);
            $need = fn (array $from) => abort_unless(in_array($doc->status, $from, true), 422, 'Status Penyesuaian Kas sudah berubah. Muat ulang halaman.');

            switch ($action) {
                case 'save':
                case 'confirm':
                    $need(['draft']);
                    abort_unless($owner || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang mengubah draf ini.');
                    $doc->fill($request->safe()->only(['doc_date', 'cash_account_id', 'direction', 'amount', 'cause']));
                    if ($action === 'confirm') {
                        $doc->fill(['doc_no' => $doc->doc_no ?? CashAdjustment::nextNo($doc->doc_date), 'status' => 'approval', 'submitted_at' => now()]);
                    }
                    $doc->save();

                    return $action === 'confirm' ? "{$doc->doc_no} dikonfirmasi dan menunggu persetujuan Supervisor." : 'Draf disimpan.';
                case 'approve':
                    $need(['approval']);
                    abort_unless($user->canApprove(), 403, 'Hanya Supervisor yang menyetujui atau menolak.');
                    abort_if($owner, 403, 'Pembuat tidak boleh menyetujui atau menolak Penyesuaian Kas miliknya sendiri.');
                    $doc->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
                    app(CashLedger::class)->postAdjustment($doc, $user);   // galat validasi (dan batal seluruh transaksi) bila pengurangan melebihi saldo

                    return "{$doc->doc_no} disetujui.";
                case 'reject':
                    $need(['approval']);
                    abort_unless($user->canApprove(), 403, 'Hanya Supervisor yang menyetujui atau menolak.');
                    abort_if($owner, 403, 'Pembuat tidak boleh menyetujui atau menolak Penyesuaian Kas miliknya sendiri.');
                    abort_if($reason === '', 422, 'Isi alasan penolakan.');
                    $doc->update(['status' => 'rejected', 'approved_by' => $user->id, 'approved_at' => now(), 'reason' => $reason]);

                    return "{$doc->doc_no} ditolak.";
                case 'cancel':
                    $need(['approval', 'approved']);
                    abort_unless($owner || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang membatalkan Penyesuaian Kas ini.');
                    abort_if($reason === '', 422, 'Isi alasan pembatalan.');
                    app(CashLedger::class)->reverse('cash_adjust', $doc->id, $user);
                    $doc->update(['status' => 'cancelled', 'reason' => $reason]);

                    return "{$doc->doc_no} dibatalkan.";
                case 'delete':
                    $need(['draft']);
                    abort_unless($owner || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang menghapus draf ini.');
                    $doc->delete();

                    return 'Draf Penyesuaian Kas dihapus.';
                case 'duplicate':
                    $need(['rejected', 'cancelled']);
                    $copy = $doc->replicate(['doc_no', 'status', 'submitted_at', 'approved_by', 'approved_at', 'reason']);
                    $copy->fill(['doc_date' => today(), 'status' => 'draft', 'created_by' => $user->id]);
                    $copy->save();
                    $this->copied = $copy;

                    return "Draf baru dibuat dari {$doc->doc_no}.";
                default:
                    abort(422, 'Aksi tidak dikenal.');
            }
        });

        if ($action === 'delete') {
            return redirect()->route('cash-adjust.index')->with('status', $message);
        }

        return redirect()->route('cash-adjust.show', $action === 'duplicate' ? $this->copied : $adjustment)->with('status', $message);
    }

    private function formData(CashAdjustment $doc): array
    {
        $log = [];
        if ($doc->exists) {
            $log[] = ['at' => $doc->created_at->format('Y-m-d H:i:s'), 'by' => $doc->creator->name, 'action' => 'Dibuat', 'note' => null];
            if ($doc->submitted_at) {
                $log[] = ['at' => $doc->submitted_at->format('Y-m-d H:i:s'), 'by' => $doc->creator->name, 'action' => 'Dikonfirmasi', 'note' => null];
            }
            if ($doc->approved_at) {
                $log[] = ['at' => $doc->approved_at->format('Y-m-d H:i:s'), 'by' => $doc->approver?->name ?? '-', 'action' => $doc->status === 'rejected' ? 'Ditolak' : 'Disetujui', 'note' => $doc->status === 'rejected' ? $doc->reason : null];
            }
            if ($doc->status === 'cancelled') {
                $log[] = ['at' => $doc->updated_at->format('Y-m-d H:i:s'), 'by' => '-', 'action' => 'Dibatalkan', 'note' => $doc->reason];
            }
        }
        $accounts = CashAccount::query()->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $doc->cash_account_id))->orderBy('name')->get();

        return ['doc' => $doc, 'log' => $log, 'accounts' => $accounts->mapWithKeys(fn (CashAccount $a) => [$a->id => $a->name.' — saldo Rp '.number_format($a->currentBalance(), 0, ',', '.')])->all()];
    }
}
