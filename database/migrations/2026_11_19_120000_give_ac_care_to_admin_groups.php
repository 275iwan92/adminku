<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Modul GA Perawatan AC (GAS Penjadwalan, GAV Kunjungan, GAD Denah, GAK Master Unit AC, GAA Area dan Gedung, GAR Ruang, GAE Vendor) diberikan RW ke Grup bawaan Administrator (ADMIN).
 *                Grup lain tidak diberi apa pun: modul GA tidak masuk bawaan peran, Administrator menunjuk petugas admin GA (penjadwalan dan master) dan pengguna yang hanya melihat (PIC, dll.) lewat layar Grup.
 * Output       : sys_user_group_module (baris modul AC untuk grup ADMIN). Tidak mengubah data lain.
 * Dipakai oleh : Modules, GroupController.
 * Status       : Aktif.
 */
return new class extends Migration
{
    private const MODULES = ['GAS', 'GAV', 'GAD', 'GAK', 'GAA', 'GAR', 'GAE'];

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
