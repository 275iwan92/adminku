<?php

namespace App\Http\Requests;

use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi simpan master Area / Gedung AC: kode (kosong = otomatis dari nama), nama, denah unggahan (jpg / png, maks 5 MB; boleh kosong) dan hapus denah unggahan.
 * Dipakai oleh : AcAreaController@store, @update.
 * Status       : Aktif.
 */
class AcAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;   // hak diatur modul GAA (Grup), dijaga EnsureModuleAccess
    }

    public function rules(): array
    {
        if (in_array($this->input('_action'), ['activate', 'deactivate'], true)) {
            return [];
        }

        return [
            'code' => ['nullable', 'string', 'max:20', Rule::unique('m_ac_area', 'code')->ignore($this->route('area'))],
            'name' => ['required', 'string', 'max:100'],
            'plan' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'remove_plan' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return ['plan.mimes' => 'Denah harus berupa gambar JPG atau PNG.', 'plan.max' => 'Denah paling besar 5 MB.'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(Text::of($this->input('code'))) ?: null, 'name' => Text::of($this->input('name')), 'remove_plan' => $this->boolean('remove_plan')]);
    }
}
