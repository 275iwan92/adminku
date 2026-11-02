<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcRoomRequest;
use App\Models\AcArea;
use App\Models\AcRoom;
use App\Services\AcCare;
use App\Support\Palette;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/* REMARK
 * Tujuan       : Master Ruang AC (GA, modul GAR): kode ruang (kosong = otomatis dari nama, bisa diubah), nama, area, persegi di denah (persen) dan PIC (nama, nomor WhatsApp, e-mail) yang diberi tahu kapan vendor datang.
 *                Ruang baru mendapat persegi bawaan di sel kosong berikutnya pada denah areanya. Ruang tidak dihapus, hanya dinonaktifkan (setelah semua AC-nya dipindah atau dinonaktifkan).
 * Sumber hulu  : m_ac_room, m_ac_area, m_ac_unit.
 * Output       : grid Ruang, form Ruang.
 * Dipakai oleh : routes/web.php (ac-rooms.*).
 * Status       : Aktif.
 */
class AcRoomController extends Controller
{
    public function index()
    {
        $rows = AcRoom::query()->with('area:id,name')->withCount('units')->orderBy('code')->get()->map(fn (AcRoom $r) => [
            'id' => $r->id, 'code' => $r->code, 'name' => $r->name, 'hue' => Palette::item('AC', $r->area->name), 'area' => $r->area->name, 'units' => $r->units_count,
            'pic' => $r->pic_name, 'phone' => $r->pic_phone, 'email' => $r->pic_email, 'active' => $r->is_active,
        ])->all();

        return view('ac-rooms.index', ['rows' => $rows]);
    }

    public function create(Request $request)
    {
        $areas = $this->areas(null);
        $areaId = (int) $request->query('area') ?: array_key_first($areas);

        return view('ac-rooms.form', ['room' => new AcRoom(['area_id' => $areaId] + $this->nextCell($areaId)), 'areas' => $areas]);
    }

    public function show(AcRoom $room)
    {
        return view('ac-rooms.form', ['room' => $room->loadCount('units'), 'areas' => $this->areas($room)]);
    }

    public function store(AcRoomRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $room = AcRoom::create(['code' => $data['code'] ?? AcCare::uniqueCode('m_ac_room', $data['name'])] + $data);

        return redirect()->route('ac-rooms.show', $room)->with('status', 'Ruang disimpan.');
    }

    public function update(AcRoomRequest $request, AcRoom $room): RedirectResponse
    {
        $action = $request->input('_action');
        if (in_array($action, ['activate', 'deactivate'], true)) {
            if ($action === 'deactivate' && $room->units()->where('is_active', true)->exists()) {
                return redirect()->route('ac-rooms.show', $room)->withErrors(['name' => 'Ruang masih punya AC aktif. Pindahkan atau nonaktifkan AC-nya dulu.']);
            }
            $room->update(['is_active' => $action === 'activate']);

            return redirect()->route('ac-rooms.show', $room)->with('status', $action === 'activate' ? 'Ruang diaktifkan.' : 'Ruang dinonaktifkan.');
        }

        $data = $request->validated();
        $room->update(['code' => $data['code'] ?? $room->code] + $data);

        return redirect()->route('ac-rooms.show', $room)->with('status', 'Ruang disimpan.');
    }

    public function toggle(AcRoom $room): JsonResponse
    {
        if ($room->is_active && $room->units()->where('is_active', true)->exists()) {
            return response()->json(['message' => 'Ruang masih punya AC aktif. Pindahkan atau nonaktifkan AC-nya dulu.'], 422);
        }
        $room->update(['is_active' => ! $room->is_active]);

        return response()->json(['active' => $room->is_active]);
    }

    // Pilihan area: yang aktif, ditambah area ruang ini sendiri walau sudah nonaktif.
    private function areas(?AcRoom $room): array
    {
        return AcArea::query()->where('is_active', true)->when($room, fn ($q) => $q->orWhere('id', $room->area_id))->orderBy('name')->pluck('name', 'id')->all();
    }

    // Persegi bawaan ruang baru: sel kosong berikutnya pada kisi 3 kolom (25 x 25 persen) di denah areanya.
    private function nextCell(?int $areaId): array
    {
        $n = $areaId ? AcRoom::query()->where('area_id', $areaId)->count() : 0;

        return ['x' => 5 + ($n % 3) * 30, 'y' => 5 + (intdiv($n, 3) % 3) * 30, 'w' => 25, 'h' => 25];
    }
}
