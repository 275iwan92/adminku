<?php

namespace App\Http\Requests;

use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;

/* REMARK
 * Tujuan       : Validasi simpan master Kas / Rekening: nama, jenis (Tunai/Bank), no. rekening wajib bila Bank. Aktif / nonaktif tidak membawa isian.
 * Dipakai oleh : CashAccountController@store, @update.
 * Status       : Aktif.
 */
class CashAccountRequest extends FormRequest
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
            'type' => ['required', 'in:cash,bank'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'account_no' => ['nullable', 'string', 'max:30', 'required_if:type,bank'],
            'account_holder' => ['nullable', 'string', 'max:100'],
            'opening_balance' => ['required', 'numeric', 'min:0', 'max:999999999999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => Text::of($this->input('name'))]);
    }
}
