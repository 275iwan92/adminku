<?php

namespace App\Http\Requests;

use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;

/* REMARK
 * Tujuan       : Validasi aksi pada satu kunjungan AC: Tandai selesai (tanggal), Catat hasil per AC (tanggal, kondisi, penyebab, tindakan, biaya jasa, spare-part), Jadwal ulang (tanggal, jam opsional, alasan), Batalkan (alasan) dan unggah foto bukti (jenis sebelum / sesudah, jpg / png, maks 5 MB).
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
            'ac-visits.result' => [
                'line_id' => ['required', 'integer'], 'done_at' => ['required', 'date_format:Y-m-d'], 'findings' => ['nullable', 'string', 'max:255'], 'cause' => ['nullable', 'string', 'max:255'], 'action' => ['nullable', 'string', 'max:255'],
                'labor_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'], 'parts' => ['nullable', 'array', 'max:10'], 'parts.*.name' => ['nullable', 'string', 'max:100'],
                'parts.*.qty' => ['nullable', 'numeric', 'min:0.01', 'max:99999'], 'parts.*.cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            ],
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
            'findings.max' => 'Kondisi paling panjang 255 huruf.', 'cause.max' => 'Penyebab paling panjang 255 huruf.', 'action.max' => 'Tindakan paling panjang 255 huruf.',
            'labor_cost.numeric' => 'Biaya jasa harus berupa angka.', 'parts.*.name.max' => 'Nama spare-part paling panjang 100 huruf.', 'parts.*.qty.numeric' => 'Jumlah spare-part harus berupa angka.', 'parts.*.qty.min' => 'Jumlah spare-part minimal 0,01.', 'parts.*.cost.numeric' => 'Biaya spare-part harus berupa angka.',
            'photo.required' => 'Pilih foto yang akan diunggah.', 'photo.mimes' => 'Foto harus berupa JPG atau PNG.', 'photo.max' => 'Foto paling besar 5 MB.', 'photo.uploaded' => 'Foto gagal diunggah (kemungkinan lebih dari 5 MB).',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'done_at' => Text::of($this->input('done_at')), 'date' => Text::of($this->input('date')), 'time' => Text::of($this->input('time')) ?: null,
            'reason' => Text::of($this->input('reason')), 'findings' => Text::of($this->input('findings')) ?: null, 'cause' => Text::of($this->input('cause')) ?: null, 'action' => Text::of($this->input('action')) ?: null, 'labor_cost' => Text::of($this->input('labor_cost')) ?: null,
            'parts' => collect(is_array($this->input('parts')) ? $this->input('parts') : [])->filter(fn ($r) => is_array($r))->map(fn ($r) => ['name' => Text::of($r['name'] ?? null), 'qty' => Text::of($r['qty'] ?? null) ?: null, 'cost' => Text::of($r['cost'] ?? null) ?: null])->values()->all(), 'line_id' => Text::of($this->input('line_id')), 'kind' => Text::of($this->input('kind')),
        ]);
    }
}
