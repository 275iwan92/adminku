<?php

namespace App\Http\Requests;

use App\Models\AcOrder;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi layar Penjadwalan AC: Jadwalkan yang dipilih (vendor aktif, tanggal Y-m-d, jam opsional, minimal satu AC), Susun otomatis (vendor, tanggal mulai) dan pemilih kapasitas (1 sampai 10).
 *                Aturan hari / jam / kapasitas / satu AC satu pesanan terbuka ditegakkan AcCare (server), bukan di sini.
 * Dipakai oleh : AcScheduleController@store, @auto, @capacity.
 * Status       : Aktif.
 */
class AcScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;   // hak tulis modul GAS dijaga EnsureModuleAccess
    }

    public function rules(): array
    {
        $vendor = ['required', Rule::exists('m_ac_vendor', 'id')->where('is_active', true)];

        return match ($this->route()->getName()) {
            'ac-schedule.store' => ['vendor_id' => $vendor, 'date' => ['required', 'date_format:Y-m-d'], 'time' => ['nullable', 'date_format:H:i'], 'units' => ['required', 'array', 'min:1', 'max:50'], 'units.*' => ['integer'],
                'type' => ['required', Rule::in(array_keys(AcOrder::TYPES))], 'complaint' => ['nullable', 'string', 'max:255'], 'priority' => ['nullable', Rule::in(array_keys(AcOrder::PRIORITIES))], 'override_reason' => ['nullable', 'string', 'max:255']],
            'ac-schedule.auto' => ['vendor_id' => $vendor, 'date' => ['nullable', 'date_format:Y-m-d']],
            default => ['capacity' => ['required', 'integer', 'min:1', 'max:10']],
        };
    }

    public function messages(): array
    {
        return [
            'units.required' => 'Pilih minimal satu AC yang akan dijadwalkan.', 'units.min' => 'Pilih minimal satu AC yang akan dijadwalkan.', 'units.max' => 'Paling banyak 50 AC sekali jadwal.',
            'date.required' => 'Pilih tanggal kunjungan di kalender.', 'date.date_format' => 'Tanggal kunjungan tidak valid.', 'time.date_format' => 'Jam kunjungan tidak valid.',
            'vendor_id.required' => 'Pilih vendor.', 'vendor_id.exists' => 'Vendor tidak ditemukan atau tidak aktif.',
            'complaint.max' => 'Keluhan paling panjang 255 huruf.', 'override_reason.max' => 'Alasan paling panjang 255 huruf.', 'type.in' => 'Tipe jadwal tidak dikenal.',
            'capacity.min' => 'Kapasitas harian paling sedikit 1 AC.', 'capacity.max' => 'Kapasitas harian paling banyak 10 AC.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $units = $this->input('units');
        $this->merge([
            'vendor_id' => Text::of($this->input('vendor_id')), 'date' => Text::of($this->input('date')) ?: null, 'time' => Text::of($this->input('time')) ?: null, 'type' => Text::of($this->input('type')) ?: 'preventive', 'complaint' => Text::of($this->input('complaint')) ?: null, 'priority' => Text::of($this->input('priority')) ?: null, 'override_reason' => Text::of($this->input('override_reason')) ?: null, 'capacity' => Text::of($this->input('capacity')),
            'units' => is_array($units) ? array_values(array_filter($units, 'is_scalar')) : [],
        ]);
    }
}
