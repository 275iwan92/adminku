<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseRequestRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Item;
use App\Models\PurchaseRequest;
use App\Support\Text;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Permintaan Pembelian (PP) modul Purchase (PUP): daftar, formulir satu PP banyak barang, dan siklus: Simpan (draf), Konfirmasi (diajukan), Setujui / Tolak (Supervisor, bukan pembuatnya), Batalkan (alasan), Hapus (draf), Duplicate (dari ditolak / dibatalkan).
 *                Satuan kosong diisi satuan barang; estimasi harga satuan kosong diisi harga beli terakhir barang (bila pernah dibeli).
 * Sumber hulu  : p_request, p_request_line, m_item, m_department, Penerimaan Barang (harga terakhir).
 * Output       : grid PP, form PP.
 * Dipakai oleh : routes/web.php (pr.*).
 * Status       : Aktif.
 */
class PurchaseRequestController extends Controller
{
    public function index()
    {
        $rows = PurchaseRequest::query()->with(['department', 'creator', 'lines'])->orderByDesc('doc_date')->orderByDesc('id')->get()->map(fn (PurchaseRequest $r) => [
            'id' => $r->id, 'doc_no' => $r->doc_no, 'doc_date' => $r->doc_date->format('Y-m-d'), 'department' => $r->department?->name, 'requester' => $r->creator->name,
            'need_date' => $r->need_date?->format('Y-m-d'), 'total' => $r->total(), 'items' => $r->lines->count(), 'state' => $r->statusLabel(), 'open' => in_array($r->status, ['approval', 'approved'], true),
        ])->all();

        return view('purchase-requests.index', ['rows' => $rows]);
    }

    public function create(Request $request)
    {
        $dept = Employee::query()->where('user_id', $request->user()->id)->value('department_id');

        return view('purchase-requests.form', $this->formData(new PurchaseRequest(['doc_date' => today(), 'department_id' => $dept, 'status' => 'draft'])));
    }

    public function show(PurchaseRequest $pr)
    {
        return view('purchase-requests.form', $this->formData($pr));
    }

    public function store(PurchaseRequestRequest $request): RedirectResponse
    {
        $pr = DB::transaction(function () use ($request) {
            $pr = new PurchaseRequest($request->safe()->only(['doc_date', 'department_id', 'need_date', 'note']) + ['status' => 'draft', 'created_by' => $request->user()->id]);
            $pr->doc_no = PurchaseRequest::nextNo($pr->doc_date ?? today());
            $pr->save();
            $this->saveLines($pr, $request->validated('lines'));

            return $pr;
        });

        return redirect()->route('pr.show', $pr)->with('status', "{$pr->doc_no} disimpan sebagai draf.");
    }

    // Siklus: save, confirm (draf -> diajukan), approve / reject (Supervisor bukan pembuat), cancel (alasan), delete (draf), duplicate (ditolak / dibatalkan -> draf baru).
    public function update(PurchaseRequestRequest $request, PurchaseRequest $pr): RedirectResponse
    {
        $action = $request->input('_action', 'save');
        $user = $request->user();
        $reason = Text::of($request->input('_reason'));
        $owner = $user->id === $pr->created_by;

        $message = DB::transaction(function () use ($action, $request, $pr, $user, $reason, $owner) {
            $pr = PurchaseRequest::query()->lockForUpdate()->findOrFail($pr->id);   // status dibaca ulang di dalam kunci: dua klik tidak melompati siklus
            $need = fn (array $from) => abort_unless(in_array($pr->status, $from, true), 422, 'Status PP sudah berubah. Muat ulang halaman.');

            switch ($action) {
                case 'save':
                case 'confirm':
                    $need(['draft']);
                    abort_unless($owner || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang mengubah draf ini.');
                    $pr->update($request->safe()->only(['doc_date', 'department_id', 'need_date', 'note']));
                    $pr->lines()->delete();
                    $this->saveLines($pr, $request->validated('lines'));
                    if ($action === 'confirm') {
                        $pr->update(['status' => 'approval', 'submitted_at' => now()]);

                        return "{$pr->doc_no} diajukan dan menunggu persetujuan Supervisor.";
                    }

                    return 'Draf disimpan.';
                case 'approve':
                case 'reject':
                    $need(['approval']);
                    abort_unless($user->isSupervisor(), 403, 'Hanya Supervisor yang menyetujui atau menolak.');
                    abort_if($owner, 403, 'Pembuat tidak boleh menyetujui atau menolak PP-nya sendiri.');
                    abort_if($action === 'reject' && $reason === '', 422, 'Isi alasan penolakan.');
                    $pr->update(['status' => $action === 'approve' ? 'approved' : 'rejected', 'approved_by' => $user->id, 'approved_at' => now(), 'reason' => $action === 'reject' ? $reason : null]);

                    return $action === 'approve' ? "{$pr->doc_no} disetujui." : "{$pr->doc_no} ditolak.";
                case 'cancel':
                    $need(['approval', 'approved']);
                    abort_unless($owner || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang membatalkan PP ini.');
                    abort_if($pr->lines()->where('ordered_qty', '>', 0)->exists(), 422, 'PP ini sudah dipesan sebagian: tidak bisa dibatalkan.');
                    abort_if($reason === '', 422, 'Isi alasan pembatalan.');
                    $pr->update(['status' => 'cancelled', 'reason' => $reason]);

                    return "{$pr->doc_no} dibatalkan.";
                case 'delete':
                    $need(['draft']);
                    abort_unless($owner || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang menghapus draf ini.');
                    $pr->delete();

                    return 'Draf PP dihapus.';
                case 'duplicate':
                    $need(['rejected', 'cancelled']);
                    $copy = $pr->replicate(['doc_no', 'status', 'submitted_at', 'approved_by', 'approved_at', 'reason']);
                    $copy->fill(['doc_date' => today(), 'status' => 'draft', 'created_by' => $user->id]);
                    $copy->doc_no = PurchaseRequest::nextNo(today());
                    $copy->save();
                    $this->saveLines($copy, $pr->lines->map(fn ($l) => ['item_id' => $l->item_id, 'item_name' => $l->item_name, 'qty' => $l->qty, 'unit' => $l->unit, 'est_price' => $l->est_price, 'note' => $l->note])->all());
                    $this->copied = $copy;

                    return "{$copy->doc_no} dibuat sebagai draf dari {$pr->doc_no}.";
                default:
                    abort(422, 'Aksi tidak dikenal.');
            }
        });

        if ($action === 'delete') {
            return redirect()->route('pr.index')->with('status', $message);
        }

        return redirect()->route('pr.show', $action === 'duplicate' ? $this->copied : $pr)->with('status', $message);
    }

    private ?PurchaseRequest $copied = null;

    // Baris lama diganti seluruhnya (belum ada yang merujuk id barisnya). Satuan dan estimasi harga kosong diisi dari master / pembelian terakhir.
    private function saveLines(PurchaseRequest $pr, array $rows): void
    {
        $items = Item::query()->whereIn('id', collect($rows)->pluck('item_id')->filter())->get()->keyBy('id');
        foreach ($rows as $row) {
            $item = $items[$row['item_id'] ?? 0] ?? null;
            $price = $row['est_price'] ?? null;
            $pr->lines()->create([
                'item_id' => $item?->id,
                'item_name' => $item ? null : Text::of($row['item_name'] ?? ''),
                'qty' => $row['qty'],
                'unit' => filled($row['unit'] ?? null) ? $row['unit'] : $item->unit,
                'est_price' => filled($price) ? $price : ($item ? PurchaseRequest::lastCost($item->id) : null),
                'note' => Text::of($row['note'] ?? '') ?: null,
            ]);
        }
    }

    private function formData(PurchaseRequest $pr): array
    {
        $lines = old('lines') ?? $pr->lines()->with('item')->get()->map(fn ($l) => [
            'item_id' => $l->item_id, 'item_name' => $l->item_name, 'qty' => (float) $l->qty, 'unit' => $l->unit, 'est_price' => $l->est_price, 'ordered_qty' => (float) $l->ordered_qty, 'note' => $l->note,
        ])->all();
        $log = [];
        if ($pr->exists) {
            $log[] = ['at' => $pr->created_at->format('Y-m-d H:i:s'), 'by' => $pr->creator->name, 'action' => 'Dibuat', 'note' => null];
            if ($pr->submitted_at) {
                $log[] = ['at' => $pr->submitted_at->format('Y-m-d H:i:s'), 'by' => $pr->creator->name, 'action' => 'Diajukan', 'note' => null];
            }
            if ($pr->approved_at) {
                $log[] = ['at' => $pr->approved_at->format('Y-m-d H:i:s'), 'by' => $pr->approver?->name ?? '-', 'action' => $pr->status === 'rejected' ? 'Ditolak' : 'Disetujui', 'note' => $pr->status === 'rejected' ? $pr->reason : null];
            }
            if ($pr->status === 'cancelled') {
                $log[] = ['at' => $pr->updated_at->format('Y-m-d H:i:s'), 'by' => '-', 'action' => 'Dibatalkan', 'note' => $pr->reason];
            }
        }

        return [
            'pr' => $pr, 'rows' => $lines, 'log' => $log,
            'departments' => Department::query()->where('is_active', true)->when($pr->department_id, fn ($q) => $q->orWhere('id', $pr->department_id))->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }
}
