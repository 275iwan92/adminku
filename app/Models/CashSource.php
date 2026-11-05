<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/* REMARK
 * Tujuan       : Sumber Dana (m_cash_source): daftar nama untuk dropdown Uang Masuk, ditambah / dihapus pengguna. Nama disalin ke k_cash_in.source_name, jadi menghapus tidak mengubah dokumen lama.
 * Dipakai oleh : CashSourceController, CashInController (dropdown), QuickAdd (cash_source).
 * Status       : Aktif.
 */
#[Fillable(['name'])]
class CashSource extends Model
{
    protected $table = 'm_cash_source';

    // Berapa Uang Masuk yang memakai nama ini (menurut teks).
    public function usedCount(): int
    {
        return CashIn::query()->where('source_name', $this->name)->count();
    }
}
