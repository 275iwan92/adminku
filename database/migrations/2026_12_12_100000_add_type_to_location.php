<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Jenis lokasi penyimpanan (lemari | gudang_tertutup | gudang_terbuka | area_luas); kosong = belum ditentukan.
 * Output       : kolom m_location.type (nullable).
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_location', fn (Blueprint $t) => $t->string('type', 20)->nullable()->after('name'));
    }

    public function down(): void
    {
        Schema::table('m_location', fn (Blueprint $t) => $t->dropColumn('type'));
    }
};
