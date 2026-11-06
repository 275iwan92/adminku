<?php

namespace App\Http\Controllers;

use App\Models\FacAsset;
use App\Models\FacCategory;
use App\Models\FacOrder;
use App\Models\FacPlace;
use App\Services\FacCare;
use App\Support\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/* REMARK
 * Tujuan       : Master Aset (GA Gedung dan Aset, modul FAA): kode otomatis AST-0001 (permanen), nama, kategori, gedung / ruang, tanggal beli, nilai, garansi s.d., kondisi (Baik / Perlu perawatan / Rusak / Dihapus),
 *                penanggung jawab, foto opsional (disk privat "ga"). Nonaktif, bukan hapus; aset dengan tiket terbuka tidak bisa dinonaktifkan. Daftar berbentuk kartu per kategori.
 * Sumber hulu  : m_fac_asset, m_fac_category, m_fac_place.
 * Output       : kartu Aset (index), form Aset, foto.
 * Dipakai oleh : routes/web.php (fac-assets.*).
 * Status       : Aktif.
 */
class FacAssetController extends Controller
{
    public function index()
    {
        $assets = FacAsset::query()->with('place.parent')->orderBy('asset_code')->get()->groupBy('category_id');
        $groups = FacCategory::query()->orderBy('name')->get()->map(fn (FacCategory $c) => [
            'label' => $c->name, 'icon' => $c->icon, 'unit' => 'aset', 'empty' => 'Belum ada aset pada kategori ini.',
            'items' => $assets->get($c->id, collect())->map(fn (FacAsset $a) => [
                'url' => route('fac-assets.show', $a), 'title' => $a->name, 'code' => $a->asset_code, 'sub' => $a->place?->path(), 'off' => ! $a->is_active,
                'pills' => [[FacCare::CONDITIONS[$a->cond], ['baik' => 'ok', 'perlu' => 'warn', 'rusak' => 'bad', 'dihapus' => 'plain'][$a->cond]]],
            ])->values()->all(),
        ])->all();

        return view('fac-assets.index', ['groups' => $groups]);
    }

    public function create(Request $request)
    {
        return view('fac-assets.form', $this->formData(new FacAsset(['asset_code' => FacAsset::nextCode(), 'cond' => 'baik', 'place_id' => $request->integer('lokasi') ?: null])));
    }

    public function show(FacAsset $asset)
    {
        return view('fac-assets.form', $this->formData($asset));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $asset = FacAsset::create(['asset_code' => FacAsset::nextCode()] + $data);
        $this->savePhoto($asset, $request->file('photo'), false);

        return redirect()->route('fac-assets.show', $asset)->with('status', 'Aset disimpan.');
    }

    public function update(Request $request, FacAsset $asset): RedirectResponse
    {
        $action = $request->input('_action');
        if (in_array($action, ['activate', 'deactivate'], true)) {
            if ($action === 'deactivate' && $asset->orders()->whereIn('status', FacOrder::OPEN)->exists()) {
                return redirect()->route('fac-assets.show', $asset)->withErrors(['name' => 'Aset masih punya tiket perawatan terbuka. Selesaikan atau batalkan dulu.']);
            }
            $asset->update(['is_active' => $action === 'activate']);

            return redirect()->route('fac-assets.show', $asset)->with('status', $action === 'activate' ? 'Aset diaktifkan.' : 'Aset dinonaktifkan.');
        }
        $asset->update($this->validated($request, $asset));
        $this->savePhoto($asset, $request->file('photo'), $request->boolean('remove_photo'));

        return redirect()->route('fac-assets.show', $asset)->with('status', 'Aset disimpan.');
    }

    public function toggle(FacAsset $asset): JsonResponse
    {
        if ($asset->is_active && $asset->orders()->whereIn('status', FacOrder::OPEN)->exists()) {
            return response()->json(['message' => 'Aset masih punya tiket perawatan terbuka.'], 422);
        }
        $asset->update(['is_active' => ! $asset->is_active]);

        return response()->json(['active' => $asset->is_active]);
    }

    // Foto aset (privat di disk "ga"): hanya lewat controller, dengan hak modul FAA atau panel FAM.
    public function photo(FacAsset $asset): StreamedResponse
    {
        abort_unless($asset->photo_path && Storage::disk('ga')->exists($asset->photo_path), 404);

        return Storage::disk('ga')->response($asset->photo_path, Str::slug($asset->asset_code).'.'.pathinfo($asset->photo_path, PATHINFO_EXTENSION), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=0']);
    }

    private function validated(Request $request, ?FacAsset $asset = null): array
    {
        $request->merge(['name' => Text::of($request->input('name')), 'place_id' => $request->input('place_id') ?: null]);
        $d = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'category_id' => ['required', 'integer', 'exists:m_fac_category,id'], 'place_id' => ['nullable', 'integer', 'exists:m_fac_place,id'],
            'bought_at' => ['nullable', 'date'], 'price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'], 'warranty_until' => ['nullable', 'date', 'after_or_equal:bought_at'],
            'cond' => ['required', Rule::in(array_keys(FacCare::CONDITIONS))], 'pic_name' => ['nullable', 'string', 'max:100'], 'note' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], [], ['name' => 'Nama', 'category_id' => 'Kategori', 'cond' => 'Kondisi', 'warranty_until' => 'Garansi s.d.']);
        unset($d['photo']);
        $d['price'] = $d['price'] ?? 0;
        foreach (['pic_name', 'note'] as $k) {
            $d[$k] = Text::of($d[$k] ?? '') ?: null;
        }

        return $d;
    }

    private function savePhoto(FacAsset $asset, ?UploadedFile $file, bool $remove): void
    {
        if ($file) {
            $old = $asset->photo_path;
            $asset->update(['photo_path' => $file->store("fac/asset/{$asset->id}", 'ga'), 'photo_name' => mb_substr($file->getClientOriginalName(), 0, 150)]);
        } elseif ($remove && $asset->photo_path) {
            $old = $asset->photo_path;
            $asset->update(['photo_path' => null, 'photo_name' => null]);
        }
        isset($old) && $old && Storage::disk('ga')->delete($old);
    }

    private function formData(FacAsset $asset): array
    {
        return [
            'asset' => $asset,
            'categories' => FacCategory::query()->where('is_active', true)->when($asset->category_id, fn ($q) => $q->orWhere('id', $asset->category_id))->orderBy('name')->pluck('name', 'id')->all(),
            'places' => FacPlace::query()->with('parent')->where('is_active', true)->when($asset->place_id, fn ($q) => $q->orWhere('id', $asset->place_id))->get()->sortBy(fn ($p) => $p->path())->mapWithKeys(fn ($p) => [$p->id => $p->path()])->all(),
        ];
    }
}
