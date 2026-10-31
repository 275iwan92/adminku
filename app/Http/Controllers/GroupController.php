<?php

namespace App\Http\Controllers;

use App\Models\UserGroup;
use App\Support\Modules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Menu Keamanan > Grup: daftar grup pengguna dan pengaturan modul apa saja yang boleh dibuka tiap grup (tabel hak akses per modul: Tidak ada / RO / RW). Hanya Administrator.
 *                Akses nyata = modul dicentang di grup DAN peran pengguna cukup (lihat Modules / User::canAccess); Administrator selalu penuh.
 * Sumber hulu  : sys_user_group, sys_user_group_module, Modules::all.
 * Output       : grid Grup, form Grup dengan centang modul per induk menu.
 * Dipakai oleh : routes/web.php (groups.*, middleware administrator).
 * Status       : Aktif.
 */
class GroupController extends Controller
{
    public function index()
    {
        $access = UserGroup::accessByGroup();
        $rows = UserGroup::query()->withCount(['users' => fn ($q) => $q->visible()])->orderBy('name')->get()->map(fn (UserGroup $g) => [
            'id' => $g->id, 'code' => $g->code, 'name' => $g->name, 'users' => $g->users_count, 'modules' => count($access[$g->id] ?? []), 'active' => $g->is_active,
        ])->all();

        return view('groups.index', ['rows' => $rows]);
    }

    public function create()
    {
        return view('groups.form', ['group' => new UserGroup, 'rows' => $this->rows([])]);
    }

    public function show(UserGroup $group)
    {
        return view('groups.form', ['group' => $group->loadCount(['users' => fn ($q) => $q->visible()]), 'rows' => $this->rows($group->moduleAccess())]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $group = UserGroup::create(['code' => $data['code'], 'name' => $data['name']]);
        $group->syncAccess($this->granted($data['modules'] ?? []));

        return redirect()->route('groups.show', $group)->with('status', 'Grup disimpan.');
    }

    public function update(Request $request, UserGroup $group): RedirectResponse
    {
        $action = $request->input('_action');
        if (in_array($action, ['activate', 'deactivate'], true)) {
            $group->update(['is_active' => $action === 'activate']);

            return redirect()->route('groups.show', $group)->with('status', $action === 'activate' ? 'Grup diaktifkan.' : 'Grup dinonaktifkan.');
        }

        $data = $this->validated($request, $group);
        $group->update(['name' => $data['name']]);
        $group->syncAccess($this->granted($data['modules'] ?? []));

        return redirect()->route('groups.show', $group)->with('status', 'Grup disimpan.');
    }

    public function toggle(UserGroup $group): JsonResponse
    {
        $group->update(['is_active' => ! $group->is_active]);

        return response()->json(['active' => $group->is_active]);
    }

    private function validated(Request $request, ?UserGroup $group = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code', $group?->code)))]);

        return $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('sys_user_group', 'code')->ignore($group)],
            'name' => ['required', 'string', 'max:100'],
            'modules' => ['nullable', 'array'],
            'modules.*.access' => ['nullable', Rule::in(['none', 'ro', 'rw'])],
        ]);
    }

    // Baris tabel hak akses: satu per modul, dikelompokkan menurut induk menu. access = none | ro | rw.
    private function rows(array $current): array
    {
        $rows = [];
        foreach (Modules::PARENTS as $parent => [$parentLabel]) {
            foreach (Modules::all() as $code => $m) {
                if ($m[0] === $parent) {
                    $rows[] = ['code' => $code, 'label' => $m[1], 'menu' => $parentLabel, 'level' => ModuleController::LEVEL_LABEL[$m[5]], 'access' => $current[$code] ?? 'none'];
                }
            }
        }

        return $rows;
    }

    // Isian tabel -> kode modul => rw|ro; kode yang tidak dikenal diabaikan, none = tanpa akses.
    private function granted(array $modules): array
    {
        return collect($modules)->filter(fn ($row, $code) => isset(Modules::all()[$code]) && in_array($row['access'] ?? 'none', ['ro', 'rw'], true))->map(fn ($row) => $row['access'])->all();
    }
}
