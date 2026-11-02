<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Warna bilah atas kolom papan Rekrutmen (tone DevLib: neutral, accent, violet, warn, success, danger) per tahap; kosong = bergilir otomatis menurut urutan. Tahap bawaan diberi warna seperti desain kanban DevLib.
 * Output       : m_recruit_step.tone.
 * Dipakai oleh : RecruitStep, CandidateController@index, Setup Rekrutmen.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_recruit_step', function (Blueprint $table) {
            $table->string('tone', 10)->nullable()->after('needs_schedule');
        });
        foreach (['Daftar' => 'neutral', 'Interview 1' => 'accent', 'Tes' => 'violet', 'Interview 2' => 'warn', 'TTD penawaran' => 'accent', 'Diterima' => 'success'] as $name => $tone) {
            DB::table('m_recruit_step')->where('name', $name)->update(['tone' => $tone]);
        }
    }

    public function down(): void
    {
        Schema::table('m_recruit_step', function (Blueprint $table) {
            $table->dropColumn('tone');
        });
    }
};
