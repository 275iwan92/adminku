<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleDocRequest;
use App\Models\Vehicle;
use App\Services\VehicleCare;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/* REMARK
 * Tujuan       : Form Perpanjang Dokumen Kendaraan (GA, modul GVD): pembaruan pajak tahunan, STNK + plat 5 tahunan, KIR (hanya kendaraan wajib KIR), dan asuransi oleh petugas admin GA yang ditunjuk lewat hak Grup.
 *                Bawaan: tanggal bayar hari ini, berlaku sampai = tanggal + interval jenis dokumen (bisa diubah). Menulis butuh akses RW modul GVD; yang hanya-baca tidak melihat form ini.
 * Sumber hulu  : VehicleCare::recordDoc (g_vehicle_doc), m_vehicle.
 * Output       : form Perpanjang Dokumen; setelah simpan ke Detail kendaraan (bila boleh) atau kembali ke form.
 * Dipakai oleh : routes/web.php (vehicle-docs.*).
 * Status       : Aktif.
 */
class VehicleDocController extends Controller
{
    public function create(Request $request)
    {
        abort_unless($request->user()->canWrite('GVD'), 403, 'Akses Anda ke modul ini hanya-baca (RO).');
        $vehicles = Vehicle::query()->forInput()->get(['id', 'plate', 'name', 'kir_required']);
        $current = $vehicles->firstWhere('id', (int) $request->query('vehicle')) ?? $vehicles->first();

        return view('vehicle-docs.form', [
            'vehicles' => $vehicles->mapWithKeys(fn (Vehicle $v) => [$v->id => $v->label()])->all(), 'selected' => $current?->id,
            'kir' => $vehicles->mapWithKeys(fn (Vehicle $v) => [$v->id => $v->kir_required])->all(),
            'docs' => collect(VehicleCare::DOCS)->map(fn ($d) => $d[0])->all(), 'months' => collect(VehicleCare::DOCS)->map(fn ($d) => $d[1])->all(),
            'back' => $request->user()->canAccess('GVM') ? route('fleet.index') : route('home'),
        ]);
    }

    public function store(VehicleDocRequest $request): RedirectResponse
    {
        $vehicle = Vehicle::findOrFail($request->validated('vehicle_id'));
        $doc = VehicleCare::recordDoc($vehicle, $request->safe()->except(['vehicle_id', 'attachment']), $request->file('attachment'), $request->user());
        $msg = $doc->typeLabel().' '.$vehicle->plate.' diperpanjang sampai '.$doc->valid_until->format('d-m-Y').'.';

        return $request->user()->canAccess('GVM')
            ? redirect()->route('fleet.show', $vehicle)->with('status', $msg)
            : redirect()->route('vehicle-docs.create', ['vehicle' => $vehicle->id])->with('status', $msg);
    }
}
