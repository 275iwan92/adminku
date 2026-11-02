<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcAreaRequest;
use App\Models\AcArea;
use App\Services\AcCare;
use App\Support\Palette;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/* REMARK
 * Tujuan       : Master Area / Gedung AC (GA, modul GAA): nama, kode (kosong = otomatis dari nama), denah unggahan (jpg / png, maks 5 MB; kosong = denah skematis dari persegi ruang). Aktif / Nonaktif saja.
 *                Denah disimpan di disk privat "ga" dan disajikan lewat AcPlanController (hak modul Denah AC); ukuran gambar dibaca saat unggah supaya titik AC (persen) tepat.
 * Sumber hulu  : m_ac_area, m_ac_room.
 * Output       : grid Area, form Area.
 * Dipakai oleh : routes/web.php (ac-areas.*).
 * Status       : Aktif.
 */
class AcAreaController extends Controller
{
    public function index()
    {
        $rows = AcArea::query()->withCount('rooms')->orderBy('name')->get()->map(fn (AcArea $a) => [
            'id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'hue' => Palette::item('AC', $a->code), 'rooms' => $a->rooms_count,
            'plan' => $a->plan_path ? 'Gambar unggahan' : 'Denah skematis', 'active' => $a->is_active,
        ])->all();

        return view('ac-areas.index', ['rows' => $rows]);
    }

    public function create()
    {
        return view('ac-areas.form', ['area' => new AcArea]);
    }

    public function show(AcArea $area)
    {
        return view('ac-areas.form', ['area' => $area->load('rooms')]);
    }

    public function store(AcAreaRequest $request): RedirectResponse
    {
        $data = $request->safe()->only(['code', 'name']);
        $area = AcArea::create(['code' => $data['code'] ?? AcCare::uniqueCode('m_ac_area', $data['name'])] + $data);
        $this->savePlan($area, $request->file('plan'), false);

        return redirect()->route('ac-areas.show', $area)->with('status', 'Area disimpan.');
    }

    public function update(AcAreaRequest $request, AcArea $area): RedirectResponse
    {
        $action = $request->input('_action');
        if (in_array($action, ['activate', 'deactivate'], true)) {
            if ($action === 'deactivate' && $area->rooms()->where('is_active', true)->exists()) {
                return redirect()->route('ac-areas.show', $area)->withErrors(['name' => 'Area masih punya ruang aktif. Nonaktifkan ruangnya dulu.']);
            }
            $area->update(['is_active' => $action === 'activate']);

            return redirect()->route('ac-areas.show', $area)->with('status', $action === 'activate' ? 'Area diaktifkan.' : 'Area dinonaktifkan.');
        }

        $area->update($request->safe()->only('name') + ['code' => $request->validated('code') ?: $area->code]);
        $this->savePlan($area, $request->file('plan'), $request->boolean('remove_plan'));

        return redirect()->route('ac-areas.show', $area)->with('status', 'Area disimpan.');
    }

    public function toggle(AcArea $area): JsonResponse
    {
        if ($area->is_active && $area->rooms()->where('is_active', true)->exists()) {
            return response()->json(['message' => 'Area masih punya ruang aktif. Nonaktifkan ruangnya dulu.'], 422);
        }
        $area->update(['is_active' => ! $area->is_active]);

        return response()->json(['active' => $area->is_active]);
    }

    // Gambar denah (privat di disk "ga"): hanya lewat controller, dengan hak modul.
    public static function planResponse(AcArea $area)
    {
        abort_unless($area->plan_path && Storage::disk('ga')->exists($area->plan_path), 404);

        return Storage::disk('ga')->response($area->plan_path, Str::slug($area->code).'-denah.'.pathinfo($area->plan_path, PATHINFO_EXTENSION), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=0']);
    }

    public function plan(AcArea $area)
    {
        return self::planResponse($area);
    }

    // Unggah / ganti / hapus denah. Berkas lama dibuang setelah yang baru tersimpan.
    private function savePlan(AcArea $area, ?UploadedFile $file, bool $remove): void
    {
        if ($file) {
            [$w, $h] = @getimagesize($file->getRealPath()) ?: [100, 60];
            $old = $area->plan_path;
            $area->update(['plan_path' => $file->store("ac/plan/{$area->id}", 'ga'), 'plan_name' => mb_substr($file->getClientOriginalName(), 0, 150), 'plan_w' => max(1, min(65535, $w)), 'plan_h' => max(1, min(65535, $h))]);
        } elseif ($remove && $area->plan_path) {
            $old = $area->plan_path;
            $area->update(['plan_path' => null, 'plan_name' => null, 'plan_w' => 100, 'plan_h' => 60]);
        }
        isset($old) && $old && Storage::disk('ga')->delete($old);
    }
}
