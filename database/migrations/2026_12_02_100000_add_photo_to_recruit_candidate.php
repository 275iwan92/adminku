<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Foto profil kandidat rekrutmen (berkas privat di disk 'ga', folder recruit; tampil di kartu kandidat dan sebagai avatar di kartu papan). Foto KTP sudah ada (ktp_path).
 * Output       : h_recruit_candidate.photo_path, photo_name.
 * Dipakai oleh : Candidate, CandidateController, RecruitmentSeeder.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('h_recruit_candidate', function (Blueprint $table) {
            $table->string('photo_path', 255)->nullable()->after('cv_name');
            $table->string('photo_name', 150)->nullable()->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('h_recruit_candidate', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'photo_name']);
        });
    }
};
