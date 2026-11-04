<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/* REMARK
 * Tujuan       : Uang Masuk (KSM): dana diterima ke satu Kas/Rekening dari satu sumber dana (teks bebas, mis. institusi, donatur, pendapatan operasional). Nomor UM-YYMM-0001 (urut per bulan). Status draft -> approval -> approved | rejected; approved boleh cancelled.
 *                Uang Keluar yang ditaut ke sini (cashOuts) dilacak sampai habis: sisa saldo = amount - total Uang Keluar disetujui.
 * Sumber hulu  : k_cash_in, m_cash_account, users.
 * Dipakai oleh : CashInController, CashOutController (dropdown sumber dana).
 * Status       : Aktif.
 */
#[Fillable(['doc_no', 'doc_date', 'cash_account_id', 'source_name', 'amount', 'description', 'attachment', 'status', 'created_by', 'submitted_at', 'approved_by', 'approved_at', 'reason'])]
class CashIn extends Model
{
    protected $table = 'k_cash_in';

    public const STATUS = ['draft' => 'Draf', 'approval' => 'Diajukan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'];

    protected function casts(): array
    {
        return ['doc_date' => 'date', 'amount' => 'decimal:2', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function cashOuts(): HasMany
    {
        return $this->hasMany(CashOut::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function number(): string
    {
        return $this->doc_no ?? 'Draf #'.$this->id;
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function usedAmount(): float
    {
        return (float) $this->cashOuts()->where('status', 'approved')->sum('amount');
    }

    public function remainingAmount(): float
    {
        return (float) $this->amount - $this->usedAmount();
    }

    // UM-YYMM-0001, urut per bulan tanggal Uang Masuk. Dipanggil di dalam transaksi simpan.
    public static function nextNo(\DateTimeInterface $date): string
    {
        $prefix = 'UM-'.$date->format('ym').'-';
        $max = static::query()->where('doc_no', 'like', $prefix.'%')->lockForUpdate()->pluck('doc_no')->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;

        return $prefix.sprintf('%04d', $max + 1);
    }
}
