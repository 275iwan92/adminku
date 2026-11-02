<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleOdometerRequest;
use App\Models\Vehicle;
use App\Services\VehicleCare;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/* REMARK
 * Tujuan       : Form Catat Odometer (cepat, GA, modul GVO): tanggal, kilometer (bawaan = odometer terakhir), dicatat oleh (bawaan nama pengguna), foto bukti opsional. Satu-satunya modul untuk sopir.
 *                Kilometer tidak boleh lebih kecil dari catatan terakhir. Foto hanya bukti (tanpa OCR).
 * Sumber hulu  : VehicleCare::recordOdometer (g_vehicle_odometer), m_vehicle.
 * Output       : form Catat Odometer; setelah simpan ke Detail kendaraan (bila punya hak Monitoring) atau kembali ke form.
 * Dipakai oleh : routes/web.php (vehicle-odometers.*).
 * Status       : Aktif.
 */
class VehicleOdometerController extends Controller
{
    public function create(Request $request)
    {
        abort_unless($request->user()->canWrite('GVO'), 403, 'Akses Anda ke modul ini hanya-baca (RO).');
        $vehicles = Vehicle::query()->forInput()->get(['id', 'plate', 'name', 'odometer_km']);
        $current = $vehicles->firstWhere('id', (int) $request->query('vehicle')) ?? $vehicles->first();

        return view('vehicle-odometers.form', [
            'vehicles' => $vehicles->mapWithKeys(fn (Vehicle $v) => [$v->id => $v->label()])->all(), 'selected' => $current?->id,
            'lastKm' => $vehicles->mapWithKeys(fn (Vehicle $v) => [$v->id => $v->odometer_km])->all(),
            'back' => $request->user()->canAccess('GVM') ? route('fleet.index') : route('home'),
        ]);
    }

    public function store(VehicleOdometerRequest $request): RedirectResponse
    {
        $vehicle = Vehicle::findOrFail($request->validated('vehicle_id'));
        $row = VehicleCare::recordOdometer($vehicle, $request->safe()->except(['vehicle_id', 'photo']), $request->file('photo'), $request->user());
        $msg = 'Odometer '.$vehicle->plate.' dicatat: '.VehicleCare::km($row->km).'.';

        return $request->user()->canAccess('GVM')
            ? redirect()->route('fleet.show', $vehicle)->with('status', $msg)
            : redirect()->route('vehicle-odometers.create', ['vehicle' => $vehicle->id])->with('status', $msg);
    }
}
