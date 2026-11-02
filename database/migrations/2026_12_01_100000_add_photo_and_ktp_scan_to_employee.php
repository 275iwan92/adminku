<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Foto profil dan pindaian KTP karyawan (berkas privat di disk 'ga', folder employee; dibuka lewat rute berhak HRE).
 * Output       : m_employee.photo_path, photo_name, ktp_path, ktp_name.
 * Dipakai oleh : Employee, EmployeeController (tab Profil), EmployeeSampleSeeder.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_employee', function (Blueprint $table) {
            $table->string('photo_path', 255)->nullable()->after('emergency_phone');
            $table->string('photo_name', 150)->nullable()->after('photo_path');
            $table->string('ktp_path', 255)->nullable()->after('photo_name');
            $table->string('ktp_name', 150)->nullable()->after('ktp_path');
        });
    }

    public function down(): void
    {
        Schema::table('m_employee', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'photo_name', 'ktp_path', 'ktp_name']);
        });
    }
};
