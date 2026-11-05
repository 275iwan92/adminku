<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Penerima barang boleh dihubungkan ke Karyawan (tombol Jadikan karyawan di form Penerima); kolom kosong = penerima bukan karyawan / belum dihubungkan.
 * Output       : m_recipient.employee_id (hapus karyawan -> tautan dikosongkan).
 * Dipakai oleh : RecipientController, EmployeeController@store.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_recipient', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('department_id')->constrained('m_employee')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('m_recipient', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
        });
    }
};
