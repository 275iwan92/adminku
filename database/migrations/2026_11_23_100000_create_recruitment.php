<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Rekrutmen (HR) sederhana, kunci NIK KTP: master urutan tahap (Daftar dan Diterima tetap, tahap tengah bebas), kandidat (boleh melamar ke beberapa bagian), dan riwayat tahap
 *                (jadwal, penilai, hasil, catatan) yang bertambah tiap kandidat dipindah di papan kanban atau dicatat di kartu kandidat.
 * Output       : m_recruit_step (diisi tahap bawaan), h_recruit_candidate, h_recruit_candidate_dept, h_recruit_event.
 * Dipakai oleh : RecruitStep, Candidate, CandidateController, RecruitStepController.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_recruit_step', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->unsignedSmallInteger('sort');
            $table->string('kind', 5)->default('mid');   // start (Daftar) | mid | end (Diterima); start dan end tetap, hanya satu masing-masing
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        $now = now();
        foreach ([['Daftar', 'start'], ['Interview 1', 'mid'], ['Tes', 'mid'], ['Interview 2', 'mid'], ['TTD penawaran', 'mid'], ['Diterima', 'end']] as $i => [$name, $kind]) {
            DB::table('m_recruit_step')->insert(['name' => $name, 'sort' => ($i + 1) * 10, 'kind' => $kind, 'created_at' => $now, 'updated_at' => $now]);
        }

        Schema::create('h_recruit_candidate', function (Blueprint $table) {
            $table->id();
            $table->string('nik', 16);   // kunci pencarian; boleh kembar (melamar lagi = baris baru) tetapi diberi peringatan
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('education', 40)->nullable();
            $table->string('position', 100)->nullable();
            $table->string('source', 20)->nullable();
            $table->date('applied_at');
            $table->foreignId('step_id')->constrained('m_recruit_step')->restrictOnDelete();
            $table->string('status', 10)->default('active');   // active | rejected | withdrawn
            $table->string('ktp_path', 255)->nullable();
            $table->string('ktp_name', 150)->nullable();
            $table->string('cv_path', 255)->nullable();
            $table->string('cv_name', 150)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('nik');
        });

        Schema::create('h_recruit_candidate_dept', function (Blueprint $table) {
            $table->foreignId('candidate_id')->constrained('h_recruit_candidate')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('m_department')->restrictOnDelete();
            $table->primary(['candidate_id', 'department_id']);
        });

        Schema::create('h_recruit_event', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('h_recruit_candidate')->cascadeOnDelete();
            $table->foreignId('step_id')->constrained('m_recruit_step')->restrictOnDelete();
            $table->date('scheduled_on')->nullable();
            $table->string('scheduled_time', 5)->nullable();   // HH:MM
            $table->string('assessor', 100)->nullable();
            $table->string('result', 8)->default('waiting');   // waiting | pass | fail
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('h_recruit_event');
        Schema::dropIfExists('h_recruit_candidate_dept');
        Schema::dropIfExists('h_recruit_candidate');
        Schema::dropIfExists('m_recruit_step');
    }
};
