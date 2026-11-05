<?php

namespace App\Http\Requests;

use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi Sumber Dana: nama wajib, unik. Hapus tidak membawa isian.
 * Dipakai oleh : CashSourceController@store, @update.
 * Status       : Aktif.
 */
class CashSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        if (in_array($this->input('_action'), ['delete', 'activate', 'deactivate'], true)) {
            return [];
        }

        return ['name' => ['required', 'string', 'max:150', Rule::unique('m_cash_source', 'name')->ignore($this->route('source'))]];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => Text::of($this->input('name'))]);
    }
}
