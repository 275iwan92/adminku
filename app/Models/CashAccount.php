<?php

namespace App\Models;

use App\Services\CashLedger;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/* REMARK
 * Tujuan       : Master Kas / Rekening (KSR): kas tunai atau rekening bank, saldo awal sendiri. Saldo berjalan = jumlah baris buku besar kas (CashLedger), tidak disimpan di sini.
 * Dipakai oleh : CashAccountController, CashIn.
 * Status       : Aktif.
 */
#[Fillable(['name', 'type', 'bank_name', 'account_no', 'account_holder', 'opening_balance', 'is_active'])]
class CashAccount extends Model
{
    protected $table = 'm_cash_account';

    public const TYPES = ['cash' => 'Tunai', 'bank' => 'Bank'];

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function cashIns(): HasMany
    {
        return $this->hasMany(CashIn::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    // Saldo = jumlah baris buku besar kas (CashLedger); saldo awal masuk sebagai baris pertama.
    public function currentBalance(): float
    {
        return app(CashLedger::class)->balance($this->id);
    }

    protected static function booted(): void
    {
        // Saldo awal (baru atau diubah) ditulis ke buku besar; tidak mengubah baris lama.
        static::saved(fn (CashAccount $a) => app(CashLedger::class)->syncOpening($a));
    }
}
