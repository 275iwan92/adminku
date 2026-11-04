<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Master Kas / Rekening (KSR): kas tunai atau rekening bank, saldo awal sendiri. Saldo berjalan = saldo awal + Uang Masuk disetujui - Uang Keluar disetujui (dihitung, tidak disimpan).
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

    // Saldo awal + Uang Masuk disetujui - Uang Keluar disetujui (lewat Uang Masuk kas ini).
    public function currentBalance(): float
    {
        $in = (float) $this->cashIns()->where('status', 'approved')->sum('amount');
        $out = (float) DB::table('k_cash_out')->join('k_cash_in', 'k_cash_in.id', '=', 'k_cash_out.cash_in_id')
            ->where('k_cash_in.cash_account_id', $this->id)->where('k_cash_out.status', 'approved')->sum('k_cash_out.amount');

        return (float) $this->opening_balance + $in - $out;
    }
}
