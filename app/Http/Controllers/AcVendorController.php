<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcVendorRequest;
use App\Models\AcVendor;
use App\Support\Palette;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/* REMARK
 * Tujuan       : Master Vendor AC (GA, modul GAE): vendor jasa perawatan AC dengan nomor WhatsApp dan e-mail untuk pemberitahuan kunjungan. Kapasitas harian ikut pengaturan (ga.ac.capacity) dan dihitung per vendor. Aktif / Nonaktif saja.
 * Sumber hulu  : m_ac_vendor.
 * Output       : grid Vendor AC, form Vendor AC.
 * Dipakai oleh : routes/web.php (ac-vendors.*).
 * Status       : Aktif.
 */
class AcVendorController extends Controller
{
    public function index()
    {
        $rows = AcVendor::query()->orderBy('code')->get()->map(fn (AcVendor $v) => [
            'id' => $v->id, 'code' => $v->code, 'name' => $v->name, 'hue' => Palette::item('AC', $v->code), 'contact' => $v->contact_name, 'phone' => $v->phone, 'email' => $v->email, 'active' => $v->is_active,
        ])->all();

        return view('ac-vendors.index', ['rows' => $rows]);
    }

    public function create()
    {
        return view('ac-vendors.form', ['vendor' => new AcVendor(['code' => AcVendor::nextCode()])]);
    }

    public function show(AcVendor $vendor)
    {
        return view('ac-vendors.form', ['vendor' => $vendor]);
    }

    public function store(AcVendorRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $vendor = AcVendor::create(['code' => $data['code'] ?? AcVendor::nextCode()] + $data);

        return redirect()->route('ac-vendors.show', $vendor)->with('status', 'Vendor AC disimpan.');
    }

    public function update(AcVendorRequest $request, AcVendor $vendor): RedirectResponse
    {
        $action = $request->input('_action');
        if (in_array($action, ['activate', 'deactivate'], true)) {
            $vendor->update(['is_active' => $action === 'activate']);

            return redirect()->route('ac-vendors.show', $vendor)->with('status', $action === 'activate' ? 'Vendor AC diaktifkan.' : 'Vendor AC dinonaktifkan.');
        }

        $vendor->update(array_filter($request->validated(), fn ($v, $k) => $k !== 'code' || $v !== null, ARRAY_FILTER_USE_BOTH));

        return redirect()->route('ac-vendors.show', $vendor)->with('status', 'Vendor AC disimpan.');
    }

    public function toggle(AcVendor $vendor): JsonResponse
    {
        $vendor->update(['is_active' => ! $vendor->is_active]);

        return response()->json(['active' => $vendor->is_active]);
    }
}
