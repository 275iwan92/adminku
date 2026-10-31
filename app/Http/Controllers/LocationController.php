<?php

namespace App\Http\Controllers;

use App\Http\Requests\LocationRequest;
use App\Models\Location;
use App\Support\Palette;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/* REMARK
 * Tujuan       : Master Lokasi Penyimpanan (rak / gudang) yang dipilih di form Barang. Hanya Supervisor. Lokasi tidak dihapus (barang memakainya); yang tidak dipakai lagi dinonaktifkan.
 * Sumber hulu  : m_location (+ jumlah barang pemakai lewat i_batch.location).
 * Output       : grid Lokasi, form Lokasi.
 * Dipakai oleh : routes/web.php (locations.*, grup supervisor).
 * Status       : Aktif.
 */
class LocationController extends Controller
{
    public function index()
    {
        $rows = Location::query()->withCount('products')->orderBy('code')->get()->map(fn (Location $l) => [
            'id' => $l->id, 'code' => $l->code, 'name' => $l->name, 'hue' => Palette::item('LOC', $l->code), 'items' => $l->products_count, 'active' => $l->is_active,
        ])->all();

        return view('locations.index', ['rows' => $rows]);
    }

    public function create()
    {
        return view('locations.form', ['location' => new Location]);
    }

    public function show(Location $location)
    {
        return view('locations.form', ['location' => $location->loadCount('products')]);
    }

    public function store(LocationRequest $request): RedirectResponse|JsonResponse
    {
        $location = Location::create($request->validated());
        if ($request->expectsJson()) {   // tambah cepat dari dropdown; id = kode (kunci dropdown), name = label dropdown
            return response()->json(['id' => $location->code, 'name' => $location->label()], 201);
        }

        return redirect()->route('locations.show', $location)->with('status', 'Lokasi disimpan.');
    }

    public function update(LocationRequest $request, Location $location): RedirectResponse
    {
        $action = $request->input('_action');
        if (in_array($action, ['activate', 'deactivate'], true)) {
            $location->update(['is_active' => $action === 'activate']);

            return redirect()->route('locations.show', $location)->with('status', $action === 'activate' ? 'Lokasi diaktifkan.' : 'Lokasi dinonaktifkan.');
        }
        $location->update($request->safe()->only('name'));  // kode tidak berubah setelah disimpan

        return redirect()->route('locations.show', $location)->with('status', 'Lokasi disimpan.');
    }

    public function toggle(Location $location): JsonResponse
    {
        $location->update(['is_active' => ! $location->is_active]);

        return response()->json(['active' => $location->is_active]);
    }
}
