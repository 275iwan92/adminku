<?php

namespace App\Rules;

use App\Support\Decimal;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/* REMARK
 * Tujuan       : Aturan presisi qty yang satu untuk semua isian jumlah: paling banyak Decimal::QTY_SCALE (3) desimal. Lebih dari itu ditolak (tidak dibulatkan diam-diam),
 *                sehingga 0,0004 tidak berubah menjadi 0 dan 1,2345 tidak menjadi 1,235. Dipasang sesudah 'numeric' (+ 'bail'); nilai bukan angka dilewati.
 * Dipakai oleh : StockDocumentRequest, ReorderOrderController; StockCounts::qty memakai Decimal::decimals yang sama.
 * Status       : Aktif.
 */
final class Quantity implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_numeric($value) && Decimal::decimals($value) > Decimal::QTY_SCALE) {
            $fail('validation.qty_scale')->translate();
        }
    }
}
