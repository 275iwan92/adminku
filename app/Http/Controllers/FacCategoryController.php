<?php

namespace App\Http\Controllers;

use App\Models\FacCategory;
use App\Services\AcCare;
use App\Support\Palette;
use App\Support\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Master Kategori Aset (GA Gedung dan Aset, modul FAC; Supervisor): Bangunan, Listrik, Plumbing, Mebel, Elektronik, Kendaraan ringan, dan seterusnya. Ikon dipilih dari daftar tetap. Kategori yang masih punya aset aktif tidak bisa dinonaktifkan.
 * Sumber hulu  : m_fac_category.
 * Output       : grid Kategori, form Kategori.
 * Dipakai oleh : routes/web.php (fac-categories.*).
 * Status       : Aktif.
 */
class FacCategoryController extends Controller
{
    public const ICONS = [
        'fa-box' => 'Kotak (umum)', 'fa-building' => 'Bangunan', 'fa-bolt' => 'Listrik', 'fa-faucet-drip' => 'Plumbing', 'fa-couch' => 'Mebel', 'fa-computer' => 'Elektronik',
        'fa-car-side' => 'Kendaraan', 'fa-fire-extinguisher' => 'Keselamatan', 'fa-wifi' => 'Jaringan', 'fa-wrench' => 'Peralatan', 'fa-snowflake' => 'Pendingin', 'fa-lightbulb' => 'Penerangan',
    ];

    public function index()
    {
        $rows = FacCategory::query()->withCount('assets')->orderBy('name')->get()->map(fn (FacCategory $c) => [
            'id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'hue' => Palette::item('FAC', $c->code), 'assets' => $c->assets_count, 'active' => $c->is_active,
        ])->all();

        return view('fac-categories.index', ['rows' => $rows]);
    }

    public function create()
    {
        return view('fac-categories.form', ['category' => new FacCategory(['icon' => 'fa-box'])]);
    }

    public function show(FacCategory $category)
    {
        return view('fac-categories.form', ['category' => $category]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $category = FacCategory::create(['code' => $data['code'] ?? AcCare::uniqueCode('m_fac_category', $data['name'])] + $data);

        return redirect()->route('fac-categories.show', $category)->with('status', 'Kategori disimpan.');
    }

    public function update(Request $request, FacCategory $category): RedirectResponse
    {
        $action = $request->input('_action');
        if (in_array($action, ['activate', 'deactivate'], true)) {
            if ($action === 'deactivate' && $category->assets()->where('is_active', true)->exists()) {
                return redirect()->route('fac-categories.show', $category)->withErrors(['name' => 'Kategori masih punya aset aktif.']);
            }
            $category->update(['is_active' => $action === 'activate']);

            return redirect()->route('fac-categories.show', $category)->with('status', $action === 'activate' ? 'Kategori diaktifkan.' : 'Kategori dinonaktifkan.');
        }
        $category->update(array_filter($this->validated($request, $category), fn ($v, $k) => $k !== 'code' || $v !== null, ARRAY_FILTER_USE_BOTH));

        return redirect()->route('fac-categories.show', $category)->with('status', 'Kategori disimpan.');
    }

    public function toggle(FacCategory $category): JsonResponse
    {
        if ($category->is_active && $category->assets()->where('is_active', true)->exists()) {
            return response()->json(['message' => 'Kategori masih punya aset aktif.'], 422);
        }
        $category->update(['is_active' => ! $category->is_active]);

        return response()->json(['active' => $category->is_active]);
    }

    private function validated(Request $request, ?FacCategory $category = null): array
    {
        $request->merge(['code' => strtoupper(Text::of($request->input('code'))) ?: null, 'name' => Text::of($request->input('name'))]);

        return $request->validate([
            'code' => ['nullable', 'string', 'max:20', Rule::unique('m_fac_category', 'code')->ignore($category)],
            'name' => ['required', 'string', 'max:100'], 'icon' => ['required', Rule::in(array_keys(self::ICONS))],
        ], [], ['name' => 'Nama', 'icon' => 'Ikon']);
    }
}
