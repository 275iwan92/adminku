<?php

namespace App\Http\Controllers;

use App\Models\UserGroup;
use App\Support\Modules;

/* REMARK
 * Tujuan       : Menu Keamanan > Daftar Modul: semua modul (layar menu) aplikasi, peran minimalnya, dan grup mana yang boleh membukanya. Hanya baca; pengaturannya ada di menu Grup.
 * Sumber hulu  : Modules::all, sys_user_group_module.
 * Output       : grid Daftar Modul.
 * Dipakai oleh : routes/web.php (modules.index, middleware administrator).
 * Status       : Aktif.
 */
class ModuleController extends Controller
{
    public const LEVEL_LABEL = ['entry' => 'Data Entry', 'supervisor' => 'Supervisor', 'administrator' => 'Administrator'];

    public function index()
    {
        $access = UserGroup::accessByGroup();
        $groups = UserGroup::query()->orderBy('name')->get()->mapWithKeys(fn (UserGroup $g) => [$g->id => [$g->name, $access[$g->id] ?? []]]);

        $rows = collect(Modules::all())->map(fn ($m, $code) => [
            'id' => $code, 'code' => $code, 'name' => $m[1], 'parent' => Modules::PARENTS[$m[0]][0], 'level' => self::LEVEL_LABEL[$m[5]],
            'groups' => $groups->filter(fn ($g) => isset($g[1][$code]))->map(fn ($g) => $g[0].' ('.strtoupper($g[1][$code]).')')->implode(', '), 'active' => true,
        ])->values()->all();

        return view('modules.index', ['rows' => $rows]);
    }
}
