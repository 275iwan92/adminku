<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use App\Support\GeoArea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/* REMARK
 * Tujuan       : Master Supplier (dipilih di transaksi Receiving). Data Entry dan Supervisor sama-sama boleh mengelola.
 * Sumber hulu  : m_supplier, GeoArea (kota + negara di grid).
 * Output       : grid Suppliers, form Supplier.
 * Dipakai oleh : routes/web.php (suppliers.*).
 * Status       : Aktif.
 */
class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::query()->orderBy('code')->get();
        $areas = GeoArea::findMany($suppliers->map->areaKey()->all());
        $rows = $suppliers->map(fn (Supplier $s) => [
            'id' => $s->id, 'code' => $s->code, 'name' => $s->name, 'phone' => $s->phone, 'contact' => $s->contact_name, 'mobile' => $s->contact_mobile,
            'place' => ($a = $areas[$s->areaKey()] ?? null) ? $a['city'].', '.$a['country'] : null, 'address' => $s->address, 'active' => $s->is_active,
        ])->all();

        return view('suppliers.index', ['rows' => $rows]);
    }

    public function create()
    {
        return view('suppliers.form', ['supplier' => new Supplier]);
    }

    public function show(Supplier $supplier)
    {
        return view('suppliers.form', ['supplier' => $supplier]);
    }

    public function store(SupplierRequest $request): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {   // tambah cepat dari dropdown (x-dl::field.select add)
            return response()->json(Supplier::createQuick($request->validated())->only(['id', 'name']), 201);
        }

        $supplier = Supplier::create($request->validated());

        return redirect()->route('suppliers.show', $supplier)->with('status', 'Supplier disimpan.');
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $action = $request->input('_action');
        if (in_array($action, ['activate', 'deactivate'], true)) {
            $supplier->update(['is_active' => $action === 'activate']);

            return redirect()->route('suppliers.show', $supplier)->with('status', $action === 'activate' ? 'Supplier diaktifkan.' : 'Supplier dinonaktifkan.');
        }

        $supplier->update($request->validated());

        return redirect()->route('suppliers.show', $supplier)->with('status', 'Supplier disimpan.');
    }

    public function toggle(Supplier $supplier): JsonResponse
    {
        if ($supplier->is_system) {
            return response()->json(['message' => Supplier::SYSTEM_MESSAGE], 422);
        }

        $supplier->update(['is_active' => ! $supplier->is_active]);

        return response()->json(['active' => $supplier->is_active]);
    }
}
