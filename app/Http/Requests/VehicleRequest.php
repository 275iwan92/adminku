<?php

namespace App\Http\Requests;

use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi simpan master Kendaraan: plat (unik, = kode), nama / model, jenis (aktif), tahun, no. rangka / mesin, wajib KIR, odometer awal (hanya saat baru), catatan.
 * Dipakai oleh : VehicleController@store, @update.
 * Status       : Aktif.
 */
class VehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;   // hak diatur modul GVK (Grup), dijaga EnsureModuleAccess
    }

    public function rules(): array
    {
        if (in_array($this->input('_action'), ['activate', 'deactivate'], true)) {
            return [];
        }
        $own = $this->route('vehicle');

        return [
            'plate' => ['required', 'string', 'max:20', Rule::unique('m_vehicle', 'plate')->ignore($own)],
            'name' => ['required', 'string', 'max:100'],
            'vehicle_type_id' => ['required', Rule::exists('m_vehicle_type', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $own?->vehicle_type_id)),
                fn ($attr, $value, $fail) => $own && (int) $value !== $own->vehicle_type_id && $own->services()->exists() ? $fail('Jenis kendaraan tidak bisa diganti: kendaraan ini sudah punya riwayat servis (jadwalnya mengikuti jenis).') : null],
            'year' => ['nullable', 'integer', 'min:1980', 'max:'.(now()->year + 1)],
            'vin' => ['nullable', 'string', 'max:40'],
            'engine_no' => ['nullable', 'string', 'max:40'],
            'kir_required' => ['boolean'],
            'odometer_km' => [$own ? 'prohibited' : 'nullable', 'integer', 'min:0', 'max:9999999'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['odometer_km.prohibited' => 'Odometer diubah lewat Catat Odometer, bukan di master kendaraan.'];
    }

    protected function prepareForValidation(): void
    {
        $plate = strtoupper(preg_replace('/\s+/', ' ', Text::of($this->input('plate'))));
        $this->merge([
            'plate' => $plate, 'name' => Text::of($this->input('name')), 'kir_required' => $this->boolean('kir_required'),
            'vin' => Text::of($this->input('vin')) ?: null, 'engine_no' => Text::of($this->input('engine_no')) ?: null, 'note' => Text::of($this->input('note')) ?: null,
            'year' => Text::of($this->input('year')) ?: null,
        ] + ($this->route('vehicle') ? [] : ['odometer_km' => Text::of($this->input('odometer_km')) ?: 0]));
    }
}
