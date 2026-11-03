<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseOrderRequest;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Support\PurchaseOrderPrint;
use App\Support\Text;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Pesanan Pembelian (PO, modul PUO): daftar, formulir satu pemasok banyak barang, dan siklus: Simpan (draf), Ajukan, Setujui / Tolak (Supervisor, bukan pembuatnya), Batalkan (alasan, selama belum ada penerimaan), Hapus (draf), Duplicate (dari ditolak / dibatalkan). Cetak setelah Disetujui.
 *                Pemasok memberi syarat bayar awal. Baris yang berasal dari PP mengingat PP-nya (tetap terhubung selama barangnya tidak diganti); setiap perubahan menghitung ulang jumlah dipesan dan status PP asal.
 * Sumber hulu  : p_order, p_order_line, m_supplier, m_item, p_request_line.
 * Output       : grid PO, form PO, cetakan PO.
 * Dipakai oleh : routes/web.php (po.*), PurchaseDecisionController (membuat draf).
 * Status       : Aktif.
 */
class PurchaseOrderController extends Controller
{
    public function index()
    {
        $rows = PurchaseOrder::query()->with(['supplier', 'lines'])->orderByDesc('doc_date')->orderByDesc('id')->get()->map(fn (PurchaseOrder $o) => [
            'id' => $o->id, 'doc_no' => $o->doc_no ?? '(draf)', 'doc_date' => $o->doc_date->format('Y-m-d'), 'supplier' => $o->supplier->name, 'need_date' => $o->need_date?->format('Y-m-d'),
            'total' => $o->total(), 'received' => $o->receivedPct().'%', 'state' => $o->statusLabel(), 'open' => $o->status === 'approval',
        ])->all();

        return view('purchase-orders.index', ['rows' => $rows]);
    }

    public function create(Request $request)
    {
        $supplier = Supplier::query()->where('is_active', true)->find($request->query('supplier'));

        return view('purchase-orders.form', $this->formData(new PurchaseOrder(['doc_date' => today(), 'status' => 'draft', 'supplier_id' => $supplier?->id, 'payment_term_days' => $supplier?->payment_term_days, 'vat_pct' => 0])));
    }

    public function show(PurchaseOrder $po)
    {
        $po->syncReceived();   // RCV yang dibatalkan langsung di Stok tidak boleh tetap dihitung diterima
        return view('purchase-orders.form', $this->formData($po));
    }

    public function store(PurchaseOrderRequest $request): RedirectResponse
    {
        $po = DB::transaction(function () use ($request) {
            $po = new PurchaseOrder(array_merge($request->safe()->only(['doc_date', 'supplier_id', 'payment_term_days', 'need_date', 'ship_to', 'note']), ['vat_pct' => $request->input('vat_pct') ?: 0, 'status' => 'draft', 'created_by' => $request->user()->id]));
            $po->save();   // draf belum bernomor: nomor lahir saat Confirm
            $this->saveLines($po, $request->validated('lines'));
            $this->syncRequests($po);

            return $po;
        });

        return redirect()->route('po.show', $po)->with('status', 'Draf PO disimpan. Nomor PO dibuat saat Confirm.');
    }

    // Siklus: save, confirm (draf -> diajukan), approve / reject (Supervisor bukan pembuat), cancel (alasan, belum ada penerimaan), delete (draf), duplicate (ditolak / dibatalkan -> draf baru).
    public function update(PurchaseOrderRequest $request, PurchaseOrder $po): RedirectResponse
    {
        $action = $request->input('_action', 'save');
        $user = $request->user();
        $reason = Text::of($request->input('_reason'));
        $owner = $user->id === $po->created_by;
        $copy = null;

        $message = DB::transaction(function () use ($action, $request, $po, $user, $reason, $owner, &$copy) {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);   // status dibaca ulang di dalam kunci: dua klik tidak melompati siklus
            $need = fn (array $from) => abort_unless(in_array($po->status, $from, true), 422, 'Status PO sudah berubah. Muat ulang halaman.');

            switch ($action) {
                case 'save':
                case 'confirm':
                    $need(['draft']);
                    abort_unless($owner || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang mengubah draf ini.');
                    $po->update(array_merge($request->safe()->only(['doc_date', 'supplier_id', 'payment_term_days', 'need_date', 'ship_to', 'note']), ['vat_pct' => $request->input('vat_pct') ?: 0]));
                    $this->saveLines($po, $request->validated('lines'));
                    $message = 'Draf disimpan.';
                    if ($action === 'confirm') {
                        $po->update(['doc_no' => $po->doc_no ?? PurchaseOrder::nextNo($po->doc_date), 'status' => 'approval', 'submitted_at' => now()]);   // Confirm: nomor lahir, masuk antrean persetujuan
                        $message = "{$po->doc_no} dikonfirmasi dan menunggu persetujuan Supervisor.";
                    }
                    break;
                case 'approve':
                case 'reject':
                    $need(['approval']);
                    abort_unless($user->canApprove(), 403, 'Hanya Supervisor yang menyetujui atau menolak.');
                    abort_if($owner, 403, 'Pembuat tidak boleh menyetujui atau menolak PO-nya sendiri.');
                    abort_if($action === 'reject' && $reason === '', 422, 'Isi alasan penolakan.');
                    $po->update(['status' => $action === 'approve' ? 'approved' : 'rejected', 'approved_by' => $user->id, 'approved_at' => now(), 'reason' => $action === 'reject' ? $reason : null]);
                    $message = $action === 'approve' ? "{$po->doc_no} disetujui." : "{$po->doc_no} ditolak.";
                    break;
                case 'cancel':
                    $need(['approval', 'approved']);
                    abort_unless($owner || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang membatalkan PO ini.');
                    abort_if($po->lines()->where('received_qty', '>', 0)->exists(), 422, 'PO ini sudah ada penerimaan: tidak bisa dibatalkan.');
                    abort_if($reason === '', 422, 'Isi alasan pembatalan.');
                    $po->update(['status' => 'cancelled', 'reason' => $reason]);
                    $message = "{$po->doc_no} dibatalkan.";
                    break;
                case 'delete':
                    $need(['draft']);
                    abort_unless($owner || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang menghapus draf ini.');
                    $requests = $this->requestIds($po);
                    $po->delete();
                    PurchaseRequest::query()->whereIn('id', $requests)->get()->each->syncOrdered();

                    return 'Draf PO dihapus.';
                case 'duplicate':
                    $need(['rejected', 'cancelled']);
                    $copy = $po->replicate(['doc_no', 'status', 'submitted_at', 'approved_by', 'approved_at', 'reason']);
                    $copy->fill(['doc_date' => today(), 'status' => 'draft', 'created_by' => $user->id]);
                    $copy->save();
                    $this->saveLines($copy, $po->lines->map(fn ($l) => ['item_id' => $l->item_id, 'item_name' => $l->item_name, 'qty' => $l->qty, 'unit' => $l->unit, 'price' => $l->price, 'note' => $l->note, 'request_line_id' => $l->request_line_id])->all());
                    $this->syncRequests($copy);

                    return "Draf baru dibuat dari {$po->doc_no}.";
                default:
                    abort(422, 'Aksi tidak dikenal.');
            }
            $this->syncRequests($po);

            return $message;
        });

        if ($action === 'delete') {
            return redirect()->route('po.index')->with('status', $message);
        }

        return redirect()->route('po.show', $copy ?? $po)->with('status', $message);
    }

    // Cetakan setelah Disetujui (A5 sampai 5 baris, A4 di atasnya).
    public function print(Request $request, PurchaseOrder $po)
    {
        abort_unless(in_array($po->status, ['approved', 'partial', 'done'], true), 403, 'PO dicetak setelah Disetujui.');

        return view('purchase-orders.print', ['doc' => PurchaseOrderPrint::load($po, $request->user()->name), 'po' => $po]);
    }

    // Baris draf diganti seluruhnya; link ke baris PP asal dipertahankan lewat kecocokan barang (barang diganti = link lepas). Harga dan satuan kosong tidak diisi otomatis di sini.
    public function saveLines(PurchaseOrder $po, array $rows): void
    {
        $links = $po->lines()->whereNotNull('request_line_id')->get()->groupBy(fn ($l) => $this->key($l->item_id, $l->item_name))->map(fn ($g) => $g->pluck('request_line_id')->all())->all();
        $po->lines()->delete();
        $items = Item::query()->whereIn('id', collect($rows)->pluck('item_id')->filter())->get()->keyBy('id');
        foreach ($rows as $row) {
            $item = $items[$row['item_id'] ?? 0] ?? null;
            $name = $item ? null : Text::of($row['item_name'] ?? '');
            $key = $this->key($item?->id, $name);
            $link = $row['request_line_id'] ?? (isset($links[$key]) ? array_shift($links[$key]) : null);
            $po->lines()->create([
                'request_line_id' => $link, 'item_id' => $item?->id, 'item_name' => $name,
                'qty' => $row['qty'], 'unit' => filled($row['unit'] ?? null) ? $row['unit'] : $item->unit,
                'price' => filled($row['price'] ?? null) ? $row['price'] : 0, 'note' => Text::of($row['note'] ?? '') ?: null,
            ]);
        }
    }

    private function key(?int $itemId, ?string $name): string
    {
        return $itemId ? 'i'.$itemId : 'n'.mb_strtolower((string) $name);
    }

    private function requestIds(PurchaseOrder $po): array
    {
        return DB::table('p_order_line as l')->join('p_request_line as r', 'r.id', '=', 'l.request_line_id')->where('l.order_id', $po->id)->pluck('r.request_id')->unique()->all();
    }

    private function syncRequests(PurchaseOrder $po): void
    {
        PurchaseRequest::query()->whereIn('id', $this->requestIds($po))->get()->each->syncOrdered();
    }

    private function formData(PurchaseOrder $po): array
    {
        $rows = old('lines') ?? $po->lines()->with(['item', 'requestLine.request'])->get()->map(fn ($l) => [
            'item_id' => $l->item_id, 'item_id_label' => $l->item ? $l->item->code.' · '.$l->item->name : null, 'item_name' => $l->item_name, 'qty' => (float) $l->qty, 'unit' => $l->unit, 'price' => $l->price, 'received_qty' => (float) $l->received_qty,
            'pp' => $l->requestLine?->request?->doc_no, 'note' => $l->note,
        ])->all();
        $log = [];
        if ($po->exists) {
            $log[] = ['at' => $po->created_at->format('Y-m-d H:i:s'), 'by' => $po->creator->name, 'action' => 'Dibuat', 'note' => null];
            if ($po->submitted_at) {
                $log[] = ['at' => $po->submitted_at->format('Y-m-d H:i:s'), 'by' => $po->creator->name, 'action' => 'Dikonfirmasi', 'note' => null];
            }
            if ($po->approved_at) {
                $log[] = ['at' => $po->approved_at->format('Y-m-d H:i:s'), 'by' => $po->approver?->name ?? '-', 'action' => $po->status === 'rejected' ? 'Ditolak' : 'Disetujui', 'note' => $po->status === 'rejected' ? $po->reason : null];
            }
            if ($po->status === 'cancelled') {
                $log[] = ['at' => $po->updated_at->format('Y-m-d H:i:s'), 'by' => '-', 'action' => 'Dibatalkan', 'note' => $po->reason];
            }
        }

        return [
            'po' => $po, 'rows' => $rows, 'log' => $log,
            'suppliers' => Supplier::query()->forSelect()->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $po->supplier_id))->pluck('name', 'id')->all(),
            'terms' => Supplier::query()->whereNotNull('payment_term_days')->pluck('payment_term_days', 'id')->all(),
        ];
    }
}
