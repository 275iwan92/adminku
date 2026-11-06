<?php

namespace App\Http\Controllers;

use App\Models\FacPlace;
use App\Services\AcCare;
use App\Support\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Master Gedung dan Ruang (GA Gedung dan Aset, modul FAG): satu tabel dua tingkat. Gedung (tanpa induk) berisi ruang / area. Kode kosong = otomatis dari nama. Aktif / Nonaktif saja:
 *                gedung yang masih punya ruang aktif, dan tempat yang masih punya aset aktif, tidak bisa dinonaktifkan.
 * Sumber hulu  : m_fac_place, m_fac_asset.
 * Output       : kartu gedung (index), form Gedung / Ruang.
 * Dipakai oleh : routes/web.php (fac-places.*).
 * Status       : Aktif.
 */
class FacPlaceController extends Controller
{
    public function index()
    {
        $places = FacPlace::query()->withCount(['assets' => fn ($q) => $q->where('is_active', true)])->orderBy('name')->get();
        $buildings = $places->whereNull('parent_id')->map(fn (FacPlace $b) => [
            'id' => $b->id, 'code' => $b->code, 'name' => $b->name, 'active' => $b->is_active, 'assets' => $b->assets_count + $places->where('parent_id', $b->id)->sum('assets_count'),
            'rooms' => $places->where('parent_id', $b->id)->map(fn ($r) => ['id' => $r->id, 'code' => $r->code, 'name' => $r->name, 'active' => $r->is_active, 'assets' => $r->assets_count])->values()->all(),
        ])->values()->all();

        return view('fac-places.index', ['buildings' => $buildings]);
    }

    public function create(Request $request)
    {
        return view('fac-places.form', $this->formData(new FacPlace(['parent_id' => $request->integer('gedung') ?: null])));
    }

    public function show(FacPlace $place)
    {
        return view('fac-places.form', $this->formData($place->load('children')));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $place = FacPlace::create(['code' => $data['code'] ?? AcCare::uniqueCode('m_fac_place', $data['name'])] + $data);

        return redirect()->route('fac-places.show', $place)->with('status', ($place->parent_id ? 'Ruang' : 'Gedung').' disimpan.');
    }

    public function update(Request $request, FacPlace $place): RedirectResponse
    {
        $action = $request->input('_action');
        if (in_array($action, ['activate', 'deactivate'], true)) {
            if ($action === 'deactivate' && ($error = $this->blocker($place))) {
                return redirect()->route('fac-places.show', $place)->withErrors(['name' => $error]);
            }
            $place->update(['is_active' => $action === 'activate']);

            return redirect()->route('fac-places.show', $place)->with('status', $action === 'activate' ? 'Diaktifkan.' : 'Dinonaktifkan.');
        }

        $data = $this->validated($request, $place);
        $place->update(array_filter($data, fn ($v, $k) => $k !== 'code' || $v !== null, ARRAY_FILTER_USE_BOTH));

        return redirect()->route('fac-places.show', $place)->with('status', 'Disimpan.');
    }

    public function toggle(FacPlace $place): JsonResponse
    {
        if ($place->is_active && ($error = $this->blocker($place))) {
            return response()->json(['message' => $error], 422);
        }
        $place->update(['is_active' => ! $place->is_active]);

        return response()->json(['active' => $place->is_active]);
    }

    private function blocker(FacPlace $p): ?string
    {
        if ($p->children()->where('is_active', true)->exists()) {
            return 'Gedung masih punya ruang aktif. Nonaktifkan ruangnya dulu.';
        }

        return $p->assets()->where('is_active', true)->exists() ? 'Masih ada aset aktif di sini. Pindahkan atau nonaktifkan asetnya dulu.' : null;
    }

    private function validated(Request $request, ?FacPlace $place = null): array
    {
        $request->merge(['code' => strtoupper(Text::of($request->input('code'))) ?: null, 'name' => Text::of($request->input('name')), 'parent_id' => $request->input('parent_id') ?: null]);
        $d = $request->validate([
            'code' => ['nullable', 'string', 'max:20', Rule::unique('m_fac_place', 'code')->ignore($place)],
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'integer', Rule::exists('m_fac_place', 'id')->whereNull('parent_id'), $place ? Rule::notIn([$place->id]) : 'nullable'],
            'pic_name' => ['nullable', 'string', 'max:100'], 'note' => ['nullable', 'string', 'max:255'],
        ], [], ['name' => 'Nama', 'parent_id' => 'Gedung induk']);
        if ($place && $place->children()->exists() && $d['parent_id']) {
            abort(422, 'Gedung yang punya ruang tidak bisa dijadikan ruang.');
        }
        foreach (['pic_name', 'note'] as $k) {
            $d[$k] = Text::of($d[$k] ?? '') ?: null;
        }

        return $d;
    }

    private function formData(FacPlace $place): array
    {
        return ['place' => $place, 'buildings' => FacPlace::query()->whereNull('parent_id')->where('is_active', true)->when($place->exists, fn ($q) => $q->whereKeyNot($place->id))->orderBy('name')->pluck('name', 'id')->all()];
    }
}
