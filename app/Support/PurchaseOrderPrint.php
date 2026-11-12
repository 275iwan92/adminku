<?php

namespace App\Support;

use App\Models\PurchaseOrder;
use Devlib2\PrintDoc\Document;

/* REMARK
 * Tujuan       : Cetakan Pesanan Pembelian (PO) memakai tata letak DevLib x-dl2::print-doc: pemasok di kanan, tanggal kirim, syarat bayar dan kirim ke di kiri, baris barang berharga, subtotal, PPN (hanya bila diisi) dan total.
 * Sumber hulu  : p_order + p_order_line (+ m_item, m_supplier, pembuat / penyetuju).
 * Input        : PurchaseOrder (load()).
 * Output       : objek Document (A5 landscape sampai 5 baris, A4 portrait di atasnya).
 * Dipakai oleh : PurchaseOrderController@print (rute po.print), tombol Print di purchase-orders/form.blade.php.
 * Status       : Aktif.
 */
final class PurchaseOrderPrint extends Document
{
    private const LABELS = [
        'Purchase Order' => 'Pesanan Pembelian', 'Supplier' => 'Pemasok', 'Delivery Date' => 'Tanggal Kirim', 'Payment Terms' => 'Syarat Bayar', 'Ship To' => 'Kirim Ke',
        'Prepared By' => 'Dibuat Oleh', 'From PR' => 'Dari PP', 'Notes' => 'Catatan', 'VAT' => 'PPN', 'Subtotal' => 'Subtotal',
    ];

    public string $type = 'purchase-order';

    public string $title = 'Purchase Order';

    public bool $priced = true;

    public string $partyLabel = 'Supplier';

    public array $refs = [['key' => 'need_date', 'label' => 'Delivery Date', 'type' => 'date'], ['key' => 'terms', 'label' => 'Payment Terms'], ['key' => 'ship_to', 'label' => 'Ship To']];

    public array $extra = [['key' => 'req', 'label' => 'From PR', 'width' => 38]];

    public static function load(PurchaseOrder $po, ?string $printedBy): self
    {
        $po->loadMissing(['lines.item', 'lines.requestLine.request', 'supplier', 'creator', 'approver']);
        Document::labels(self::LABELS);

        $rows = $po->lines->map(fn ($l) => [
            'name' => $l->item?->name ?? $l->item_name, 'sub' => $l->item?->code, 'sub2' => $l->note, 'qty' => $l->qty, 'unit' => $l->unit,
            'req' => $l->requestLine?->request?->doc_no, 'price' => $l->price, 'subtotal' => Decimal::amount($l->qty, $l->price),
        ])->all();
        $sub = Decimal::total(array_map(fn ($r) => [1, $r['subtotal']], $rows));
        $vat = $po->vat();
        $lines = array_values(array_filter([['Subtotal', $sub], $vat > 0 ? ['VAT', $vat] : null]));
        $s = $po->supplier;

        return new self([
            'kind' => 'PO', 'number' => $po->doc_no, 'date' => $po->doc_date->toDateString(), 'status' => in_array($po->status, ['approved', 'partial', 'done'], true) ? 'confirmed' : $po->status,
            'party' => ['name' => $s->name, 'code' => $s->code, 'lines' => array_values(array_filter([$s->address, $s->phone, $s->email]))],
            'need_date' => $po->need_date?->toDateString(), 'terms' => $po->payment_term_days === null ? null : ($po->payment_term_days === 0 ? 'Tunai' : $po->payment_term_days.' hari'), 'ship_to' => $po->ship_to,
            'rows' => $rows, 'notes' => array_values(array_filter([filled($po->note) ? ['Notes', $po->note] : null])), 'printedBy' => $printedBy,
            'approvedBy' => $po->approver?->name,
            'sums' => ['lines' => $lines, 'grand' => $po->total()],
        ]);
    }
}
