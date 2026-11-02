<?php

namespace App\Http\Requests;

use App\Models\Supplier;
use App\Rules\UniqueName;
use App\Support\GeoArea;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi simpan master Supplier (tambah / ubah): kode, nama, telepon kantor (PSTN), kontak + HP, alamat jalan, wilayah (wajib minimal sampai kota hanya bila data wilayah ada; kosong = opsional; kode pos ikut dari wilayah).
 * Sumber hulu  : field "area" = "LEVEL:id" dari lookup geo_area; dicek ada di tabel geo_*. validated() mengembalikan area_level + area_id (bukan area).
 * Dipakai oleh : SupplierController@store, @update.
 * Status       : Aktif.
 */
class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        if ($this->expectsJson()) {   // tambah cepat dari dropdown: kode otomatis, alamat / wilayah tidak ditanyakan
            return [
                'name' => ['required', 'string', 'max:150', new UniqueName('m_supplier')],
                'phone' => ['nullable', 'string', 'max:40'],
                'contact_name' => ['nullable', 'string', 'max:100'],
            ];
        }

        $system = $this->route('supplier')?->is_system ? $this->route('supplier') : null;
        $locked = fn (string $field) => fn ($attr, $value, $fail) => $system && $value !== $system->{$field} ? $fail(Supplier::SYSTEM_MESSAGE) : null;

        if (in_array($this->input('_action'), ['activate', 'deactivate'], true)) {
            return $system ? ['_action' => [fn ($attr, $value, $fail) => $fail(Supplier::SYSTEM_MESSAGE)]] : [];
        }

        return [
            'code' => [$this->route('supplier') ? 'required' : 'nullable', 'string', 'max:20', Rule::unique('m_supplier', 'code')->ignore($this->route('supplier')), $locked('code')],
            'name' => ['required', 'string', 'max:150', new UniqueName('m_supplier', $this->route('supplier')), $locked('name')],
            'phone' => ['nullable', 'string', 'max:40'],
            'contact_name' => ['nullable', 'string', 'max:100'],
            'contact_mobile' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365', fn ($attr, $value, $fail) => $system && (int) $value !== (int) $system->payment_term_days ? $fail(Supplier::SYSTEM_MESSAGE) : null],
            'note' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'area' => [GeoArea::available() ? 'required' : 'nullable', 'string', fn ($attr, $value, $fail) => GeoArea::find($value) ? null : $fail('Pilih wilayah dari daftar (minimal sampai kota).')],
        ];
    }

    public function messages(): array
    {
        return ['area.required' => 'Wilayah wajib diisi: cari dan pilih minimal sampai kota.'];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated();
        if (isset($data['area'])) {
            [$data['area_level'], $data['area_id']] = explode(':', $data['area']);
            $data['postal_code'] = GeoArea::find($data['area'])['postal_code'] ?? null;  // kode pos selalu dari wilayah terpilih
            unset($data['area']);
        }

        return $key ? data_get($data, $key, $default) : $data;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(Text::of($this->input('code'))), 'name' => Text::of($this->input('name'))]);
    }
}
