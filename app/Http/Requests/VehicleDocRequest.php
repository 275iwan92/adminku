<?php

namespace App\Http\Requests;

use App\Services\VehicleCare;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi form Perpanjang Dokumen Kendaraan: kendaraan aktif, jenis dokumen, tanggal bayar / uji (tidak di masa depan), berlaku sampai (setelah tanggal bayar, paling jauh 10 tahun dari hari ini),
 *                no. dokumen, biaya (maks 2 desimal), lampiran foto / PDF (jpg, png, pdf, maks 5 MB), catatan. KIR untuk kendaraan tidak wajib KIR ditolak oleh VehicleCare::recordDoc.
 * Dipakai oleh : VehicleDocController@store.
 * Status       : Aktif.
 */
class VehicleDocRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;   // hak tulis modul GVD dijaga EnsureModuleAccess
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', Rule::exists('m_vehicle', 'id')->where('is_active', true)],
            'doc_type' => ['required', Rule::in(array_keys(VehicleCare::DOCS))],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'valid_until' => ['required', 'date', 'bail', 'after:paid_at', 'before_or_equal:'.today()->addYears(10)->toDateString()],
            'doc_no' => ['nullable', 'string', 'max:60'],
            'cost' => ['required', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,2'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'paid_at.before_or_equal' => 'Tanggal bayar / uji tidak boleh setelah hari ini.',
            'valid_until.after' => 'Berlaku sampai harus setelah tanggal bayar / uji.',
            'valid_until.before_or_equal' => 'Berlaku sampai terlalu jauh (paling lama 10 tahun dari hari ini).',
            'attachment.mimes' => 'Lampiran harus berupa foto JPG / PNG atau berkas PDF.',
            'attachment.max' => 'Lampiran paling besar 5 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'vehicle_id' => Text::of($this->input('vehicle_id')), 'doc_type' => Text::of($this->input('doc_type')), 'paid_at' => Text::of($this->input('paid_at')), 'valid_until' => Text::of($this->input('valid_until')),
            'doc_no' => Text::of($this->input('doc_no')) ?: null, 'note' => Text::of($this->input('note')) ?: null,
            'cost' => Text::of($this->input('cost')) === '' ? 0 : Text::of($this->input('cost')),
        ]);
    }
}
