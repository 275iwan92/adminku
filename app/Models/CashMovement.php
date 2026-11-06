<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/* REMARK
 * Tujuan       : Satu baris buku besar kas (k_cash_ledger): jumlah bertanda (+ masuk, - keluar) pada satu kas. Hanya App\Services\CashLedger yang membuat baris; tidak pernah diubah atau dihapus.
 * Dipakai oleh : CashLedger, CashCardController, CashReportController.
 * Status       : Aktif.
 */
class CashMovement extends Model
{
    protected $table = 'k_cash_ledger';

    public const UPDATED_AT = null;

    public const TYPES = ['opening' => 'Saldo awal', 'in' => 'Uang Masuk', 'out' => 'Uang Keluar', 'adjust' => 'Penyesuaian'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['moved_at' => 'date', 'amount' => 'decimal:2'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
