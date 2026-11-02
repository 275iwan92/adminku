<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Permintaan Pembelian (PP): satu header, banyak baris barang. Nomor PP-YYMM-0001 (urut per bulan). Status draft -> approval (diajukan) -> approved | rejected; approved boleh cancelled. partial / done diisi PO nanti.
 * Sumber hulu  : p_request, p_request_line, m_department, users; harga beli terakhir dari Penerimaan Barang terkonfirmasi (rm_stockcard).
 * Dipakai oleh : PurchaseRequestController, ItemLookups (lookup item_pp).
 * Status       : Aktif.
 */
#[Fillable(['doc_no', 'doc_date', 'department_id', 'need_date', 'note', 'status', 'created_by', 'submitted_at', 'approved_by', 'approved_at', 'reason'])]
class PurchaseRequest extends Model
{
    protected $table = 'p_request';

    // Label status di grid / filter (form memakai istilah DevLib lewat Indonesian).
    public const STATUS = ['draft' => 'Draf', 'approval' => 'Diajukan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'partial' => 'Dipesan sebagian', 'done' => 'Selesai', 'cancelled' => 'Dibatalkan'];

    protected function casts(): array
    {
        return ['doc_date' => 'date', 'need_date' => 'date', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequestLine::class, 'request_id')->orderBy('id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function total(): float
    {
        return (float) $this->lines->sum(fn (PurchaseRequestLine $l) => (float) $l->qty * (float) $l->est_price);
    }

    // PP-YYMM-0001, urut per bulan tanggal PP. Dipanggil di dalam transaksi simpan.
    public static function nextNo(\DateTimeInterface $date): string
    {
        $prefix = 'PP-'.$date->format('ym').'-';
        $max = static::query()->where('doc_no', 'like', $prefix.'%')->lockForUpdate()->pluck('doc_no')->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;

        return $prefix.sprintf('%04d', $max + 1);
    }

    // Harga beli terakhir per satuan stok barang, dari Penerimaan terkonfirmasi yang tidak dibatalkan (null = belum pernah dibeli).
    public static function lastCost(int $itemId): ?string
    {
        $cost = DB::table('rm_stockcard as c')
            ->join('rm_stock as d', 'd.id', '=', 'c.stock_document_id')->join('i_batch as b', 'b.id', '=', 'c.batch_id')
            ->where('b.item_id', $itemId)->where('c.type', 'IN')->whereNull('c.reversal_of')->whereIn('d.status', ['confirmed', 'cancel_pending'])
            ->orderByDesc('c.moved_at')->orderByDesc('c.id')->value('c.unit_cost');

        return $cost === null ? null : (string) round((float) $cost, 2);
    }
}
