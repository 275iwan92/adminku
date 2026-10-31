<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

// Grup pengguna: modul (kode menu, lihat App\Support\Modules) yang boleh dibuka anggotanya, tiap modul rw (baca + tulis) atau ro (hanya baca).
#[Fillable(['code', 'name', 'is_active'])]
class UserGroup extends Model
{
    protected $table = 'sys_user_group';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'group_id');
    }

    /** @return array<string, string> kode modul => 'rw'|'ro' */
    public function moduleAccess(): array
    {
        return DB::table('sys_user_group_module')->where('group_id', $this->id)->pluck('access', 'module')->all();
    }

    /** Hak semua grup dengan satu kueri (untuk daftar), agar tidak satu kueri per grup. @return array<int, array<string, string>> id grup => (kode modul => 'rw'|'ro') */
    public static function accessByGroup(): array
    {
        $map = [];
        foreach (DB::table('sys_user_group_module')->get(['group_id', 'module', 'access']) as $r) {
            $map[$r->group_id][$r->module] = $r->access;
        }

        return $map;
    }

    /** @param  array<string, string>  $access  kode modul => 'rw'|'ro' (modul yang tidak ada = tanpa akses) */
    public function syncAccess(array $access): void
    {
        DB::table('sys_user_group_module')->where('group_id', $this->id)->delete();
        DB::table('sys_user_group_module')->insert(array_map(fn ($m, $a) => ['group_id' => $this->id, 'module' => $m, 'access' => $a], array_keys($access), $access));
    }
}
