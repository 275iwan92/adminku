<?php

namespace App\Http\Requests;

use App\Models\Discipline;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi penerbitan SP 1 / SP 2 / SP 3 / PHK: karyawan aktif, jenis, tanggal terbit, berlaku s/d (SP; kosong = +6 bulan) atau tanggal efektif (PHK, wajib), alasan, rujukan aturan, penandatangan.
 * Dipakai oleh : DisciplineController@store.
 * Status       : Aktif.
 */
class DisciplineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', Rule::exists('m_employee', 'id')->where('is_active', true)],
            'type' => ['required', Rule::in(array_keys(Discipline::TYPES))],
            'issued_on' => ['required', 'date'],
            'until_on' => [$this->input('type') === 'phk' ? 'required' : 'nullable', 'date', 'after_or_equal:issued_on'],
            'reason' => ['required', 'string', 'max:500'],
            'rule_ref' => ['nullable', 'string', 'max:255'],
            'signer_name' => ['required', 'string', 'max:100'],
            'signer_title' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Pilih karyawan.',
            'employee_id.exists' => 'Karyawan harus masih aktif.',
            'until_on.required' => 'Isi tanggal efektif PHK.',
            'until_on.after_or_equal' => 'Tanggal ini tidak boleh sebelum tanggal terbit.',
            'reason.required' => 'Isi pelanggaran atau alasan.',
            'signer_name.required' => 'Isi nama penandatangan.',
            'signer_title.required' => 'Isi jabatan penandatangan.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => Text::of($this->input('reason')), 'signer_name' => Text::of($this->input('signer_name')), 'signer_title' => Text::of($this->input('signer_title'))]);
    }
}
