<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcUnitRequest;
use App\Models\AcRoom;
use App\Models\AcUnit;
use App\Services\AcCare;
use App\Support\Palette;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;

/* REMARK
 * Tujuan       : Master Unit AC (GA, modul GAK): kode aset permanen (stiker pada unit; kosong = otomatis AC-nnnn; tidak berubah), ruang (kode ruang berubah bila AC dipindah), merek, tipe, PK, tanggal pasang,
 *                posisi titik di denah (kosong = otomatis di tengah ruang), cuci rutin dan servis besar terakhir sebelum tercatat di sistem (dasar jatuh tempo sampai ada riwayat), catatan. Aktif / Nonaktif saja.
 * Sumber hulu  : m_ac_unit, m_ac_room, AcCare.
 * Output       : grid Unit AC, form Unit AC.
 * Dipakai oleh : routes/web.php (ac-units.*).
 * Status       : Aktif.
 */
class AcUnitController extends Controller
{
    public function index()
    {
        $rows = AcUnit::query()->with('room.area')->orderBy('asset_code')->get()->map(fn (AcUnit $u) => [
            'id' => $u->id, 'asset_code' => $u->asset_code, 'room' => $u->room->code.' · '.$u->room->name, 'hue' => Palette::item('AC', $u->room->area->name), 'area' => $u->room->area->name, 'type' => $u->typeText(),
            'installed' => $u->installed_at?->format('d-m-Y'), 'wash' => $u->last_wash_at?->format('d-m-Y'), 'major' => $u->last_major_at?->format('d-m-Y'), 'active' => $u->is_active,
        ])->all();

        return view('ac-units.index', ['rows' => $rows]);
    }

    public function create()
    {
        return view('ac-units.form', ['unit' => new AcUnit(['asset_code' => AcUnit::nextCode(), 'capacity_pk' => 1, 'last_wash_at' => today()]), 'rooms' => $this->rooms(null)]);
    }

    public function show(AcUnit $unit)
    {
        return view('ac-units.form', ['unit' => $unit, 'rooms' => $this->rooms($unit)]);
    }

    public function store(AcUnitRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $unit = AcUnit::create($this->withPosition(['asset_code' => $data['asset_code'] ?? AcUnit::nextCode()] + $data));

        return redirect()->route('ac-units.show', $unit)->with('status', 'Unit AC '.$unit->asset_code.' disimpan.');
    }

    public function update(AcUnitRequest $request, AcUnit $unit): RedirectResponse
    {
        $action = $request->input('_action');
        if (in_array($action, ['activate', 'deactivate'], true)) {
            if ($action === 'deactivate' && ($why = $this->blocked($unit))) {
                return redirect()->route('ac-units.show', $unit)->withErrors(['brand' => $why]);
            }
            $unit->update(['is_active' => $action === 'activate']);

            return redirect()->route('ac-units.show', $unit)->with('status', $action === 'activate' ? 'Unit AC diaktifkan.' : 'Unit AC dinonaktifkan.');
        }

        $unit->update($this->withPosition(Arr::except($request->validated(), 'asset_code'), $unit));   // kode aset permanen

        return redirect()->route('ac-units.show', $unit)->with('status', 'Unit AC '.$unit->asset_code.' disimpan.');
    }

    public function toggle(AcUnit $unit): JsonResponse
    {
        if ($unit->is_active && ($why = $this->blocked($unit))) {
            return response()->json(['message' => $why], 422);
        }
        $unit->update(['is_active' => ! $unit->is_active]);

        return response()->json(['active' => $unit->is_active]);
    }

    // AC yang masih punya pesanan kunjungan terbuka tidak boleh dinonaktifkan (batalkan atau selesaikan kunjungannya dulu).
    private function blocked(AcUnit $unit): ?string
    {
        $open = AcCare::openLines([$unit->id])[$unit->id] ?? null;

        return $open ? 'AC ini masih terjadwal pada '.$open['date']->format('d-m-Y').' ('.$open['no'].'). Batalkan atau selesaikan kunjungannya dulu.' : null;
    }

    // Posisi titik kosong = otomatis: tengah ruang, bergeser ke kanan per AC lain di ruang yang sama (supaya titik tidak bertumpuk). Posisi yang tidak berubah ruangnya dipertahankan.
    private function withPosition(array $data, ?AcUnit $unit = null): array
    {
        if (($data['pos_x'] ?? null) !== null) {
            return $data;
        }
        if ($unit && $unit->pos_x !== null && (int) $data['room_id'] === $unit->room_id) {
            return ['pos_x' => $unit->pos_x, 'pos_y' => $unit->pos_y] + $data;
        }
        $room = AcRoom::findOrFail($data['room_id']);
        $n = AcUnit::query()->where('room_id', $room->id)->when($unit, fn ($q) => $q->where('id', '!=', $unit->id))->count();

        return [
            'pos_x' => round(min($room->x + $room->w - 2, $room->x + $room->w / 2 + ($n % 4) * 4 - 6), 2),
            'pos_y' => round($room->y + $room->h / 2 + intdiv($n, 4) * 5, 2),
        ] + $data;
    }

    private function rooms(?AcUnit $unit): array
    {
        return AcRoom::query()->with('area:id,name')->where('is_active', true)->when($unit, fn ($q) => $q->orWhere('id', $unit->room_id))->orderBy('code')->get()
            ->mapWithKeys(fn (AcRoom $r) => [$r->id => $r->code.' · '.$r->name.' ('.$r->area->name.')'])->all();
    }
}
