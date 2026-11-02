<?php

namespace App\Http\Requests;

use App\Services\AcCare;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi layar Pengaturan GA (kendaraan dan perawatan AC): hari kunjungan minimal satu, jam HH:MM dengan jam akhir setelah jam mulai, kapasitas bulat 1..20, bulan bulat 1..60 dengan servis besar >= cuci rutin,
 *                ambang hari 1..365, ambang km 100..50.000 (boleh ditulis 1.000). Hanya Supervisor ke atas; hak modul GAP dijaga EnsureModuleAccess.
 * Dipakai oleh : GaSettingsController@update.
 * Status       : Aktif.
 */
class GaSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSupervisor();
    }

    public function rules(): array
    {
        return [
            'vehicle_soon_days' => ['required', 'integer', 'between:1,365'],
            'vehicle_soon_km' => ['required', 'integer', 'between:100,50000'],
            'ac_wash_months' => ['required', 'integer', 'between:1,60'],
            'ac_major_months' => ['required', 'integer', 'between:1,60', 'gte:ac_wash_months'],
            'ac_days' => ['required', 'array', 'min:1'],
            'ac_days.*' => ['integer', Rule::in(array_keys(AcCare::DAYS))],
            'ac_start_time' => ['required', 'date_format:H:i'],
            'ac_end_time' => ['required', 'date_format:H:i', 'after:ac_start_time'],
            'ac_capacity' => ['required', 'integer', 'between:1,20'],
            'ac_soon_days' => ['required', 'integer', 'between:1,365'],
            'ac_pic_notice_time' => ['required', 'date_format:H:i'],
        ];
    }

    // Angka boleh ditulis Indonesia (1.000); teks aneh / larik jadi kosong sehingga ditolak "wajib diisi", bukan galat 500.
    protected function prepareForValidation(): void
    {
        $in = [];
        foreach (array_keys($this->rules()) as $k) {
            if ($k === 'ac_days' || $k === 'ac_days.*') {
                continue;
            }
            $v = Text::of($this->input($k));
            $in[$k] = preg_match('/^\d{1,3}(\.\d{3})+$/', $v) ? str_replace('.', '', $v) : $v;
        }
        $days = $this->input('ac_days');
        $in['ac_days'] = is_array($days) ? array_values(array_filter(array_map([Text::class, 'of'], $days), 'strlen')) : [];
        $this->merge($in);
    }

    public function attributes(): array
    {
        return [
            'vehicle_soon_days' => 'ambang Segera kendaraan (hari)', 'vehicle_soon_km' => 'ambang Segera kendaraan (km)', 'ac_wash_months' => 'interval cuci rutin', 'ac_major_months' => 'interval servis besar',
            'ac_days' => 'hari kunjungan', 'ac_days.*' => 'hari kunjungan', 'ac_start_time' => 'jam mulai', 'ac_end_time' => 'jam akhir', 'ac_capacity' => 'kapasitas per hari', 'ac_soon_days' => 'ambang Segera AC (hari)',
            'ac_pic_notice_time' => 'jam pesan PIC',
        ];
    }

    public function messages(): array
    {
        return [
            'ac_days.required' => 'Pilih minimal satu hari kunjungan.', 'ac_days.min' => 'Pilih minimal satu hari kunjungan.',
            'ac_end_time.after' => 'Jam akhir harus setelah jam mulai.',
            'ac_major_months.gte' => 'Interval servis besar tidak boleh lebih pendek dari interval cuci rutin.',
            'ac_start_time.date_format' => 'Jam mulai harus berformat JJ:MM, mis. 09:00.', 'ac_end_time.date_format' => 'Jam akhir harus berformat JJ:MM, mis. 16:00.',
            'ac_pic_notice_time.date_format' => 'Jam pesan PIC harus berformat JJ:MM, mis. 15:00.',
        ];
    }
}
