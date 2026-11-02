<?php

namespace App\Http\Requests;

use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;

/* REMARK
 * Tujuan       : Validasi aksi pada satu kunjungan AC: Tandai selesai (tanggal), Jadwal ulang (tanggal, jam opsional, alasan), Batalkan (alasan) dan unggah foto bukti (jenis sebelum / sesudah, jpg / png, maks 5 MB).
 *                Urutan tahap, hari / jam / kapasitas ditegakkan AcVisits / AcCare.
 * Dipakai oleh : AcVisitController@complete, @reschedule, @cancel, @photo.
 * Status       : Aktif.
 */
class AcVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;   // hak tulis modul GAV dijaga EnsureModuleAccess
    }

    public function rules(): array
    {
        return match ($this->route()->getName()) {
            'ac-visits.complete' => ['done_at' => ['required', 'date_format:Y-m-d']],
            'ac-visits.reschedule' => ['date' => ['required', 'date_format:Y-m-d'], 'time' => ['nullable', 'date_format:H:i'], 'reason' => ['required', 'string', 'min:3', 'max:255']],
            'ac-visits.cancel' => ['reason' => ['required', 'string', 'min:3', 'max:255']],
            'ac-visits.photo' => ['line_id' => ['required', 'integer'], 'kind' => ['required', 'in:before,after'], 'photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120']],
            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'done_at.required' => 'Isi tanggal selesai.', 'done_at.date_format' => 'Tanggal selesai tidak valid.',
            'date.required' => 'Isi tanggal baru.', 'date.date_format' => 'Tanggal baru tidak valid.', 'time.date_format' => 'Jam baru tidak valid.',
            'reason.required' => 'Alasan wajib diisi.', 'reason.min' => 'Alasan terlalu pendek (minimal 3 huruf).', 'reason.max' => 'Alasan paling panjang 255 huruf.',
            'line_id.required' => 'Pilih AC yang difoto.', 'kind.required' => 'Pilih foto sebelum atau sesudah.', 'kind.in' => 'Pilih foto sebelum atau sesudah.',
            'photo.required' => 'Pilih foto yang akan diunggah.', 'photo.mimes' => 'Foto harus berupa JPG atau PNG.', 'photo.max' => 'Foto paling besar 5 MB.', 'photo.uploaded' => 'Foto gagal diunggah (kemungkinan lebih dari 5 MB).',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'done_at' => Text::of($this->input('done_at')), 'date' => Text::of($this->input('date')), 'time' => Text::of($this->input('time')) ?: null,
            'reason' => Text::of($this->input('reason')), 'line_id' => Text::of($this->input('line_id')), 'kind' => Text::of($this->input('kind')),
        ]);
    }
}
