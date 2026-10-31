<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Dokumen stok material diberi awalan rm_ (nama tunggal, gaya tmmockup): stock_documents -> rm_stock, stock_document_lines -> rm_stock_line, stock_movements -> rm_stockcard.
 *                Hanya nama tabel; kolom (stock_document_id, item_id, ...), kunci asing (ikut menunjuk nama baru), data, dan nama index/constraint lama tidak berubah. Migrasi lama tetap memakai nama lama karena berjalan sebelum rename ini.
 *                MySQL: satu RENAME TABLE atomik. Tabel yang sudah bernama baru dilewati (aman dijalankan ulang). Berhenti bila ada VIEW, karena RENAME TABLE tidak memperbarui isi VIEW.
 * Output       : tabel rm_stock, rm_stock_line, rm_stockcard.
 * Dipakai oleh : App\Models\StockDocument, StockDocumentLine, StockMovement (protected $table), join/SQL mentah di ReorderOrder, LeadTimes, ProductUnits, ReorderOrders, StockLedger.
 * Status       : Aktif.
 */
return new class extends Migration
{
    private const MAP = [
        'stock_documents' => 'rm_stock', 'stock_document_lines' => 'rm_stock_line', 'stock_movements' => 'rm_stockcard',
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
