<?php

namespace App\Http\Controllers;

use App\Enums\StockDocumentType;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Services\StockDocuments;
use App\Support\Text;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/* REMARK
 * Tujuan       : Penerimaan Pembelian (PV, modul PUV): barang datang dari satu PO Disetujui / Diterima sebagian. Draf (Simpan) lalu Konfirmasi: membuat dokumen Penerimaan Barang (RCV) di Stok dan langsung mengonfirmasinya (stok bertambah, pemasok + harga dari PO, surat jalan = No. Referensi),
 *                lalu menghitung ulang jumlah diterima dan status PO (Diterima sebagian / Selesai). Batalkan (Supervisor, alasan) membatalkan RCV-nya (stok dikembalikan) dan jumlah diterima PO kembali. Hapus hanya draf.
 *                Jumlah diterima tidak boleh melebihi sisa PO; barang yang masih teks bebas di PO (belum ada di master) tidak bisa diterima; kedaluwarsa wajib untuk barang yang melacak kedaluwarsa.
 * Sumber hulu  : p_receipt, p_receipt_line, p_order, p_order_line, StockDocuments (RCV).
 * Output       : grid PV, form PV.
 * Dipakai oleh : routes/web.php (pv.*), tombol Terima barang di PO.
 * Status       : Aktif.
 */
class PurchaseReceiptController extends Controller
{
    public function __construct(private readonly StockDocuments $documents) {}

    public function index()
    {
        $rows = PurchaseReceipt::query()->with(['order', 'supplier', 'stockDocument'])->orderByDesc('doc_date')->orderByDesc('id')->get()->map(fn (PurchaseReceipt $r) => [
            'id' => $r->id, 'doc_no' => $r->doc_no, 'doc_date' => $r->doc_date->format('Y-m-d'), 'order' => $r->order->doc_no, 'supplier' => $r->supplier->name,
            'ref_no' => $r->ref_no, 'rcv' => $r->stockDocument?->doc_no, 'state' => $r->statusLabel(),
        ])->all();

        return view('purchase-receipts.index', ['rows' => $rows]);
    }

    public function create(Request $request)
    {
        $order = PurchaseOrder::query()->whereIn('status', ['approved', 'partial'])->find($request->query('po'));

        return view('purchase-receipts.form', $this->formData(new PurchaseReceipt(['doc_date' => today(), 'status' => 'draft', 'order_id' => $order?->id, 'supplier_id' => $order?->supplier_id]), $order));
    }

    public function show(PurchaseReceipt $pv)
    {
        $pv->order->syncReceived();

        return view('purchase-receipts.form', $this->formData($pv, $pv->order));
    }

    public function store(Request $request): RedirectResponse
    {
        $order = PurchaseOrder::query()->whereIn('status', ['approved', 'partial'])->findOrFail($request->input('order_id'));
        $data = $this->validated($request);
        $pv = DB::transaction(function () use ($data, $order, $request) {
            $pv = new PurchaseReceipt(['doc_date' => $data['doc_date'], 'order_id' => $order->id, 'supplier_id' => $order->supplier_id, 'ref_no' => Text::of($data['ref_no'] ?? '') ?: null, 'receiver' => Text::of($data['receiver'] ?? '') ?: null,
                'note' => Text::of($data['note'] ?? '') ?: null, 'status' => 'draft', 'created_by' => $request->user()->id]);
            $pv->doc_no = PurchaseReceipt::nextNo($pv->doc_date);
            $pv->save();
            $this->saveLines($pv, $order, $this->lineRows($request, $order, null));

            return $pv;
        });

        return redirect()->route('pv.show', $pv)->with('status', "{$pv->doc_no} disimpan sebagai draf.");
    }

    // Siklus: save, confirm (RCV otomatis), cancel (Supervisor, alasan), delete (draf).
    public function update(Request $request, PurchaseReceipt $pv): RedirectResponse
    {
        $action = $request->input('_action', 'save');
        $user = $request->user();
        $reason = Text::of($request->input('_reason'));

        $message = DB::transaction(function () use ($action, $request, $pv, $user, $reason) {
            $pv = PurchaseReceipt::query()->lockForUpdate()->findOrFail($pv->id);   // status dibaca ulang di dalam kunci
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($pv->order_id);
            $need = fn (array $from) => abort_unless(in_array($pv->status, $from, true), 422, 'Status PV sudah berubah. Muat ulang halaman.');

            switch ($action) {
                case 'save':
                case 'confirm':
                    $need(['draft']);
                    abort_unless($user->id === $pv->created_by || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang mengubah draf ini.');
                    $data = $this->validated($request);
                    abort_unless(in_array($order->status, ['approved', 'partial'], true), 422, 'PO ini sudah tidak menerima barang.');
                    $pv->update(['doc_date' => $data['doc_date'], 'ref_no' => Text::of($data['ref_no'] ?? '') ?: null, 'receiver' => Text::of($data['receiver'] ?? '') ?: null, 'note' => Text::of($data['note'] ?? '') ?: null]);
                    $rows = $this->lineRows($request, $order, $pv, $action === 'confirm');
                    $this->saveLines($pv, $order, $rows);
                    if ($action === 'save') {
                        return 'Draf disimpan.';
                    }

                    return $this->confirm($pv, $order, $rows, $user);
                case 'cancel':
                    $need(['confirmed']);
                    abort_unless($user->isSupervisor(), 403, 'Hanya Supervisor yang membatalkan PV.');
                    abort_if($pv->statusNow() === 'cancelled', 422, 'RCV PV ini sudah dibatalkan di Stok.');
                    abort_if(mb_strlen($reason) < 3, 422, 'Isi alasan pembatalan (minimal 3 huruf).');
                    $this->documents->cancel($pv->stockDocument, $user, $reason);
                    $pv->update(['status' => 'cancelled', 'reason' => $reason]);
                    $order->syncReceived();

                    return "{$pv->doc_no} dibatalkan; stok dikembalikan lewat pembatalan {$pv->stockDocument->doc_no}.";
                case 'delete':
                    $need(['draft']);
                    abort_unless($user->id === $pv->created_by || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang menghapus draf ini.');
                    $pv->delete();

                    return 'Draf PV dihapus.';
                default:
                    abort(422, 'Aksi tidak dikenal.');
            }
        });

        return $action === 'delete' ? redirect()->route('pv.index')->with('status', $message) : redirect()->route('pv.show', $pv)->with('status', $message);
    }

    // Konfirmasi: RCV dibuat dan dikonfirmasi dalam transaksi yang sama; kegagalan stok (mis. faktor satuan belum ada) membatalkan seluruh Konfirmasi.
    private function confirm(PurchaseReceipt $pv, PurchaseOrder $order, array $rows, $user): string
    {
        $lines = array_map(fn ($r) => ['item_id' => $r['line']->item_id, 'qty' => $r['qty'], 'unit' => $r['line']->unit, 'unit_cost' => $r['line']->price, 'expiry_date' => $r['expiry'], 'note' => null], $rows);
        $rcv = $this->documents->create(StockDocumentType::Receiving, ['supplier_id' => $pv->supplier_id, 'ref_no' => $pv->ref_no, 'recipient' => $pv->receiver, 'note' => trim("{$pv->doc_no} dari {$order->doc_no}. ".$pv->note)], $lines, $user);
        $this->documents->confirm($rcv, $user);
        $pv->update(['status' => 'confirmed', 'stock_document_id' => $rcv->id, 'confirmed_by' => $user->id, 'confirmed_at' => now()]);
        $order->syncReceived();

        return "{$pv->doc_no} dikonfirmasi: stok bertambah lewat {$rcv->doc_no}.".($order->fresh()->status === 'done' ? " {$order->doc_no} selesai diterima." : '');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'doc_date' => ['required', 'date'],
            'ref_no' => ['nullable', 'string', 'max:60'],
            'receiver' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array'],
            'lines.*.qty' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lines.*.expiry_date' => ['nullable', 'date'],
        ], ['lines.required' => 'Isi jumlah yang diterima.', 'doc_date.required' => 'Isi tanggal terima.']);
    }

    // Baris yang diisi (qty > 0), dicek terhadap sisa PO. Konfirmasi juga mewajibkan barang master dan kedaluwarsa yang sah.
    private function lineRows(Request $request, PurchaseOrder $order, ?PurchaseReceipt $pv, bool $confirm = false): array
    {
        $orderLines = $order->lines()->with('item')->get()->keyBy('id');
        $rows = [];
        foreach ((array) $request->input('lines', []) as $id => $in) {
            $line = $orderLines[(int) $id] ?? null;
            $qty = (float) ($in['qty'] ?? 0);
            if (! $line || $qty <= 0) {
                continue;
            }
            $left = (float) $line->qty - $this->received($line->id, $pv?->id);
            if ($qty > $left + 0.0005) {
                throw ValidationException::withMessages(['lines' => "{$line->label()}: jumlah diterima {$qty} melebihi sisa PO {$left}."]);
            }
            $expiry = Text::of($in['expiry_date'] ?? '') ?: null;
            if ($confirm) {
                if (! $line->item_id) {
                    throw ValidationException::withMessages(['lines' => "{$line->label()} belum ada di master Barang: tambahkan dulu di Data Master, lalu pilih barangnya di PO."]);
                }
                if ($line->item->tracks_expiry && ! $expiry) {
                    throw ValidationException::withMessages(['lines' => "Isi tanggal kedaluwarsa untuk {$line->label()}."]);
                }
                if ($expiry && $expiry < today()->toDateString()) {
                    throw ValidationException::withMessages(['lines' => "Tanggal kedaluwarsa {$line->label()} sudah lewat."]);
                }
            }
            $rows[] = ['line' => $line, 'qty' => $qty, 'expiry' => $expiry];
        }
        if (! $rows) {
            throw ValidationException::withMessages(['lines' => 'Isi jumlah diterima pada minimal satu barang.']);
        }

        return $rows;
    }

    private function saveLines(PurchaseReceipt $pv, PurchaseOrder $order, array $rows): void
    {
        $pv->lines()->delete();
        foreach ($rows as $r) {
            $pv->lines()->create(['order_line_id' => $r['line']->id, 'item_id' => $r['line']->item_id, 'qty' => $r['qty'], 'unit' => $r['line']->unit, 'price' => $r['line']->price, 'expiry_date' => $r['expiry']]);
        }
    }

    // Jumlah baris PO yang sudah diterima lewat PV terkonfirmasi (RCV masih berlaku), di luar PV yang sedang dibuka.
    private function received(int $orderLineId, ?int $exceptReceipt): float
    {
        return (float) DB::table('p_receipt_line as rl')->join('p_receipt as r', 'r.id', '=', 'rl.receipt_id')->join('rm_stock as d', 'd.id', '=', 'r.stock_document_id')
            ->where('rl.order_line_id', $orderLineId)->where('r.status', 'confirmed')->whereIn('d.status', ['confirmed', 'cancel_pending'])
            ->when($exceptReceipt, fn ($q) => $q->where('r.id', '!=', $exceptReceipt))->sum('rl.qty');
    }

    private function formData(PurchaseReceipt $pv, ?PurchaseOrder $order): array
    {
        $mine = $pv->exists ? $pv->lines->keyBy('order_line_id') : collect();
        $rows = $order ? $order->lines()->with('item')->get()->map(function ($l) use ($pv, $mine) {
            $got = $this->received($l->id, $pv->status === 'draft' ? $pv->id : null);   // draf: di luar PV ini; terkonfirmasi: termasuk PV ini
            $left = max(0.0, (float) $l->qty - $got);
            $line = $mine[$l->id] ?? null;

            return [
                'id' => $l->id, 'label' => $l->label(), 'ordered' => (float) $l->qty, 'got' => $got, 'left' => $left, 'unit' => $l->unit, 'price' => (float) $l->price,
                'tracks' => (bool) $l->item?->tracks_expiry, 'master' => (bool) $l->item_id,
                'qty' => old("lines.{$l->id}.qty", $line ? (float) $line->qty : ($pv->exists ? 0 : $left)), 'expiry' => old("lines.{$l->id}.expiry_date", $line?->expiry_date?->format('Y-m-d')),
            ];
        })->all() : [];
        $log = [];
        if ($pv->exists) {
            $log[] = ['at' => $pv->created_at->format('Y-m-d H:i:s'), 'by' => $pv->creator->name, 'action' => 'Dibuat', 'note' => null];
            if ($pv->confirmed_at) {
                $log[] = ['at' => $pv->confirmed_at->format('Y-m-d H:i:s'), 'by' => \App\Models\User::find($pv->confirmed_by)?->name ?? '-', 'action' => 'Dikonfirmasi', 'note' => $pv->stockDocument?->doc_no];
            }
            if ($pv->status === 'cancelled') {
                $log[] = ['at' => $pv->updated_at->format('Y-m-d H:i:s'), 'by' => '-', 'action' => 'Dibatalkan', 'note' => $pv->reason];
            }
        }

        return [
            'pv' => $pv, 'order' => $order, 'rows' => $rows, 'log' => $log,
            'orders' => PurchaseOrder::query()->with('supplier')->where(fn ($q) => $q->whereIn('status', ['approved', 'partial'])->orWhere('id', $pv->order_id))->orderBy('doc_no')->get()->mapWithKeys(fn ($o) => [$o->id => $o->doc_no.' · '.$o->supplier->name])->all(),
        ];
    }
}
