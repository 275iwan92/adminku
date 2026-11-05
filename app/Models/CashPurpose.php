<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

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

    // Berapa Uang Keluar yang memakai nama ini (menurut kategori).
    public function usedCount(): int
    {
        return CashOut::query()->where('purpose_id', $this->id)->count();
    }
}
