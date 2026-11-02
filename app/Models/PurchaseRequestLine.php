<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/* REMARK
 * Tujuan       : Baris PP: barang master (item_id) atau teks bebas (item_name) untuk barang baru, qty, satuan, estimasi harga satuan, dan jumlah yang sudah dipesan (diisi PO nanti).
 * Dipakai oleh : PurchaseRequest, PurchaseRequestController.
 * Status       : Aktif.
 */
#[Fillable(['request_id', 'item_id', 'item_name', 'qty', 'unit', 'est_price', 'note', 'ordered_qty'])]
class PurchaseRequestLine extends Model
{
    protected $table = 'p_request_line';

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'request_id');
    }

    public function label(): string
    {
        return $this->item ? $this->item->code.' · '.$this->item->name : (string) $this->item_name;
    }
}
