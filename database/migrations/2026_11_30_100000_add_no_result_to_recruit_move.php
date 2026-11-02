<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Log perpindahan kartu Rekrutmen menandai perpindahan KELUAR dari tahap berjadwal (Interview / Tes) saat hasilnya belum diisi dan pemakai memilih "Tetap pindahkan" (dipindah tanpa hasil).
 * Output       : h_recruit_move.no_result.
 * Dipakai oleh : CandidateController@move, Riwayat Pindah di kartu kandidat.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('h_recruit_move', function (Blueprint $table) {
            $table->boolean('no_result')->default(false)->after('assessor');
        });
    }

    public function down(): void
    {
        Schema::table('h_recruit_move', function (Blueprint $table) {
            $table->dropColumn('no_result');
        });
    }
};
