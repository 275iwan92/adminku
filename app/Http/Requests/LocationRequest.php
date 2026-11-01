<?php

namespace App\Http\Requests;

use App\Rules\UniqueName;
use App\Support\Text;
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
            'code' => ['required', 'string', 'max:50', 'regex:/^\S+$/', Rule::unique('m_location', 'code')->ignore($this->route('location'))],
            'name' => ['required', 'string', 'max:100', new UniqueName('m_location', $this->route('location'))],
        ];
    }

    public function messages(): array
    {
        return ['code.regex' => 'Kode lokasi tidak boleh berisi spasi (pakai strip, mis. A1-01).'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(Text::of($this->input('code'))), 'name' => Text::of($this->input('name'))]);
    }
}
