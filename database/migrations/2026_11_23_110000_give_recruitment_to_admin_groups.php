<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Modul HR Rekrutmen (HRR) diberikan RW ke Grup bawaan Administrator (ADMIN) saja. Grup lain tidak diberi apa pun: Administrator menunjuk petugas HR lewat layar Grup.
 * Output       : sys_user_group_module (satu baris HRR untuk grup ADMIN). Aman dijalankan ulang.
 * Dipakai oleh : Modules, GroupController.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        $admin = DB::table('sys_user_group')->where('code', 'ADMIN')->value('id');
        if ($admin) {
            DB::table('sys_user_group_module')->insertOrIgnore(['group_id' => $admin, 'module' => 'HRR', 'access' => 'rw']);
        }
    }

    public function down(): void
    {
        DB::table('sys_user_group_module')->where('module', 'HRR')->delete();
    }
};
