<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleRequest;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Support\Palette;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/* REMARK
 * Tujuan       : Master Kendaraan (GA, modul GVK): plat (= kode), nama / model, jenis, tahun, no. rangka / mesin, wajib KIR, odometer terakhir, aktif / nonaktif. Kendaraan tidak dihapus (riwayatnya tersimpan), hanya dinonaktifkan.
 *                Odometer hanya dimasukkan sekali saat kendaraan didaftarkan (odometer awal); sesudah itu hanya naik lewat Catat Odometer / Catat Servis.
 * Sumber hulu  : m_vehicle, m_vehicle_type.
 * Output       : grid Kendaraan, form Kendaraan.
 * Dipakai oleh : routes/web.php (vehicles.*).
 * Status       : Aktif.
 */
class VehicleController extends Controller
{
    public function index()
    {
        $rows = Vehicle::query()->with('type')->orderBy('plate')->get()->map(fn (Vehicle $v) => [
            'id' => $v->id, 'plate' => $v->plate, 'name' => $v->name, 'type' => $v->type->name, 'hue' => Palette::item($v->type->code, $v->plate), 'year' => $v->year,
            'kir' => $v->kir_required ? 'Wajib' : 'Tidak wajib', 'odometer' => $v->odometer_km, 'vin' => $v->vin, 'engine_no' => $v->engine_no, 'active' => $v->is_active,
        ])->all();

        return view('vehicles.index', ['rows' => $rows]);
    }

    public function create()
    {
        return view('vehicles.form', ['vehicle' => new Vehicle(['kir_required' => true, 'odometer_km' => 0]), 'types' => $this->types(null)]);
    }

    public function show(Vehicle $vehicle)
    {
        return view('vehicles.form', ['vehicle' => $vehicle, 'types' => $this->types($vehicle)]);
    }

    public function store(VehicleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data += ['odometer_at' => today()];
        $vehicle = Vehicle::create($data);

        return redirect()->route('vehicles.show', $vehicle)->with('status', 'Kendaraan disimpan.');
    }

    public function update(VehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $action = $request->input('_action');
        if (in_array($action, ['activate', 'deactivate'], true)) {
            $vehicle->update(['is_active' => $action === 'activate']);

            return redirect()->route('vehicles.show', $vehicle)->with('status', $action === 'activate' ? 'Kendaraan diaktifkan.' : 'Kendaraan dinonaktifkan.');
        }

        $vehicle->update($request->validated());

        return redirect()->route('vehicles.show', $vehicle)->with('status', 'Kendaraan disimpan.');
    }

    public function toggle(Vehicle $vehicle): JsonResponse
    {
        $vehicle->update(['is_active' => ! $vehicle->is_active]);

        return response()->json(['active' => $vehicle->is_active]);
    }

    // Pilihan jenis: yang aktif, ditambah jenis kendaraan ini sendiri walau sudah nonaktif.
    private function types(?Vehicle $vehicle): array
    {
        return VehicleType::query()->where('is_active', true)->when($vehicle, fn ($q) => $q->orWhere('id', $vehicle->vehicle_type_id))->orderBy('name')->pluck('name', 'id')->all();
    }
}
