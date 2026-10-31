<?php

namespace App\Http\Requests;

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
            'name' => ['required', 'string', 'max:100'],
            'department_id' => ['nullable', Rule::exists('m_department', 'id')->where('is_active', true)],
        ];
    }
}
