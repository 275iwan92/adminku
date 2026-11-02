<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Modul baru Beranda Stok (STB; isi Beranda lama dipindah ke butir pertama menu Stok) diberikan ke Grup yang sudah memegang Barang (ITM), dengan akses yang sama, supaya petugas stok tidak kehilangan layarnya.
 * Output       : sys_user_group_module (baris STB). Tidak mengubah data lain; Grup tanpa ITM (mis. petugas GA saja) tidak diberi.
 * Dipakai oleh : Modules, GroupController.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('sys_user_group_module')->where('module', 'ITM')->get() as $g) {
            DB::table('sys_user_group_module')->insertOrIgnore(['group_id' => $g->group_id, 'module' => 'STB', 'access' => $g->access]);
        }
    }

    public function down(): void
    {
        DB::table('sys_user_group_module')->where('module', 'STB')->delete();
    }
};
