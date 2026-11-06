<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/* REMARK
 * Tujuan       : Penyesuaian Kas (KSA): koreksi saldo satu kas, tambah atau kurangi, dengan alasan wajib. Nomor PK-YYMM-0001. Status draft -> approval -> approved | rejected; approved boleh cancelled.
 *                Baris buku besar kas ditulis CashLedger saat Approve (Supervisor), bukan saat disimpan.
 * Sumber hulu  : k_cash_adjust, m_cash_account, users.
 * Dipakai oleh : CashAdjustmentController, CashLedger.
 * Status       : Aktif.
 */
#[Fillable(['doc_no', 'doc_date', 'cash_account_id', 'direction', 'amount', 'cause', 'status', 'created_by', 'submitted_at', 'approved_by', 'approved_at', 'reason'])]
class CashAdjustment extends Model
{
    protected $table = 'k_cash_adjust';

    public const STATUS = ['draft' => 'Draf', 'approval' => 'Diajukan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'];

    public const DIRECTIONS = ['add' => 'Tambah saldo', 'sub' => 'Kurangi saldo'];

    protected function casts(): array
    {
        return ['doc_date' => 'date', 'amount' => 'decimal:2', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function signedAmount(): float
    {
        return $this->direction === 'sub' ? -(float) $this->amount : (float) $this->amount;
    }

    public function number(): string
    {
        return $this->doc_no ?? 'Draf #'.$this->id;
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    // PK-YYMM-0001, urut per bulan tanggal Penyesuaian. Dipanggil di dalam transaksi simpan.
    public static function nextNo(\DateTimeInterface $date): string
    {
        $prefix = 'PK-'.$date->format('ym').'-';
        $max = static::query()->where('doc_no', 'like', $prefix.'%')->lockForUpdate()->pluck('doc_no')->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;

        return $prefix.sprintf('%04d', $max + 1);
    }
}
