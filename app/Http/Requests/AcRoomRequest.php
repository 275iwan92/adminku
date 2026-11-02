<?php

namespace App\Http\Requests;

use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi simpan master Ruang AC: area (aktif), kode ruang (kosong = otomatis dari nama), nama, persegi di denah (persen, harus muat di dalam denah) dan PIC (nama, nomor WhatsApp, e-mail).
 * Dipakai oleh : AcRoomController@store, @update.
 * Status       : Aktif.
 */
class AcRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;   // hak diatur modul GAR (Grup), dijaga EnsureModuleAccess
    }

    public function rules(): array
    {
        if (in_array($this->input('_action'), ['activate', 'deactivate'], true)) {
            return [];
        }
        $own = $this->route('room');
        $fit = fn (string $other) => fn ($attr, $value, $fail) => (float) $value + (float) $this->input($other) > 100.001 ? $fail('Persegi ruang keluar dari denah (posisi + ukuran lebih dari 100 persen).') : null;

        return [
            'area_id' => ['required', Rule::exists('m_ac_area', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $own?->area_id))],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('m_ac_room', 'code')->ignore($own)],
            'name' => ['required', 'string', 'max:100'],
            'x' => ['required', 'numeric', 'min:0', 'max:100', $fit('w')],
            'y' => ['required', 'numeric', 'min:0', 'max:100', $fit('h')],
            'w' => ['required', 'numeric', 'min:1', 'max:100'],
            'h' => ['required', 'numeric', 'min:1', 'max:100'],
            'pic_name' => ['nullable', 'string', 'max:100'],
            'pic_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\s().-]{8,30}$/'],
            'pic_email' => ['nullable', 'email', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return ['pic_phone.regex' => 'Nomor WhatsApp hanya berisi angka, spasi, +, -, titik, dan kurung (minimal 8 karakter).'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'area_id' => Text::of($this->input('area_id')), 'code' => strtoupper(Text::of($this->input('code'))) ?: null, 'name' => Text::of($this->input('name')),
            'x' => Text::of($this->input('x')), 'y' => Text::of($this->input('y')), 'w' => Text::of($this->input('w')), 'h' => Text::of($this->input('h')),
            'pic_name' => Text::of($this->input('pic_name')) ?: null, 'pic_phone' => Text::of($this->input('pic_phone')) ?: null, 'pic_email' => Text::of($this->input('pic_email')) ?: null,
        ]);
    }
}
