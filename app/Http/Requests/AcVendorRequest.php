<?php

namespace App\Http\Requests;

use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi simpan master Vendor AC: kode (kosong = otomatis VND-nnn), nama, kontak, nomor WhatsApp dan e-mail (dipakai pemberitahuan kunjungan), catatan.
 * Dipakai oleh : AcVendorController@store, @update.
 * Status       : Aktif.
 */
class AcVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;   // hak diatur modul GAE (Grup), dijaga EnsureModuleAccess
    }

    public function rules(): array
    {
        if (in_array($this->input('_action'), ['activate', 'deactivate'], true)) {
            return [];
        }

        return [
            'code' => ['nullable', 'string', 'max:20', Rule::unique('m_ac_vendor', 'code')->ignore($this->route('vendor'))],
            'name' => ['required', 'string', 'max:100'],
            'contact_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\s().-]{8,30}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Nomor WhatsApp hanya berisi angka, spasi, +, -, titik, dan kurung (minimal 8 karakter).'];
    }

    protected function prepareForValidation(): void
    {
        $in = fn (string $k) => Text::of($this->input($k)) ?: null;
        $this->merge(['code' => strtoupper(Text::of($this->input('code'))) ?: null, 'name' => Text::of($this->input('name')), 'contact_name' => $in('contact_name'), 'phone' => $in('phone'), 'email' => $in('email'), 'note' => $in('note')]);
    }
}
