<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Master Karyawan (HR, kepegawaian saja, tanpa gaji): data pribadi, pekerjaan, akun Jays (opsional), keluar dengan alasan; daftar keluarga, dokumen (KTP, KK, NPWP, SIM, BPJS, dst. dengan masa berlaku) dan riwayat kontrak.
 *                Konsep dari tmerp (emp_employee, emp_family, emp_document, emp_contract); alamat KTP dan domisili memakai pola area_level + area_id dari geo_*.
 * Output       : m_employee, m_employee_family, m_employee_doc, m_employee_contract.
 * Dipakai oleh : Employee, EmployeeController.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_employee', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('nickname', 50)->nullable();
            $table->string('gender', 1);   // L | P
            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('religion', 20)->nullable();
            $table->string('marital', 15)->nullable();
            $table->string('nik_ktp', 20)->nullable();
            $table->string('npwp', 30)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('ktp_address', 255)->nullable();
            $table->string('ktp_area_level', 10)->nullable();
            $table->unsignedBigInteger('ktp_area_id')->nullable();
            $table->string('home_address', 255)->nullable();   // kosong = sama dengan KTP
            $table->string('home_area_level', 10)->nullable();
            $table->unsignedBigInteger('home_area_id')->nullable();
            $table->string('emergency_name', 150)->nullable();
            $table->string('emergency_relation', 50)->nullable();
            $table->string('emergency_phone', 30)->nullable();
            $table->foreignId('department_id')->constrained('m_department')->restrictOnDelete();
            $table->string('position', 100)->nullable();
            $table->string('employment', 10)->default('tetap');   // tetap | kontrak | harian | magang
            $table->date('join_date');
            $table->string('work_status', 12)->default('working');   // working | leave | furlough (aktif); karyawan keluar = is_active false
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('exit_reason', 20)->nullable();
            $table->date('exit_date')->nullable();
            $table->string('exit_note', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('m_employee_family', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('m_employee')->cascadeOnDelete();
            $table->string('relation', 15);   // suami | istri | anak | orang_tua
            $table->string('name', 150);
            $table->date('birth_date')->nullable();
            $table->string('nik', 20)->nullable();
            $table->boolean('is_deceased')->default(false);
            $table->timestamps();
        });

        Schema::create('m_employee_doc', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('m_employee')->cascadeOnDelete();
            $table->string('doc_type', 20);   // ktp | kk | npwp | sim | skck | paspor | ijazah | bpjs_kes | bpjs_tk | lainnya
            $table->string('doc_no', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('m_employee_contract', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('m_employee')->cascadeOnDelete();
            $table->string('contract_no', 50)->nullable();
            $table->string('employment', 10);   // tetap | kontrak | harian | magang
            $table->date('start_date');
            $table->date('end_date')->nullable();   // kosong hanya untuk tetap
            $table->date('probation_end')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_employee_contract');
        Schema::dropIfExists('m_employee_doc');
        Schema::dropIfExists('m_employee_family');
        Schema::dropIfExists('m_employee');
    }
};
