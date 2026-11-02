<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Batas WIP kolom papan Rekrutmen (jumlah kartu maksimal yang nyaman per tahap; penghitung tampil "2 / 5"; melebihi hanya memberi peringatan, tidak memblokir). Kosong = tanpa batas.
 * Output       : m_recruit_step.wip_limit.
 * Dipakai oleh : RecruitStep, CandidateController@index, Setup Rekrutmen.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_recruit_step', function (Blueprint $table) {
            $table->unsignedSmallInteger('wip_limit')->nullable()->after('tone');
        });
    }

    public function down(): void
    {
        Schema::table('m_recruit_step', function (Blueprint $table) {
            $table->dropColumn('wip_limit');
        });
    }
};
