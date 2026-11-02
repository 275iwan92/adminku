<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\VehicleDoc;
use App\Models\VehicleOdometer;
use App\Services\VehicleCare;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/* REMARK
 * Tujuan       : Monitoring Kendaraan (GA, modul GVM): daftar kendaraan aktif dengan pajak tahunan, KIR, servis berikutnya, dan status Aman / Segera / Terlambat; ringkasan di atas;
 *                lalu Detail satu kendaraan (dokumen dan masa berlaku, jadwal perawatan dengan batang kemajuan, riwayat servis). Hanya-baca; pencatatan lewat tiga form input (modul GVD, GVS, GVO).
 *                Lampiran dokumen dan foto odometer disajikan di sini (privat di disk "ga", hanya yang punya hak modul GVM).
 * Sumber hulu  : VehicleCare::overview (jumlah kueri tetap), m_vehicle, g_vehicle_service.
 * Output       : grid Monitoring, layar Detail, berkas lampiran.
 * Dipakai oleh : routes/web.php (fleet.*).
 * Status       : Aktif.
 */
class FleetController extends Controller
{
    private const HISTORY = 30;

    public function index()
    {
        $vehicles = Vehicle::query()->with('type')->where('is_active', true)->orderBy('plate')->get();
        $over = VehicleCare::overview($vehicles);
        $order = array_flip(array_keys(VehicleCare::STATUS));   // aman 0, segera 1, terlambat 2
        $rows = $vehicles->map(fn (Vehicle $v) => [
            'id' => $v->id, 'plate' => $v->plate, 'name' => $v->name, 'type' => $v->type->name, 'odometer' => $v->odometer_km,
            'tax' => $over[$v->id]['docs']['pajak']['text'], 'kir' => $over[$v->id]['docs']['kir']['text'], 'stnk' => $over[$v->id]['docs']['stnk']['text'], 'insurance' => $over[$v->id]['docs']['asuransi']['text'],
            'service' => $over[$v->id]['next']['text'] ?? 'Tidak ada jadwal', 'status' => VehicleCare::STATUS[$over[$v->id]['status']], '_rank' => $order[$over[$v->id]['status']],
        ])->sortBy([['_rank', 'desc'], ['plate', 'asc']])->values()->map(fn ($r) => array_diff_key($r, ['_rank' => 1]))->all();
        $count = fn (string $status) => collect($over)->where('status', $status)->count();

        return view('fleet.index', [
            'rows' => $rows, 'active' => $vehicles->count(), 'late' => $count('terlambat'), 'soon' => $count('segera'), 'yearCost' => VehicleCare::yearCost(), 'year' => today()->year,
        ]);
    }

    public function show(Vehicle $vehicle)
    {
        $vehicle->load('type');
        $over = VehicleCare::overview(collect([$vehicle]))[$vehicle->id];
        $services = $vehicle->services()->with('lines')->orderByDesc('serviced_at')->orderByDesc('id')->limit(self::HISTORY)->get();

        return view('fleet.show', [
            'vehicle' => $vehicle, 'o' => $over, 'services' => $services, 'historyLimit' => self::HISTORY,
            'yearCost' => VehicleCare::yearCost(null, $vehicle->id), 'year' => today()->year,
        ]);
    }

    // Lampiran dokumen (kind=doc) atau foto odometer (kind=odometer).
    public function file(string $kind, int $id): StreamedResponse
    {
        $row = $kind === 'doc' ? VehicleDoc::with('vehicle')->findOrFail($id) : VehicleOdometer::with('vehicle')->findOrFail($id);
        $path = $kind === 'doc' ? $row->attachment_path : $row->photo_path;
        abort_unless($path && Storage::disk('ga')->exists($path), 404);
        $label = $kind === 'doc' ? $row->typeLabel() : 'odometer';
        $name = Str::slug($label.' '.$row->vehicle->plate.' '.($kind === 'doc' ? $row->paid_at : $row->read_at)->format('Y-m-d')).'.'.pathinfo($path, PATHINFO_EXTENSION);

        return Storage::disk('ga')->response($path, $name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=0']);
    }
}
