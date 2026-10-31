<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi simpan master Lokasi Penyimpanan (kode unik, nama); hanya Supervisor.
 * Dipakai oleh : LocationController@store, @update.
 * Status       : Aktif.
 */
class LocationRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:50', Rule::unique('m_location', 'code')->ignore($this->route('location'))],
            'name' => ['required', 'string', 'max:100', ...($this->expectsJson() ? [Rule::unique('m_location', 'name')] : [])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
    }
}
