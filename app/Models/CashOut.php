<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/* REMARK
 * Tujuan       : Uang Keluar (KSK): pengeluaran yang wajib ditaut ke satu Uang Masuk sebagai sumber dananya (pelacakan dana); kas asal ikut otomatis kas tujuan Uang Masuk itu. Nomor UK-YYMM-0001. Status sama pola Uang Masuk / Purchase.
 * Sumber hulu  : k_cash_out, k_cash_in, users.
 * Dipakai oleh : CashOutController.
 * Status       : Aktif.
 */
#[Fillable(['doc_no', 'doc_date', 'cash_in_id', 'cash_account_id', 'amount', 'purpose', 'purpose_id', 'description', 'attachment', 'status', 'created_by', 'submitted_at', 'approved_by', 'approved_at', 'reason'])]
class CashOut extends Model
{
    protected $table = 'k_cash_out';

    public const STATUS = ['draft' => 'Draf', 'approval' => 'Diajukan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'];

    protected function casts(): array
    {
        return ['doc_date' => 'date', 'amount' => 'decimal:2', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    // Kategori Kebutuhan (m_cash_purpose); kosong = Uang Keluar lama bernama bebas ("Belum dikategorikan").
    public function category(): BelongsTo
    {
        return $this->belongsTo(CashPurpose::class, 'purpose_id');
    }

    public function cashIn(): BelongsTo
    {
        return $this->belongsTo(CashIn::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Kas Asal sendiri (k_cash_out.cash_account_id); Uang Keluar lama diisi migrasi dari kas Uang Masuk yang ditaut.
    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    public function cashAccount(): ?CashAccount
    {
        return $this->account ?? $this->cashIn?->cashAccount;
    }

    public function number(): string
    {
        return $this->doc_no ?? 'Draf #'.$this->id;
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    // UK-YYMM-0001, urut per bulan tanggal Uang Keluar. Dipanggil di dalam transaksi simpan.
    public static function nextNo(\DateTimeInterface $date): string
    {
        $prefix = 'UK-'.$date->format('ym').'-';
        $max = static::query()->where('doc_no', 'like', $prefix.'%')->lockForUpdate()->pluck('doc_no')->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;

        return $prefix.sprintf('%04d', $max + 1);
    }
}
