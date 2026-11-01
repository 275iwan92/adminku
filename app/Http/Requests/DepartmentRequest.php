<?php

namespace App\Http\Requests;

use App\Rules\UniqueName;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi simpan master Departemen (kode unik, nama). Aktif / nonaktif tidak membawa isian.
 * Dipakai oleh : DepartmentController@store, @update.
 * Status       : Aktif.
 */
class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        if (in_array($this->input('_action'), ['activate', 'deactivate'], true)) {
            return [];
        }

        return [
            'code' => [$this->route('department') ? 'required' : 'nullable', 'string', 'max:20', Rule::unique('m_department', 'code')->ignore($this->route('department'))],
            'name' => ['required', 'string', 'max:100', new UniqueName('m_department', $this->route('department'))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(Text::of($this->input('code'))), 'name' => Text::of($this->input('name'))]);  // kode kosong (baru) -> dibuat controller
    }
}
