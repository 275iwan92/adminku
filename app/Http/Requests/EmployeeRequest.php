<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Support\GeoArea;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi simpan Karyawan (profil, pekerjaan, daftar keluarga / dokumen / kontrak). Aksi Nonaktifkan hanya memeriksa alasan + tanggal keluar; Aktifkan tidak membawa isian.
 *                Wilayah KTP / domisili = "LEVEL:id" dari lookup geo_area; employeeData() mengembalikan ktp_area_level + ktp_area_id (dan home_*), bukan ktp_area / home_area.
 * Dipakai oleh : EmployeeController@store, @update.
 * Status       : Aktif.
 */
class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $action = $this->input('_action');
        if ($action === 'activate') {
            return [];
        }
        if ($action === 'deactivate') {
            return [
                'exit_reason' => ['required', Rule::in(array_keys(Employee::EXIT_REASONS))],
                'exit_date' => ['required', 'date'],
                'exit_note' => ['nullable', 'string', 'max:255'],
            ];
        }
        $area = fn ($attr, $value, $fail) => $value === null || $value === '' || GeoArea::find($value) ? null : $fail('Pilih wilayah dari daftar.');
        $keys = fn (array $a) => Rule::in(array_keys($a));
        $employee = $this->route('employee');

        return [
            'code' => [$employee ? 'nullable' : 'required', 'string', 'max:20', Rule::unique('m_employee', 'code')->ignore($employee)],
            'name' => ['required', 'string', 'max:150'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'gender' => ['required', $keys(Employee::GENDERS)],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'religion' => ['nullable', 'string', 'max:20'],
            'marital' => ['nullable', $keys(Employee::MARITALS)],
            'nik_ktp' => ['nullable', 'digits:16', Rule::unique('m_employee', 'nik_ktp')->ignore($employee)],
            'npwp' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'ktp_address' => ['nullable', 'string', 'max:255'],
            'ktp_area' => ['nullable', 'string', $area],
            'home_address' => ['nullable', 'string', 'max:255'],
            'home_area' => ['nullable', 'string', $area],
            'emergency_name' => ['nullable', 'string', 'max:150'],
            'emergency_relation' => ['nullable', 'string', 'max:50'],
            'emergency_phone' => ['nullable', 'string', 'max:30'],
            'department_id' => ['required', Rule::exists('m_department', 'id')],
            'position' => ['nullable', 'string', 'max:100'],
            'employment' => ['required', $keys(Employee::EMPLOYMENTS)],
            'join_date' => ['required', 'date'],
            'work_status' => ['required', $keys(Employee::WORK_STATUSES)],
            'user_id' => ['nullable', Rule::exists('users', 'id'), Rule::unique('m_employee', 'user_id')->ignore($employee)],
            'family' => ['nullable', 'array'],
            'family.*.relation' => ['required', $keys(Employee::RELATIONS)],
            'family.*.name' => ['required', 'string', 'max:150'],
            'family.*.birth_date' => ['nullable', 'date'],
            'family.*.nik' => ['nullable', 'digits:16'],
            'family.*.is_deceased' => ['nullable', 'boolean'],
            'docs' => ['nullable', 'array'],
            'docs.*.doc_type' => ['required', $keys(Employee::DOC_TYPES)],
            'docs.*.doc_no' => ['nullable', 'string', 'max:50'],
            'docs.*.expiry_date' => ['nullable', 'date'],
            'docs.*.note' => ['nullable', 'string', 'max:255'],
            'contracts' => ['nullable', 'array'],
            'contracts.*.contract_no' => ['nullable', 'string', 'max:50'],
            'contracts.*.employment' => ['required', $keys(Employee::EMPLOYMENTS)],
            'contracts.*.start_date' => ['required', 'date'],
            'contracts.*.end_date' => ['nullable', 'date', 'after_or_equal:contracts.*.start_date'],
            'contracts.*.probation_end' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'exit_reason.required' => 'Pilih alasan keluar.',
            'exit_date.required' => 'Isi tanggal efektif keluar.',
            'nik_ktp.digits' => 'NIK KTP harus 16 angka.',
            'nik_ktp.unique' => 'NIK KTP ini sudah dipakai karyawan lain.',
            'family.*.nik.digits' => 'NIK anggota keluarga harus 16 angka.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(Text::of($this->input('code'))),
            'name' => Text::of($this->input('name')),
            'nik_ktp' => preg_replace('/\D/', '', Text::of($this->input('nik_ktp'))) ?: null,
            'user_id' => $this->input('user_id') ?: null,
        ]);
    }

    /** Hasil validasi untuk disimpan: kunci wilayah dipecah, daftar keluarga / dokumen / kontrak dirapikan. */
    public function employeeData(): array
    {
        $data = $this->validated();
        foreach (['ktp', 'home'] as $p) {
            [$data[$p.'_area_level'], $data[$p.'_area_id']] = ($data[$p.'_area'] ?? '') !== '' ? explode(':', $data[$p.'_area']) : [null, null];
            unset($data[$p.'_area']);
        }
        foreach (['family', 'docs', 'contracts'] as $k) {
            $data[$k] = array_values($data[$k] ?? []);
        }
        foreach ($data['family'] as &$row) {
            $row['is_deceased'] = (bool) ($row['is_deceased'] ?? false);
        }

        return $data;
    }
}
