<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Jays akan berisi beberapa modul dalam satu database, jadi sembilan tabel master diberi awalan m_ (nama tunggal): products -> m_item, product_units -> m_item_unit, categories -> m_category,
 *                units -> m_unit, unit_conversions -> m_unit_conversion, locations -> m_location, recipients -> m_recipient, suppliers -> m_supplier, departments -> m_department.
 *                Hanya nama tabel; kolom, kunci asing (ikut menunjuk nama baru), data, dan nama index/constraint lama tidak berubah. Migrasi lama tetap memakai nama lama karena berjalan sebelum rename ini.
 *                MySQL: satu RENAME TABLE atomik. Tabel yang sudah bernama baru dilewati (aman dijalankan ulang). Berhenti bila ada VIEW, karena RENAME TABLE tidak memperbarui isi VIEW.
 * Output       : tabel m_item, m_item_unit, m_category, m_unit, m_unit_conversion, m_location, m_recipient, m_supplier, m_department.
 * Dipakai oleh : App\Models\* (protected $table), aturan exists:/unique, SQL mentah di ProductController dan Product.
 * Status       : Aktif.
 */
return new class extends Migration
{
    private const MAP = [
        'products' => 'm_item', 'product_units' => 'm_item_unit', 'categories' => 'm_category', 'units' => 'm_unit', 'unit_conversions' => 'm_unit_conversion',
        'locations' => 'm_location', 'recipients' => 'm_recipient', 'suppliers' => 'm_supplier', 'departments' => 'm_department',
    ];

    public function up(): void
    {
        $this->rename(self::MAP);
    }

    public function down(): void
    {
        $this->rename(array_flip(self::MAP));
    }

    private function rename(array $map): void
    {
        $map = array_filter($map, fn ($to, $from) => Schema::hasTable($from) && ! Schema::hasTable($to), ARRAY_FILTER_USE_BOTH);
        if ($map === []) {
            return;
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $views = DB::table('information_schema.VIEWS')->where('TABLE_SCHEMA', DB::raw('database()'))->pluck('TABLE_NAME')->all();
            if ($views !== []) {
                throw new RuntimeException('Rename tabel dibatalkan: database memiliki VIEW ('.implode(', ', $views).') yang tidak ikut diperbarui. Perbarui VIEW lebih dulu.');
            }
            DB::statement('RENAME TABLE '.implode(', ', array_map(fn ($from, $to) => "`$from` TO `$to`", array_keys($map), $map)));

            return;
        }

        foreach ($map as $from => $to) {
            Schema::rename($from, $to);
        }
    }
};
