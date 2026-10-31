<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Tabel sistem diberi awalan sys_ (nama tunggal, gaya tmmockup): user_groups -> sys_user_group, user_group_modules -> sys_user_group_module, constants -> sys_constant.
 *                Hanya nama tabel; kolom (group_id, ...), kunci asing (users.group_id dan sys_user_group_module.group_id ikut menunjuk nama baru), data, dan nama index/constraint lama tidak berubah. Tabel users, geo_*, dan tabel kerangka Laravel tidak diubah. Migrasi lama tetap memakai nama lama karena berjalan sebelum rename ini.
 *                MySQL: satu RENAME TABLE atomik. Tabel yang sudah bernama baru dilewati (aman dijalankan ulang). Berhenti bila ada VIEW, karena RENAME TABLE tidak memperbarui isi VIEW.
 * Output       : tabel sys_user_group, sys_user_group_module, sys_constant.
 * Dipakai oleh : App\Models\UserGroup, Constant (protected $table), DB::table('sys_user_group_module') di UserGroup, ModuleController, GroupController (unique), UserRequest (exists), Constants (hasTable).
 * Status       : Aktif.
 */
return new class extends Migration
{
    private const MAP = [
        'user_groups' => 'sys_user_group', 'user_group_modules' => 'sys_user_group_module', 'constants' => 'sys_constant',
    ];

    public function up(): void
    {
        $this->rename(self::MAP);
    }

    public function down(): void
    {
        $this->rename(array_flip(self::MAP));
    }

    private function rename(array $map): void
    {
        $map = array_filter($map, fn ($to, $from) => Schema::hasTable($from) && ! Schema::hasTable($to), ARRAY_FILTER_USE_BOTH);
        if ($map === []) {
            return;
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $views = DB::table('information_schema.VIEWS')->where('TABLE_SCHEMA', DB::raw('database()'))->pluck('TABLE_NAME')->all();
            if ($views !== []) {
                throw new RuntimeException('Rename tabel dibatalkan: database memiliki VIEW ('.implode(', ', $views).') yang tidak ikut diperbarui. Perbarui VIEW lebih dulu.');
            }
            DB::statement('RENAME TABLE '.implode(', ', array_map(fn ($from, $to) => "`$from` TO `$to`", array_keys($map), $map)));

            return;
        }

        foreach ($map as $from => $to) {
            Schema::rename($from, $to);
        }
    }
};
