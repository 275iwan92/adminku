<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\Discipline;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
        $sp = Discipline::query()->inForce()->get()->groupBy('employee_id')->map(fn ($g) => Discipline::TYPES[$g->sortByDesc(fn (Discipline $d) => Discipline::RANK[$d->type])->first()->type]);
        $rows = Employee::query()->with(['department', 'contracts', 'docs'])->orderBy('code')->get()->map(function (Employee $e) use ($sp) {
            $attention = $e->attention();

            return [
                'id' => $e->id, 'code' => $e->code, 'name' => $e->name, 'position' => $e->position, 'department' => $e->department->name, 'employment' => Employee::EMPLOYMENTS[$e->employment],
                'join_date' => $e->join_date->format('Y-m-d'), 'status' => $e->is_active ? Employee::WORK_STATUSES[$e->work_status] : 'Keluar: '.(Employee::EXIT_REASONS[$e->exit_reason] ?? '-'),
                'attention' => $attention, 'attn' => $attention !== null, 'sp' => $sp[$e->id] ?? '', 'active' => $e->is_active,
            ];
        })->all();

        return view('employees.index', ['rows' => $rows]);
    }

    public function create(Request $request)
    {
        $employee = new Employee(['code' => Employee::nextCode(), 'employment' => 'tetap', 'work_status' => 'working', 'join_date' => today()]);
        if ($candidate = Candidate::with('departments')->find($request->query('kandidat'))) {   // Jadikan karyawan dari Rekrutmen: data kandidat terisi, belum tersimpan
            $employee->fill(['name' => $candidate->name, 'nik_ktp' => $candidate->nik, 'phone' => $candidate->phone, 'email' => $candidate->email, 'birth_date' => $candidate->birth_date, 'department_id' => $candidate->departments->first()?->id, 'position' => $candidate->position]);
        }

        return view('employees.form', $this->formData($employee));
    }

    public function show(Request $request, Employee $employee)
    {
        if ($employee->is_active && array_key_exists((string) $request->query('exit'), Employee::EXIT_REASONS)) {   // dari penerbitan PHK: alasan keluar terisi (belum tersimpan)
            $employee->fill(['exit_reason' => $request->query('exit'), 'exit_date' => today()]);
        }

        return view('employees.form', $this->formData($employee));
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        $employee = DB::transaction(function () use ($request) {
            $employee = $this->save(new Employee, $request->employeeData());
            $this->saveFiles($employee, $request);

            return $employee;
        });

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

        DB::transaction(function () use ($request, $employee) {
            $this->save($employee, $request->employeeData());
            $this->saveFiles($employee, $request);
        });

        return redirect()->route('employees.show', $employee)->with('status', 'Karyawan disimpan.');
    }

    // Berkas privat (disk 'ga', folder employee/{id}): foto profil dan scan KTP; berkas baru mengganti yang lama.
    private function saveFiles(Employee $employee, EmployeeRequest $request): void
    {
        foreach (['photo' => 'photo', 'ktp_file' => 'ktp', 'ktp_crop' => 'ktp'] as $input => $col) {   // hasil crop KTP (diproses terakhir) menggantikan berkas yang diunggah apa adanya
            if (! $request->hasFile($input)) {
                continue;
            }
            $file = $request->file($input);
            $old = $employee->{$col.'_path'};
            $employee->update([$col.'_path' => $file->store("employee/{$employee->id}", 'ga'), $col.'_name' => mb_substr($file->getClientOriginalName(), 0, 150)]);
            $old && Storage::disk('ga')->delete($old);
        }
    }

    // Tampil berkas (foto / scan KTP) sebagai gambar; hanya pemegang hak HRE (rute employees.*).
    public function file(Employee $employee, string $kind)
    {
        $path = $employee->{$kind.'_path'};
        abort_unless($path && Storage::disk('ga')->exists($path), 404);

        return Storage::disk('ga')->response($path, $employee->{$kind.'_name'});
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
            'disciplines' => $employee->exists ? Discipline::where('employee_id', $employee->id)->orderByDesc('issued_on')->orderByDesc('id')->get() : collect(),
            'departments' => Department::query()->where('is_active', true)->when($employee->department_id, fn ($q) => $q->orWhere('id', $employee->department_id))->orderBy('name')->pluck('name', 'id')->all(),
            'users' => User::query()->whereNotIn('id', $taken)->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $employee->user_id))->orderBy('name')->pluck('name', 'id')->all(),
            'family' => $lines('family', ['relation', 'name', 'birth_date', 'nik', 'is_deceased'], ['birth_date']),
            'docs' => $lines('docs', ['doc_type', 'doc_no', 'expiry_date', 'note'], ['expiry_date']),
            'contracts' => $lines('contracts', ['contract_no', 'employment', 'start_date', 'end_date', 'probation_end'], ['start_date', 'end_date', 'probation_end']),
        ];
    }
}
