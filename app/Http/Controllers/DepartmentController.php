<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepartmentRequest;
use App\Models\Department;
use App\Support\Palette;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/* REMARK
 * Tujuan       : Master Departemen (dipilih di Issuance; dasar analisis pengeluaran per departemen; di menu HR bernama Bagian dan dipakai karyawan). Data Entry dan Supervisor sama-sama boleh mengelola.
 * Sumber hulu  : m_department (+ jumlah penerima dan dokumen).
 * Output       : grid Departemen, form Departemen.
 * Dipakai oleh : routes/web.php (departments.*).
 * Status       : Aktif.
 */
class DepartmentController extends Controller
{
    public function index()
    {
        $rows = Department::query()->withCount(['recipients', 'documents', 'employees'])->orderBy('code')->get()->map(fn (Department $d) => [
            'id' => $d->id, 'code' => $d->code, 'name' => $d->name, 'recipients' => $d->recipients_count, 'documents' => $d->documents_count, 'employees' => $d->employees_count, 'active' => $d->is_active, 'hue' => crc32($d->code) % 360, 'icon' => Palette::departmentIcon($d->name),
        ])->all();

        return view('departments.index', ['rows' => $rows]);
    }

    public function create()
    {
        return view('departments.form', ['department' => new Department(['code' => Department::nextCode()])]);   // kode bawaan, boleh diubah
    }

    public function show(Department $department)
    {
        return view('departments.form', ['department' => $department->loadCount(['recipients', 'documents', 'employees'])]);
    }

    public function store(DepartmentRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        if ($request->expectsJson()) {
            return response()->json(Department::create(array_merge($data, ['code' => $data['code'] ?: Department::codeFor($data['name'])]))->only(['id', 'name']), 201);
        }
        $department = Department::create(array_merge($data, ['code' => $data['code'] ?: Department::codeFor($data['name'])]));

        return redirect()->route('departments.show', $department)->with('status', 'Departemen disimpan.');
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $action = $request->input('_action');
        if (in_array($action, ['activate', 'deactivate'], true)) {
            $department->update(['is_active' => $action === 'activate']);

            return redirect()->route('departments.show', $department)->with('status', $action === 'activate' ? 'Departemen diaktifkan.' : 'Departemen dinonaktifkan.');
        }
        $department->update($request->validated());

        return redirect()->route('departments.show', $department)->with('status', 'Departemen disimpan.');
    }

    public function toggle(Department $department): JsonResponse
    {
        $department->update(['is_active' => ! $department->is_active]);

        return response()->json(['active' => $department->is_active]);
    }
}
