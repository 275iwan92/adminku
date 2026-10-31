<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/* REMARK
 * Tujuan       : Kategori barang gudang: ATK, Sparepart Maintenance, Tools/Peralatan.
 * Output       : tabel m_category.
 * Dipakai oleh : DatabaseSeeder.
 * Status       : Aktif.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['ATK' => 'ATK (Alat Tulis Kantor)', 'SPR' => 'Sparepart Maintenance', 'TLS' => 'Tools / Peralatan'] as $code => $name) {
            Category::updateOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
