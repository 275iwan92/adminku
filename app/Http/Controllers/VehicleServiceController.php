<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleServiceRequest;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Services\VehicleCare;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/* REMARK
 * Tujuan       : Form Catat Servis (GA, modul GVS): kendaraan, tanggal, odometer (bawaan = odometer terakhir), pekerjaan yang dikerjakan (dicentang dari jadwal jenis kendaraan itu), bengkel, biaya total, catatan.
 *                Jadwal berikutnya dihitung otomatis (odometer + interval km, tanggal + interval bulan). Pengambilan oli / filter dari gudang tetap manual: modul ini TIDAK menyentuh stok.
 * Sumber hulu  : VehicleCare::recordService (g_vehicle_service, g_vehicle_service_line, g_vehicle_odometer), m_vehicle_type_plan.
 * Output       : form Catat Servis; setelah simpan ke Detail kendaraan (bila boleh) atau kembali ke form.
 * Dipakai oleh : routes/web.php (vehicle-services.*).
 * Status       : Aktif.
 */
class VehicleServiceController extends Controller
{
    public function create(Request $request)
    {
        abort_unless($request->user()->canWrite('GVS'), 403, 'Akses Anda ke modul ini hanya-baca (RO).');
        $vehicles = Vehicle::query()->forInput()->get(['id', 'plate', 'name', 'vehicle_type_id', 'odometer_km']);
        $current = $vehicles->firstWhere('id', (int) $request->query('vehicle')) ?? $vehicles->first();
        $types = VehicleType::query()->whereIn('id', $vehicles->pluck('vehicle_type_id')->unique())->with('plans')->get();

        return view('vehicle-services.form', [
            'vehicles' => $vehicles->mapWithKeys(fn (Vehicle $v) => [$v->id => $v->label()])->all(), 'selected' => $current?->id,
            'typeOf' => $vehicles->mapWithKeys(fn (Vehicle $v) => [$v->id => $v->vehicle_type_id])->all(),
            'lastKm' => $vehicles->mapWithKeys(fn (Vehicle $v) => [$v->id => $v->odometer_km])->all(),
            'plans' => $types->mapWithKeys(fn (VehicleType $t) => [$t->id => $t->plans->mapWithKeys(fn ($p) => [$p->id => $p->name.' ('.$p->intervalLabel().')'])->all()])->all(),
            'back' => $request->user()->canAccess('GVM') ? route('fleet.index') : route('home'),
        ]);
    }

    public function store(VehicleServiceRequest $request): RedirectResponse
    {
        $vehicle = Vehicle::findOrFail($request->validated('vehicle_id'));
        $service = VehicleCare::recordService($vehicle, $request->safe()->except('vehicle_id'), $request->user());
        $msg = 'Servis '.$vehicle->plate.' tanggal '.$service->serviced_at->format('d-m-Y').' dicatat. Jadwal berikutnya dihitung otomatis.';

        return $request->user()->canAccess('GVM')
            ? redirect()->route('fleet.show', $vehicle)->with('status', $msg)
            : redirect()->route('vehicle-services.create', ['vehicle' => $vehicle->id])->with('status', $msg);
    }
}
