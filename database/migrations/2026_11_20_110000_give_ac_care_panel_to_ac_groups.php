<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Modul GAM (Perawatan AC: Monitoring + Setup) diberikan ke Grup yang sudah punya modul AC lain: RW bila punya RW di salah satunya, selain itu R. Dengan GAM menu samping GA menampilkan satu butir "Perawatan AC".
 * Output       : sys_user_group_module (baris GAM). Tidak mengubah data lain.
 * Dipakai oleh : Modules, GroupController.
 * Status       : Aktif.
 */
return new class extends Migration
{
    private const AC = ['GAS', 'GAV', 'GAD', 'GAK', 'GAA', 'GAR', 'GAE'];

    public function up(): void
    {
        $rows = DB::table('sys_user_group_module')->whereIn('module', self::AC)->get(['group_id', 'access'])->groupBy('group_id');
        foreach ($rows as $groupId => $list) {
            DB::table('sys_user_group_module')->insertOrIgnore(['group_id' => $groupId, 'module' => 'GAM', 'access' => $list->contains('access', 'rw') ? 'rw' : 'r']);
        }
    }

    public function down(): void
    {
        DB::table('sys_user_group_module')->where('module', 'GAM')->delete();
    }
};
