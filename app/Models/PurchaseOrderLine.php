<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/* REMARK
 * Tujuan       : Baris PO: barang master (item_id) atau teks bebas (item_name), qty, satuan, harga satuan, jumlah diterima (diisi PV nanti), dan baris PP asalnya (request_line_id, boleh kosong).
 * Dipakai oleh : PurchaseOrder, PurchaseOrderController, PurchaseDecisionController, PurchaseRequest::syncOrdered.
 * Status       : Aktif.
 */
#[Fillable(['order_id', 'request_line_id', 'item_id', 'item_name', 'qty', 'unit', 'price', 'received_qty', 'note'])]
class PurchaseOrderLine extends Model
{
    protected $table = 'p_order_line';

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function requestLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestLine::class, 'request_line_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'order_id');
    }

    public function label(): string
    {
        return $this->item ? $this->item->code.' · '.$this->item->name : (string) $this->item_name;
    }
}
