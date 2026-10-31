<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi simpan master Barang. Barang baru boleh dibuat semua pengguna (harga dan parameter pesan ulang boleh kosong = 0, Supervisor melengkapi); mengubah barang hanya Supervisor.
 * Dipakai oleh : ProductController@store, @update.
 * Status       : Aktif.
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (! $this->route('product') || $user->isSupervisor());
    }

    public function rules(): array
    {
        // Aktifkan / nonaktifkan lewat tombol panel form tidak mengirim isian.
        if (in_array($this->input('_action'), ['activate', 'deactivate'], true)) {
            return [];
        }
        $new = ! $this->route('product');
        $sup = $this->user()->isSupervisor();
        $own = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:150'],
            // Kode otomatis; kategori dan tanda kedaluwarsa (bagian dari identitas barang) hanya diisi saat barang baru.
            ...($new ? [
                'category_id' => ['required', Rule::exists('m_category', 'id')->where('is_active', true)],
                'tracks_expiry' => ['nullable', 'boolean'],
            ] : []),
            // Satuan dan lokasi dari master yang aktif (nilai lama barang yang sudah nonaktif tetap boleh dipertahankan).
            'unit' => ['required', Rule::exists('m_unit', 'code')->where(fn ($q) => $q->where('is_active', true)->orWhere('code', $own?->unit))],
            'location' => ['nullable', Rule::exists('m_location', 'code')->where(fn ($q) => $q->where('is_active', true)->orWhere('code', $own?->location))],
            'unit_price' => [$sup ? 'required' : 'nullable', 'numeric', 'min:0', 'max:999999999999'],
            'reorder_point' => [$sup ? 'required' : 'nullable', 'numeric', 'min:0', 'max:999999999'],
            'reorder_qty' => [$sup ? 'required' : 'nullable', 'numeric', 'min:0', 'max:999999999'],
            'lead_time_days' => [$sup ? 'required' : 'nullable', 'integer', 'min:0', 'max:365'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['tracks_expiry' => $this->boolean('tracks_expiry')]);
    }

    // Isian harga / parameter yang tidak dikirim (Data Entry) tidak menimpa nilai yang ada.
    public function validated($key = null, $default = null)
    {
        $data = array_filter(parent::validated(), fn ($v) => $v !== null);
        $data += ['location' => null];

        return $key ? data_get($data, $key, $default) : $data;
    }
}
