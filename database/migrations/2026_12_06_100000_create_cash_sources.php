<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Master Sumber Dana (m_cash_source) untuk dropdown Uang Masuk: nama unik, bisa ditambah / dihapus pengguna. k_cash_in.source_name tetap teks (nama disalin) sehingga hapus master tidak merusak dokumen lama.
 *                Berisi 7 contoh awal.
 * Output       : m_cash_source.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_cash_source', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->timestamps();
        });

        $now = now();
        DB::table('m_cash_source')->insert(array_map(fn ($n) => ['name' => $n, 'created_at' => $now, 'updated_at' => $now], [
            'Pendapatan operasional', 'Penjualan barang bekas', 'Donatur Bapak Hadi', 'Donatur umum', 'Setoran modal pemilik', 'Pengembalian dana (refund)', 'Bunga bank',
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('m_cash_source');
    }
};
