<?php

namespace App\Http\Requests;

use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi form Catat Odometer (cepat): kendaraan aktif, tanggal (tidak di masa depan), kilometer, dicatat oleh, foto bukti opsional (jpg, png, pdf, maks 5 MB).
 *                Kilometer tidak boleh lebih kecil dari catatan terakhir (dicek terkunci di VehicleCare::recordOdometer). Tanpa OCR: foto hanya bukti.
 * Dipakai oleh : VehicleOdometerController@store.
 * Status       : Aktif.
 */
class VehicleOdometerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;   // hak tulis modul GVO dijaga EnsureModuleAccess
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', Rule::exists('m_vehicle', 'id')->where('is_active', true)],
            'read_at' => ['required', 'date', 'before_or_equal:today'],
            'km' => ['required', 'integer', 'min:0', 'max:9999999'],
            'recorded_by' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'read_at.before_or_equal' => 'Tanggal pembacaan tidak boleh setelah hari ini.',
            'photo.mimes' => 'Foto harus berupa JPG / PNG atau berkas PDF.',
            'photo.max' => 'Foto paling besar 5 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['vehicle_id' => Text::of($this->input('vehicle_id')), 'read_at' => Text::of($this->input('read_at')), 'km' => Text::of($this->input('km')), 'recorded_by' => Text::of($this->input('recorded_by')) ?: $this->user()?->name]);
    }
}
