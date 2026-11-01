<?php

namespace App\Http\Requests;

use App\Rules\UniqueName;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi simpan master Penerima barang (nama, departemen bawaan opsional).
 * Dipakai oleh : RecipientController@store, @update.
 * Status       : Aktif.
 */
class RecipientRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100', new UniqueName('m_recipient', $this->route('recipient'))],
            'department_id' => ['nullable', Rule::exists('m_department', 'id')->where('is_active', true)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => Text::of($this->input('name'))]);
    }
}
