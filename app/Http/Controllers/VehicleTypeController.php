<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleTypeRequest;
use App\Models\VehicleType;
use App\Support\Palette;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Master Jenis Kendaraan (GA, modul GVT) beserta rencana perawatannya: pekerjaan dengan interval km dan/atau bulan. Interval diatur DI SINI, bukan diketik ulang tiap servis.
 *                Jenis tidak dihapus (kendaraan memakainya), hanya dinonaktifkan. Butir rencana disinkronkan menurut nama: nama sama = diperbarui (riwayat servisnya tetap terhubung), nama baru = ditambah, nama hilang = dibuang (baris riwayat servis tetap menyimpan namanya).
 * Sumber hulu  : m_vehicle_type, m_vehicle_type_plan, jumlah kendaraan pemakai.
 * Output       : grid Jenis Kendaraan, form Jenis Kendaraan dengan tabel rencana perawatan.
 * Dipakai oleh : routes/web.php (vehicle-types.*).
 * Status       : Aktif.
 */
class VehicleTypeController extends Controller
{
    public function index()
    {
        $rows = VehicleType::query()->withCount(['vehicles', 'plans'])->orderBy('code')->get()->map(fn (VehicleType $t) => [
            'id' => $t->id, 'code' => $t->code, 'name' => $t->name, 'hue' => Palette::item('VEH', $t->code), 'plans' => $t->plans_count, 'vehicles' => $t->vehicles_count, 'active' => $t->is_active,
        ])->all();

        return view('vehicle-types.index', ['rows' => $rows]);
    }

    public function create()
    {
        return view('vehicle-types.form', ['type' => new VehicleType, 'plans' => old('plans', [])]);
    }

    public function show(VehicleType $type)
    {
        $type->loadCount('vehicles');

        return view('vehicle-types.form', ['type' => $type, 'plans' => old('plans') ?? $type->plans->map(fn ($p) => ['name' => $p->name, 'interval_km' => $p->interval_km, 'interval_months' => $p->interval_months])->all()]);
    }

    public function store(VehicleTypeRequest $request): RedirectResponse
    {
        $type = DB::transaction(function () use ($request) {
            $type = VehicleType::create($request->safe()->only(['code', 'name']));
            $this->syncPlans($type, $request->validated('plans', []));

            return $type;
        });

        return redirect()->route('vehicle-types.show', $type)->with('status', 'Jenis kendaraan disimpan.');
    }

    public function update(VehicleTypeRequest $request, VehicleType $type): RedirectResponse
    {
        $action = $request->input('_action');
        if (in_array($action, ['activate', 'deactivate'], true)) {
            $type->update(['is_active' => $action === 'activate']);

            return redirect()->route('vehicle-types.show', $type)->with('status', $action === 'activate' ? 'Jenis kendaraan diaktifkan.' : 'Jenis kendaraan dinonaktifkan.');
        }

        DB::transaction(function () use ($request, $type) {
            $type->update($request->safe()->only('name'));   // kode tidak berubah setelah disimpan
            $this->syncPlans($type, $request->validated('plans', []));
        });

        return redirect()->route('vehicle-types.show', $type)->with('status', 'Jenis kendaraan disimpan.');
    }

    public function toggle(VehicleType $type): JsonResponse
    {
        $type->update(['is_active' => ! $type->is_active]);

        return response()->json(['active' => $type->is_active]);
    }

    // Butir rencana = salinan form: dicocokkan menurut nama (tanpa beda huruf besar / kecil), urutan form disimpan.
    private function syncPlans(VehicleType $type, array $rows): void
    {
        $existing = $type->plans()->get()->keyBy(fn ($p) => mb_strtolower($p->name));
        $keep = [];
        foreach (array_values($rows) as $i => $row) {
            $data = ['name' => $row['name'], 'interval_km' => $row['interval_km'] ?: null, 'interval_months' => $row['interval_months'] ?: null, 'sort' => $i + 1];
            $plan = $existing[mb_strtolower($row['name'])] ?? null;
            $plan ? $plan->update($data) : $plan = $type->plans()->create($data);
            $keep[] = $plan->id;
        }
        $type->plans()->whereNotIn('id', $keep)->delete();
    }
}
