<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/* REMARK
 * Tujuan       : Baris PV: satu baris PO yang diterima (qty, satuan, harga dari PO, kedaluwarsa bila barangnya melacak kedaluwarsa).
 * Dipakai oleh : PurchaseReceipt, PurchaseReceiptController, PurchaseOrder::syncReceived.
 * Status       : Aktif.
 */
#[Fillable(['receipt_id', 'order_line_id', 'item_id', 'qty', 'unit', 'price', 'expiry_date'])]
class PurchaseReceiptLine extends Model
{
    protected $table = 'p_receipt_line';

    protected function casts(): array
    {
        return ['expiry_date' => 'date'];
    }
}
