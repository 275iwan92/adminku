<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Layar Denah AC (modul GAD) dihapus; hak modul GAD di Grup dibersihkan supaya tidak menggantung. Gambar denah per area dan posisi titik AC tidak disentuh.
 * Output       : sys_user_group_module (baris GAD dihapus). Aman dijalankan ulang.
 * Dipakai oleh : Modules, GroupController.
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('sys_user_group_module')->where('module', 'GAD')->delete();
    }

    public function down(): void
    {
        // hak lama tidak dipulihkan: modulnya sudah tidak ada
    }
};
