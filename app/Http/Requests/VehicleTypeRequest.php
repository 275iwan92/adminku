<?php

namespace App\Http\Requests;

use App\Rules\UniqueName;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi simpan master Jenis Kendaraan beserta rencana perawatannya: kode unik, nama unik, dan tiap butir rencana punya nama (tidak ganda) serta interval km dan/atau bulan.
 * Dipakai oleh : VehicleTypeController@store, @update.
 * Status       : Aktif.
 */
class VehicleTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;   // hak diatur modul GVT (Grup), dijaga EnsureModuleAccess
    }

    public function rules(): array
    {
        if (in_array($this->input('_action'), ['activate', 'deactivate'], true)) {
            return [];
        }

        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9._-]+$/', Rule::unique('m_vehicle_type', 'code')->ignore($this->route('type'))],
            'name' => ['required', 'string', 'max:100', new UniqueName('m_vehicle_type', $this->route('type'))],
            'plans' => ['nullable', 'array', 'max:40'],
            'plans.*.name' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'plans.*.interval_km' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'plans.*.interval_months' => ['nullable', 'integer', 'min:1', 'max:240'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {   // tiap butir wajib punya sedikitnya satu interval
            foreach ((array) $this->input('plans', []) as $i => $row) {
                if (is_array($row) && blank($row['interval_km'] ?? null) && blank($row['interval_months'] ?? null)) {
                    $validator->errors()->add("plans.$i.interval_km", 'Isi interval km atau interval bulan (atau keduanya) di setiap butir rencana.');
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Kode hanya boleh huruf besar, angka, dan . _ - (mis. PICKUP).',
            'plans.*.name.required' => 'Isi nama pekerjaan di setiap butir rencana.',
            'plans.*.name.distinct' => 'Nama pekerjaan yang sama muncul lebih dari sekali.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $plans = collect((array) $this->input('plans', []))->filter(fn ($r) => is_array($r))->map(fn ($r) => [
            'name' => Text::of($r['name'] ?? null),
            'interval_km' => Text::of($r['interval_km'] ?? null) ?: null,
            'interval_months' => Text::of($r['interval_months'] ?? null) ?: null,
        ])->filter(fn ($r) => $r['name'] !== '' || $r['interval_km'] || $r['interval_months'])->values()->all();   // baris kosong sama sekali diabaikan
        $this->merge(['code' => $this->route('type')?->code ?? strtoupper(Text::of($this->input('code'))), 'name' => Text::of($this->input('name')), 'plans' => $plans]);
    }
}
