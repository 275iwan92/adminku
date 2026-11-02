<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Modul GA Perawatan Aset Kendaraan (GVM Monitoring, GVD Perpanjang Dokumen, GVS Catat Servis, GVO Catat Odometer, GVK Master Kendaraan, GVT Jenis Kendaraan) diberikan RW ke Grup bawaan Administrator (ADMIN).
 *                Grup lain tidak diberi apa pun: modul GA tidak masuk bawaan peran, Administrator menunjuk petugas admin GA dan sopir lewat layar Grup (GVD hanya untuk petugas admin GA, sopir hanya GVO).
 * Output       : sys_user_group_module (baris modul GA untuk grup ADMIN). Tidak mengubah data lain.
 * Dipakai oleh : Modules, GroupController.
 * Status       : Aktif.
 */
return new class extends Migration
{
    private const MODULES = ['GVM', 'GVD', 'GVS', 'GVO', 'GVK', 'GVT'];

    public function up(): void
    {
        $admin = DB::table('sys_user_group')->where('code', 'ADMIN')->value('id');
        foreach ($admin ? self::MODULES : [] as $module) {
            DB::table('sys_user_group_module')->insertOrIgnore(['group_id' => $admin, 'module' => $module, 'access' => 'rw']);
        }
    }

    public function down(): void
    {
        DB::table('sys_user_group_module')->whereIn('module', self::MODULES)->delete();
    }
};
