<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/* REMARK
 * Tujuan       : Sumber Dana (m_cash_source): daftar nama untuk dropdown Uang Masuk, ditambah / dihapus pengguna. Nama disalin ke k_cash_in.source_name dan pilihan disimpan di source_id; sumber yang sudah dipakai dinonaktifkan, bukan dihapus.
 * Dipakai oleh : CashSourceController, CashInController (dropdown), QuickAdd (cash_source).
 * Status       : Aktif.
 */
#[Fillable(['name', 'is_active'])]
class CashSource extends Model
{
    protected $table = 'm_cash_source';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    // Uang Masuk yang memakai sumber ini (menurut source_id); daftar memakai withCount('cashIns') supaya satu query.
    public function cashIns(): HasMany
    {
        return $this->hasMany(CashIn::class, 'source_id');
    }
}
