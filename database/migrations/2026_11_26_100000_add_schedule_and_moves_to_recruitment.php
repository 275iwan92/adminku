<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Rekrutmen: tahap berjadwal (needs_schedule: pindah ke tahap ini memunculkan pop-up tanggal + jam wajib; bawaan Interview 1, Tes, Interview 2) dan log perpindahan kartu
 *                (h_recruit_move: waktu persis, siapa, dari tahap mana ke mana, jadwal yang diisi); satu baris tiap perpindahan dari papan (seret atau menu Pindah ke).
 * Output       : m_recruit_step.needs_schedule, h_recruit_move.
 * Dipakai oleh : RecruitStep, CandidateController@move, form Kandidat (Riwayat pindah).
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_recruit_step', function (Blueprint $table) {
            $table->boolean('needs_schedule')->default(false)->after('kind');
        });
        DB::table('m_recruit_step')->whereIn('name', ['Interview 1', 'Tes', 'Interview 2'])->update(['needs_schedule' => true]);

        Schema::create('h_recruit_move', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('h_recruit_candidate')->cascadeOnDelete();
            $table->foreignId('from_step_id')->nullable()->constrained('m_recruit_step')->restrictOnDelete();
            $table->foreignId('to_step_id')->constrained('m_recruit_step')->restrictOnDelete();
            $table->foreignId('moved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('scheduled_on')->nullable();
            $table->string('scheduled_time', 5)->nullable();
            $table->string('assessor', 100)->nullable();
            $table->timestamp('moved_at')->useCurrent();
            $table->index('candidate_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('h_recruit_move');
        Schema::table('m_recruit_step', function (Blueprint $table) {
            $table->dropColumn('needs_schedule');
        });
    }
};
