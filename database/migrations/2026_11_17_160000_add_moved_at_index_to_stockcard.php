<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Indeks rm_stockcard(moved_at) untuk laporan per periode (Ringkasan Stok: jumlah masuk/keluar per periode, nilai stok Beranda, min(moved_at)). Tanpa indeks ini EXPLAIN pada 80 ribu pergerakan
 *                memindai seluruh tabel (type=ALL) untuk jumlah per periode; indeks lama (batch_id, moved_at, id) hanya membantu pencarian per batch. Hanya indeks; tidak mengubah data.
 * Output       : rm_stockcard_moved_at_index.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rm_stockcard', function (Blueprint $table) {
            $table->index('moved_at', 'rm_stockcard_moved_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('rm_stockcard', function (Blueprint $table) {
            $table->dropIndex('rm_stockcard_moved_at_index');
        });
    }
};
