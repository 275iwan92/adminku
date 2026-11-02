<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Master Karyawan (HR, modul HRE), kepegawaian saja tanpa gaji: profil, pekerjaan, dokumen (nomor + masa berlaku), keluarga, riwayat kontrak, akun Jays opsional.
 *                Karyawan tidak dihapus: Nonaktifkan wajib alasan + tanggal efektif (akun Jays ikut nonaktif), Aktifkan kembali kecuali alasan Meninggal.
 * Sumber hulu  : m_employee (+ family, doc, contract), m_department, users.
 * Output       : grid Karyawan, form Karyawan (lima tab).
 * Dipakai oleh : routes/web.php (employees.*).
 * Status       : Aktif.
 */
class EmployeeController extends Controller
{
    public function index()
    {
        $rows = Employee::query()->with(['department', 'contracts', 'docs'])->orderBy('code')->get()->map(function (Employee $e) {
            $attention = $e->attention();

            return [
                'id' => $e->id, 'code' => $e->code, 'name' => $e->name, 'position' => $e->position, 'department' => $e->department->name, 'employment' => Employee::EMPLOYMENTS[$e->employment],
                'join_date' => $e->join_date->format('Y-m-d'), 'status' => $e->is_active ? Employee::WORK_STATUSES[$e->work_status] : 'Keluar: '.(Employee::EXIT_REASONS[$e->exit_reason] ?? '-'),
                'attention' => $attention, 'attn' => $attention !== null, 'active' => $e->is_active,
            ];
        })->all();

        return view('employees.index', ['rows' => $rows]);
    }

    public function create()
    {
        return view('employees.form', $this->formData(new Employee(['code' => Employee::nextCode(), 'employment' => 'tetap', 'work_status' => 'working', 'join_date' => today()])));
    }

    public function show(Employee $employee)
    {
        return view('employees.form', $this->formData($employee));
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        $employee = DB::transaction(fn () => $this->save(new Employee, $request->employeeData()));

        return redirect()->route('employees.show', $employee)->with('status', 'Karyawan disimpan.');
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $action = $request->input('_action');
        if ($action === 'deactivate') {
            DB::transaction(function () use ($request, $employee) {
                $employee->update($request->validated() + ['is_active' => false]);
                if ($employee->user_id && $employee->user_id !== $request->user()->id) {
                    User::whereKey($employee->user_id)->update(['is_active' => false]);   // akun sendiri tidak dimatikan
                }
            });

            return redirect()->route('employees.show', $employee)->with('status', 'Karyawan dinonaktifkan.');
        }
        if ($action === 'activate') {
            if ($employee->exit_reason === 'deceased') {
                return redirect()->route('employees.show', $employee)->withErrors(['exit_reason' => 'Karyawan yang meninggal tidak bisa diaktifkan kembali.']);
            }
            $employee->update(['is_active' => true, 'exit_reason' => null, 'exit_date' => null, 'exit_note' => null]);

            return redirect()->route('employees.show', $employee)->with('status', 'Karyawan diaktifkan kembali.');
        }

        DB::transaction(fn () => $this->save($employee, $request->employeeData()));

        return redirect()->route('employees.show', $employee)->with('status', 'Karyawan disimpan.');
    }

    // Keluarga, dokumen, dan kontrak = salinan form: baris lama diganti seluruhnya (tidak ada yang merujuk id barisnya). Kode tidak berubah setelah disimpan.
    private function save(Employee $employee, array $data): Employee
    {
        [$family, $docs, $contracts] = [$data['family'], $data['docs'], $data['contracts']];
        unset($data['family'], $data['docs'], $data['contracts']);
        if ($employee->exists) {
            $employee->update(collect($data)->except('code')->all());
        } else {
            $employee = Employee::create($data);
        }
        foreach (['family' => $family, 'docs' => $docs, 'contracts' => $contracts] as $relation => $rows) {
            $employee->{$relation}()->delete();
            $employee->{$relation}()->createMany($rows);
        }

        return $employee;
    }

    private function formData(Employee $employee): array
    {
        $lines = fn (string $rel, array $keys, array $dates) => old($rel) ?? $employee->{$rel}->map(fn ($r) => collect($keys)->mapWithKeys(fn ($k) => [$k => in_array($k, $dates, true) ? $r->{$k}?->format('Y-m-d') : $r->{$k}])->all())->all();
        $taken = Employee::query()->whereNotNull('user_id')->where('id', '!=', $employee->id ?? 0)->pluck('user_id');

        return [
            'employee' => $employee,
            'departments' => Department::query()->where('is_active', true)->when($employee->department_id, fn ($q) => $q->orWhere('id', $employee->department_id))->orderBy('name')->pluck('name', 'id')->all(),
            'users' => User::query()->whereNotIn('id', $taken)->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $employee->user_id))->orderBy('name')->pluck('name', 'id')->all(),
            'family' => $lines('family', ['relation', 'name', 'birth_date', 'nik', 'is_deceased'], ['birth_date']),
            'docs' => $lines('docs', ['doc_type', 'doc_no', 'expiry_date', 'note'], ['expiry_date']),
            'contracts' => $lines('contracts', ['contract_no', 'employment', 'start_date', 'end_date', 'probation_end'], ['start_date', 'end_date', 'probation_end']),
        ];
    }
}
