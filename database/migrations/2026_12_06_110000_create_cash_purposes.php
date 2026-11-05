<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Master Kebutuhan (m_cash_purpose) untuk dropdown "Digunakan Untuk Kebutuhan" di Uang Keluar: nama unik, bisa ditambah / dihapus pengguna. k_cash_out.purpose tetap teks (nama disalin),
 *                jadi hapus master tidak merusak dokumen lama. Berisi 8 contoh awal.
 * Output       : m_cash_purpose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_cash_purpose', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->timestamps();
        });

        $now = now();
        DB::table('m_cash_purpose')->insert(array_map(fn ($n) => ['name' => $n, 'created_at' => $now, 'updated_at' => $now], [
            'Operasional kantor', 'Pembelian ATK', 'Konsumsi acara', 'Transportasi dan perjalanan dinas', 'Sewa tempat kegiatan', 'Perawatan dan perbaikan', 'Honor dan upah harian', 'Bantuan sosial',
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('m_cash_purpose');
    }
};
