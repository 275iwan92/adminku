<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Kedisiplinan (HR): penerbitan SP 1, SP 2, SP 3, dan PHK kepada karyawan, masing-masing dengan nomor surat otomatis (urut per tahun dan jenis) untuk dicetak. Surat terbit tidak diubah; salah = Cabut dengan alasan.
 *                Status Aktif / Kedaluwarsa / Diganti dihitung dari tanggal dan SP yang lebih tinggi; hanya Dicabut yang disimpan. until_on = berlaku s/d (SP) atau tanggal efektif (PHK).
 * Output       : h_discipline, hak modul HRD untuk Grup ADMIN.
 * Dipakai oleh : Discipline, DisciplineController.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('h_discipline', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 40)->unique();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('seq');
            $table->string('type', 4);   // sp1 | sp2 | sp3 | phk
            $table->foreignId('employee_id')->constrained('m_employee')->restrictOnDelete();
            $table->date('issued_on');
            $table->date('until_on')->nullable();
            $table->string('reason', 500);
            $table->string('rule_ref', 255)->nullable();
            $table->string('signer_name', 100);
            $table->string('signer_title', 100);
            $table->string('status', 8)->default('issued');   // issued | revoked
            $table->string('revoke_reason', 255)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['year', 'type', 'seq']);
            $table->index(['employee_id', 'issued_on']);
        });

        $admin = DB::table('sys_user_group')->where('code', 'ADMIN')->value('id');
        if ($admin) {
            DB::table('sys_user_group_module')->insertOrIgnore(['group_id' => $admin, 'module' => 'HRD', 'access' => 'rw']);
        }
    }

    public function down(): void
    {
        DB::table('sys_user_group_module')->where('module', 'HRD')->delete();
        Schema::dropIfExists('h_discipline');
    }
};
