<?php

namespace App\Http\Requests;

use App\Models\Unit;
use App\Rules\UniqueName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi simpan master Satuan (tambah / ubah) beserta daftar konversinya (1 satuan ini = faktor x satuan lain); hanya Supervisor.
 * Dipakai oleh : UnitController@store, @update.
 * Status       : Aktif.
 */
class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSupervisor();
    }

    public function rules(): array
    {
        if (in_array($this->input('_action'), ['activate', 'deactivate'], true)) {
            return [];
        }
        $own = $this->route('unit')?->code ?? $this->input('code');

        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[a-z0-9._\/-]+$/', Rule::unique('m_unit', 'code')->ignore($this->route('unit'))],
            'name' => ['required', 'string', 'max:100', new UniqueName('m_unit', $this->route('unit'))],
            'kind' => ['nullable', Rule::in(array_keys(Unit::KINDS))],
            'conversions' => ['nullable', 'array', 'max:50'],
            'conversions.*.to_unit' => ['required', 'distinct', Rule::notIn([$own]), Rule::exists('m_unit', 'code')],
            'conversions.*.factor' => ['required', 'numeric', 'gt:0', 'max:999999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Gunakan huruf kecil, angka, dan . _ / - saja (misalnya pcs, box, m2).',
            'conversions.*.to_unit.required' => 'Pilih satuan tujuan di setiap baris konversi.',
            'conversions.*.to_unit.distinct' => 'Satuan tujuan yang sama muncul di lebih dari satu baris konversi.',
            'conversions.*.to_unit.not_in' => 'Satuan tidak bisa dikonversi ke dirinya sendiri.',
            'conversions.*.factor.required' => 'Isi faktor di setiap baris konversi.',
            'conversions.*.factor.gt' => 'Faktor harus lebih dari 0.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtolower(trim((string) $this->input('code'))), 'name' => trim((string) $this->input('name'))]);
    }
}
