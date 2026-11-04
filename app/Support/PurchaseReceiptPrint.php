<?php

namespace App\Support;

use App\Models\Item;
use App\Models\PurchaseReceipt;
use Devlib\PrintDoc\Document;

/* REMARK
 * Tujuan       : Cetakan Penerimaan Pembelian (PV) / bukti terima barang: pemasok, PO asal, no. surat jalan, penerima, baris barang + kedaluwarsa. Tanpa harga; PO di kepala, PP per baris.
 * Sumber hulu  : p_receipt + p_receipt_line (+ m_item, p_order, m_supplier).
 * Dipakai oleh : PurchaseReceiptController@print (rute pv.print), tombol Print di purchase-receipts/form.blade.php.
 * Status       : Aktif.
 */
final class PurchaseReceiptPrint extends Document
{
    private const LABELS = ['Goods Receipt' => 'Penerimaan Barang', 'Supplier' => 'Pemasok', 'PO No.' => 'No. PO', 'Delivery Note' => 'No. Surat Jalan', 'Received By' => 'Diterima Oleh', 'Expiry' => 'Kedaluwarsa', 'PR No.' => 'No. PP', 'Notes' => 'Catatan'];

    public string $type = 'purchase-receipt';

    public string $title = 'Goods Receipt';

    public string $partyLabel = 'Supplier';

    public array $refs = [['key' => 'po', 'label' => 'PO No.'], ['key' => 'ref', 'label' => 'Delivery Note'], ['key' => 'receiver', 'label' => 'Received By']];

    public array $extra = [['key' => 'pr', 'label' => 'PR No.', 'width' => 44], ['key' => 'exp', 'label' => 'Expiry', 'width' => 40]];

    public static function load(PurchaseReceipt $pv, ?string $printedBy): self
    {
        $pv->loadMissing(['lines', 'order', 'supplier']);
        $prs = \App\Models\PurchaseOrderLine::query()->with('requestLine.request')->whereIn('id', $pv->lines->pluck('order_line_id'))->get()->keyBy('id');
        Document::labels(self::LABELS);
        $items = Item::query()->whereIn('id', $pv->lines->pluck('item_id')->filter())->get()->keyBy('id');
        $s = $pv->supplier;

        return new self([
            'kind' => 'PV', 'number' => $pv->doc_no, 'date' => $pv->doc_date->toDateString(), 'status' => 'confirmed',
            'party' => ['name' => $s->name, 'code' => $s->code, 'lines' => array_values(array_filter([$s->address, $s->phone, $s->email]))],
            'po' => $pv->order->doc_no, 'ref' => $pv->ref_no, 'receiver' => $pv->receiver,
            'rows' => $pv->lines->map(fn ($l) => [
                'name' => $items[$l->item_id]->name ?? '-', 'sub' => $items[$l->item_id]->code ?? null, 'qty' => $l->qty, 'unit' => $l->unit, 'pr' => $prs[$l->order_line_id]->requestLine?->request?->doc_no, 'exp' => $l->expiry_date?->format('d/m/Y'),
            ])->all(),
            'notes' => array_values(array_filter([filled($pv->note) ? ['Notes', $pv->note] : null])), 'printedBy' => $printedBy,
        ]);
    }
}
