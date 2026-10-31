<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Tabel inventori bersama diberi awalan i_ (nama tunggal, gaya tmmockup): items (stok per batch) -> i_batch, stock_counts -> i_count, stock_count_lines -> i_count_line, reorder_orders -> i_reorder_order.
 *                Hanya nama tabel; kolom (item_id, product_id, stock_count_id, ...), kunci asing (ikut menunjuk nama baru), data, dan nama index/constraint lama tidak berubah. Migrasi lama tetap memakai nama lama karena berjalan sebelum rename ini.
 *                MySQL: satu RENAME TABLE atomik. Tabel yang sudah bernama baru dilewati (aman dijalankan ulang). Berhenti bila ada VIEW, karena RENAME TABLE tidak memperbarui isi VIEW.
 * Output       : tabel i_batch, i_count, i_count_line, i_reorder_order.
 * Dipakai oleh : App\Models\Item, StockCount, StockCountLine, ReorderOrder (protected $table), join/SQL mentah/aturan exists di ProductController, Product, ReorderOrder, LeadTimes, ProductUnits, ReorderOrders, StockCountController, StockDocumentRequest, CountSchedule, SampleData.
 * Status       : Aktif.
 */
return new class extends Migration
{
    private const MAP = [
        'items' => 'i_batch', 'stock_counts' => 'i_count', 'stock_count_lines' => 'i_count_line', 'reorder_orders' => 'i_reorder_order',
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
