<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Istilah master barang: kolom product_id (yang menunjuk master m_item) diganti item_id pada i_batch, m_item_unit, i_reorder_order. Kolom item_id lama (penunjuk batch) sudah menjadi batch_id (migrasi 2026_11_17_140000), jadi tidak ada bentrok.
 *                Hanya nama kolom; data, kunci asing (MySQL dan SQLite ikut menunjuk kolom baru), dan tipe tidak berubah. Nama index/constraint lama (mis. items_product_id_expiry_date_unique, product_units_product_id_unit_unique) sengaja dibiarkan dan masih memuat istilah lama.
 *                Aman dijalankan ulang: kolom yang sudah bernama baru dilewati. Berhenti bila ada VIEW di database, karena rename kolom tidak memperbarui isi VIEW.
 * Output       : kolom item_id pada i_batch, m_item_unit, i_reorder_order.
 * Dipakai oleh : App\Models\Batch, ItemUnit, ReorderOrder, Item, StockDocuments, ReorderOrders, LeadTimes, ItemUnits, UnitConverter, StockDocumentRequest, ReportController, StockDocumentController, ReorderOrderController, form dokumen stok dan pemesanan ulang.
 * Status       : Aktif.
 */
return new class extends Migration
{
    private const TABLES = ['i_batch', 'm_item_unit', 'i_reorder_order'];

    public function up(): void
    {
        $this->rename('product_id', 'item_id');
    }

    public function down(): void
    {
        $this->rename('item_id', 'product_id');
    }

    private function rename(string $from, string $to): void
    {
        $tables = array_filter(self::TABLES, fn ($t) => Schema::hasColumn($t, $from) && ! Schema::hasColumn($t, $to));
        if ($tables === []) {
            return;
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $views = DB::table('information_schema.VIEWS')->where('TABLE_SCHEMA', DB::raw('database()'))->pluck('TABLE_NAME')->all();
            if ($views !== []) {
                throw new RuntimeException('Rename kolom dibatalkan: database memiliki VIEW ('.implode(', ', $views).') yang tidak ikut diperbarui. Perbarui VIEW lebih dulu.');
            }
        }

        foreach ($tables as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->renameColumn($from, $to));
        }
    }
};
