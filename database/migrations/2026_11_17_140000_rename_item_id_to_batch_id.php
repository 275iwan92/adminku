<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Istilah batch: kolom item_id (yang menunjuk satu batch di i_batch) diganti batch_id pada rm_stock_line, rm_stockcard, i_count_line. product_id (menunjuk master m_item) tidak berubah.
 *                Hanya nama kolom; data, kunci asing (MySQL dan SQLite ikut menunjuk kolom baru), dan tipe tidak berubah. Nama index/constraint lama (mis. rm_stockcard_item_id_index) sengaja dibiarkan dan masih memuat istilah lama.
 *                Aman dijalankan ulang: kolom yang sudah bernama baru dilewati. Berhenti bila ada VIEW di database, karena rename kolom tidak memperbarui isi VIEW.
 * Output       : kolom batch_id pada rm_stock_line, rm_stockcard, i_count_line.
 * Dipakai oleh : App\Models\StockDocumentLine, StockMovement, StockCountLine, StockLedger, StockDocuments, StockCounts, StockValuation, ReorderOrders, LeadTimes, ProductUnits, ReorderOrder, StockDocumentRequest, ReportController, StockCountController, StockDocumentController, form dokumen stok.
 * Status       : Aktif.
 */
return new class extends Migration
{
    private const TABLES = ['rm_stock_line', 'rm_stockcard', 'i_count_line'];

    public function up(): void
    {
        $this->rename('item_id', 'batch_id');
    }

    public function down(): void
    {
        $this->rename('batch_id', 'item_id');
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
