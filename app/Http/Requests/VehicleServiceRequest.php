<?php

namespace App\Http\Requests;

use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi form Catat Servis: kendaraan aktif, tanggal (tidak di masa depan), odometer, pekerjaan yang dikerjakan (dicentang dari jadwal jenis kendaraan itu; dicocokkan lagi di VehicleCare::recordService),
 *                bengkel, biaya total (maks 2 desimal), catatan. Tanpa satu pun pekerjaan dicentang, catatan wajib (servis di luar jadwal). Odometer tidak boleh lebih kecil dari catatan terakhir (dicek terkunci di VehicleCare).
 * Dipakai oleh : VehicleServiceController@store.
 * Status       : Aktif.
 */
class VehicleServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;   // hak tulis modul GVS dijaga EnsureModuleAccess
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', Rule::exists('m_vehicle', 'id')->where('is_active', true)],
            'serviced_at' => ['required', 'date', 'before_or_equal:today'],
            'odometer_km' => ['required', 'integer', 'min:0', 'max:9999999'],
            'jobs' => ['nullable', 'array', 'max:40'],
            'jobs.*' => ['integer'],
            'workshop' => ['nullable', 'string', 'max:100'],
            'cost' => ['required', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,2'],
            'note' => ['nullable', 'string', 'max:255', 'required_without:jobs'],
        ];
    }

    public function messages(): array
    {
        return [
            'serviced_at.before_or_equal' => 'Tanggal servis tidak boleh setelah hari ini.',
            'note.required_without' => 'Centang pekerjaan yang dikerjakan, atau isi catatan bila servisnya di luar jadwal.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'vehicle_id' => Text::of($this->input('vehicle_id')), 'serviced_at' => Text::of($this->input('serviced_at')), 'odometer_km' => Text::of($this->input('odometer_km')),
            'workshop' => Text::of($this->input('workshop')) ?: null, 'note' => Text::of($this->input('note')) ?: null,
            'cost' => Text::of($this->input('cost')) === '' ? 0 : Text::of($this->input('cost')),
            'jobs' => array_values(array_filter((array) $this->input('jobs', []), fn ($j) => $j !== null && $j !== '')),
        ]);
    }
}
