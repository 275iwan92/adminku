<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/* REMARK
 * Tujuan       : Kebutuhan (m_cash_purpose): daftar nama untuk dropdown Uang Keluar, ditambah / dihapus pengguna. Nama disalin ke k_cash_out.purpose, jadi menghapus tidak mengubah dokumen lama.
 * Dipakai oleh : CashPurposeController, CashOutController (dropdown), QuickAdd (cash_purpose).
 * Status       : Aktif.
 */
#[Fillable(['name', 'is_active'])]
class CashPurpose extends Model
{
    protected $table = 'm_cash_purpose';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function cashOuts(): HasMany
    {
        return $this->hasMany(CashOut::class, 'purpose_id');
    }
}
