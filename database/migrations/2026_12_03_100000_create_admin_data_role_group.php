<?php

use App\Support\Modules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Peran Admin Data (membuka layar setingkat Supervisor, tidak menyetujui): Grup bawaan ADMINDATA dibuat; Grup SUPERVISOR dan ADMINDATA dilengkapi semua modul selain Keamanan (rw), yang belum ada saja.
 * Output       : sys_user_group (ADMINDATA), sys_user_group_module. Aman dijalankan ulang; kolom users.role berupa teks bebas, tanpa perubahan skema.
 * Dipakai oleh : User::ROLES, Modules.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        $data = DB::table('sys_user_group')->where('code', 'ADMINDATA')->value('id')
            ?? DB::table('sys_user_group')->insertGetId(['code' => 'ADMINDATA', 'name' => 'Admin Data', 'created_at' => now(), 'updated_at' => now()]);
        $open = array_keys(array_filter(Modules::all(), fn ($m) => $m[0] !== 'SEC'));
        foreach ([$data, DB::table('sys_user_group')->where('code', 'SUPERVISOR')->value('id')] as $group) {
            foreach ($group ? $open : [] as $module) {
                DB::table('sys_user_group_module')->insertOrIgnore(['group_id' => $group, 'module' => $module, 'access' => 'rw']);
            }
        }
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'admin_data')->update(['group_id' => null]);
        if ($id = DB::table('sys_user_group')->where('code', 'ADMINDATA')->value('id')) {
            DB::table('sys_user_group_module')->where('group_id', $id)->delete();
            DB::table('sys_user_group')->where('id', $id)->delete();
        }
    }
};
