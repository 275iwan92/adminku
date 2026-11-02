<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/* REMARK
 * Tujuan       : Penerimaan Pembelian (PV): barang datang dari satu PO. Nomor PV-YYMM-0001 (urut per bulan). Status draft -> confirmed (Konfirmasi membuat Penerimaan Barang di Stok dan langsung mengonfirmasinya) -> cancelled.
 *                statusNow() ikut membatalkan tampilan bila RCV-nya dibatalkan langsung dari Stok.
 * Sumber hulu  : p_receipt, p_receipt_line, p_order, rm_stock (RCV), users.
 * Dipakai oleh : PurchaseReceiptController, PurchaseOrder::syncReceived.
 * Status       : Aktif.
 */
#[Fillable(['doc_no', 'doc_date', 'order_id', 'supplier_id', 'ref_no', 'receiver', 'note', 'status', 'stock_document_id', 'created_by', 'confirmed_by', 'confirmed_at', 'reason'])]
class PurchaseReceipt extends Model
{
    protected $table = 'p_receipt';

    public const STATUS = ['draft' => 'Draf', 'confirmed' => 'Terkonfirmasi', 'cancelled' => 'Dibatalkan'];

    protected function casts(): array
    {
        return ['doc_date' => 'date', 'confirmed_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseReceiptLine::class, 'receipt_id')->orderBy('id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'order_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stockDocument(): BelongsTo
    {
        return $this->belongsTo(StockDocument::class, 'stock_document_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusNow(): string
    {
        return $this->status === 'confirmed' && $this->stockDocument?->status === 'cancelled' ? 'cancelled' : $this->status;
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->statusNow()] ?? $this->status;
    }

    // PV-YYMM-0001, urut per bulan tanggal PV. Dipanggil di dalam transaksi simpan.
    public static function nextNo(\DateTimeInterface $date): string
    {
        $prefix = 'PV-'.$date->format('ym').'-';
        $max = static::query()->where('doc_no', 'like', $prefix.'%')->lockForUpdate()->pluck('doc_no')->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;

        return $prefix.sprintf('%04d', $max + 1);
    }
}
