<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi simpan master Kategori: awalan kode barang tepat 3 karakter (huruf / angka, unik) dan nama; hanya Supervisor.
 * Dipakai oleh : CategoryController@store, @update.
 * Status       : Aktif.
 */
class CategoryRequest extends FormRequest
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

        return [
            'code' => ['required', 'regex:/^[A-Z0-9]{3}$/', Rule::unique('m_category', 'code')->ignore($this->route('category'))],
            'name' => ['required', 'string', 'max:100', ...($this->expectsJson() ? [Rule::unique('m_category', 'name')] : [])],
        ];
    }

    public function messages(): array
    {
        return ['code.regex' => 'Awalan kode harus tepat 3 karakter huruf atau angka (misalnya ATK, SPR).'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
    }
}
