<?php

namespace App\Http\Requests;

use App\Models\Candidate;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi simpan Kandidat rekrutmen: NIK KTP wajib 16 angka, nama, minimal satu bagian dilamar, tahap sekarang, status, riwayat tahap (jadwal, penilai, hasil), foto KTP dan CV (gambar / PDF).
 * Dipakai oleh : CandidateController@store, @update.
 * Status       : Aktif.
 */
class CandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $keys = fn (array $a) => Rule::in(array_keys($a));

        return [
            'nik' => ['required', 'digits:16'],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'education' => ['nullable', $keys(Candidate::EDUCATIONS)],
            'position' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', $keys(Candidate::SOURCES)],
            'applied_at' => ['required', 'date'],
            'step_id' => ['nullable', Rule::exists('m_recruit_step', 'id')->where('is_active', true)],
            'status' => ['nullable', $keys(Candidate::STATUSES)],
            'departments' => ['required', 'array', 'min:1'],
            'departments.*' => [Rule::exists('m_department', 'id')],
            'note' => ['nullable', 'string', 'max:255'],
            'ktp' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'cv' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx', 'max:5120'],
            'events' => ['nullable', 'array'],
            'events.*.step_id' => ['required', Rule::exists('m_recruit_step', 'id')],
            'events.*.scheduled_on' => ['nullable', 'date'],
            'events.*.scheduled_time' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'events.*.assessor' => ['nullable', 'string', 'max:100'],
            'events.*.result' => ['required', $keys(Candidate::RESULTS)],
            'events.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'nik.required' => 'NIK KTP wajib diisi.',
            'nik.digits' => 'NIK KTP harus 16 angka.',
            'departments.required' => 'Pilih minimal satu bagian yang dilamar.',
            'departments.min' => 'Pilih minimal satu bagian yang dilamar.',
            'events.*.scheduled_time.regex' => 'Jam ditulis HH:MM, mis. 09:30.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nik' => preg_replace('/\D/', '', Text::of($this->input('nik'))),
            'name' => Text::of($this->input('name')),
        ]);
    }
}
