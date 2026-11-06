<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Penggunaan / penyerahan aset (laptop, kalkulator, ATK tidak habis pakai): pemegang saat ini (karyawan atau departemen), posisi (office / loan / home / repair / lost), tanggal wajib kembali, tanda "perlu dimonitor";
 *                dokumen Serah Terima Aset (pinjam, bawa pulang, pengembalian, laporan hilang; Draf > Confirm > Approve Supervisor) dan Opname Aset (cek fisik per orang atau ruang, selisih jadi temuan).
 *                Modul FAH (Serah Terima), FAO (Opname), FAP (Aset per Pemegang) diberi RW ke grup bawaan ADMIN.
 * Output       : kolom baru m_fac_asset, g_fac_handover, g_fac_check, g_fac_check_line, sys_user_group_module.
 * Dipakai oleh : FacAsset, FacHandover, FacCheck, App\Services\FacCustody.
 * Status       : Aktif.
 */
return new class extends Migration
{
    private const MODULES = ['FAH', 'FAO', 'FAP'];

    public function up(): void
    {
        Schema::table('m_fac_asset', function (Blueprint $table) {
            $table->foreignId('holder_employee_id')->nullable()->constrained('m_employee');
            $table->foreignId('holder_department_id')->nullable()->constrained('m_department');
            $table->string('position', 8)->default('office');   // office | loan | home | repair | lost
            $table->date('due_back')->nullable();   // wajib kembali (pinjam / bawa pulang yang disetujui)
            $table->boolean('monitored')->default(false);   // aset bernilai kecil yang tetap dipantau (kalkulator, stapler, ...)
        });

        Schema::create('g_fac_handover', function (Blueprint $table) {
            $table->id();
            $table->string('no', 20)->nullable()->unique();   // ST-YYMM-0001, lahir saat Confirm
            $table->string('type', 8);   // loan | home | return | lost
            $table->foreignId('asset_id')->constrained('m_fac_asset');
            $table->foreignId('employee_id')->nullable()->constrained('m_employee');
            $table->foreignId('department_id')->nullable()->constrained('m_department');
            $table->date('doc_date');
            $table->date('expected_return')->nullable();
            $table->string('purpose', 255)->nullable();
            $table->string('condition_out', 10)->nullable();   // kondisi saat keluar / kembali (kunci FacCare::CONDITIONS)
            $table->string('damage_note', 255)->nullable();
            $table->string('status', 10)->default('draft');
            $table->string('photo_path', 255)->nullable();
            $table->string('photo_name', 150)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestamps();
            $table->index(['asset_id', 'status']);
        });

        Schema::create('g_fac_check', function (Blueprint $table) {
            $table->id();
            $table->date('checked_at');
            $table->foreignId('employee_id')->nullable()->constrained('m_employee');
            $table->foreignId('place_id')->nullable()->constrained('m_fac_place');
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('g_fac_check_line', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_id')->constrained('g_fac_check')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('m_fac_asset');
            $table->boolean('found');
            $table->string('note', 255)->nullable();
        });

        $admin = DB::table('sys_user_group')->where('code', 'ADMIN')->value('id');
        foreach ($admin ? self::MODULES : [] as $module) {
            DB::table('sys_user_group_module')->insertOrIgnore(['group_id' => $admin, 'module' => $module, 'access' => 'rw']);
        }
    }

    public function down(): void
    {
        DB::table('sys_user_group_module')->whereIn('module', self::MODULES)->delete();
        Schema::dropIfExists('g_fac_check_line');
        Schema::dropIfExists('g_fac_check');
        Schema::dropIfExists('g_fac_handover');
        Schema::table('m_fac_asset', function (Blueprint $table) {
            $table->dropConstrainedForeignId('holder_employee_id');
            $table->dropConstrainedForeignId('holder_department_id');
            $table->dropColumn(['position', 'due_back', 'monitored']);
        });
    }
};
