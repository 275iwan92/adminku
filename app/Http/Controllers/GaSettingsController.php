<?php

namespace App\Http\Controllers;

use App\Http\Requests\GaSettingsRequest;
use App\Services\AcCare;
use App\Services\VehicleCare;
use App\Support\ActivityLog;
use App\Support\Constants;
use Illuminate\Http\RedirectResponse;

/* REMARK
 * Tujuan       : Layar Pengaturan GA (modul GAP, GA > Pengaturan GA): satu formulir untuk ambang "Segera" kendaraan dan seluruh pengaturan Perawatan AC (interval, hari dan jam kunjungan, kapasitas, jam pesan PIC),
 *                tanpa mengubah basis data manual. Disimpan di sys_constant (grup "ga") dengan kunci dan format yang SAMA dengan yang dibaca AcCare / VehicleCare (hari = "1,2,3,4,5"). Nilai lama tidak diubah.
 *                Yang mengubah dan kapan: dicatat di ActivityLog (nilai lama > baru per kunci yang berubah).
 * Sumber hulu  : AcCare / VehicleCare (nilai berlaku sekarang), Constants.
 * Output       : view ga-settings.form; sys_constant ga.vehicle.* dan ga.ac.*.
 * Dipakai oleh : routes/web.php (ga-settings.edit, ga-settings.update).
 * Status       : Aktif.
 */
class GaSettingsController extends Controller
{
    // isian formulir => kunci sys_constant
    private const KEYS = [
        'vehicle_soon_days' => 'ga.vehicle.soon_days', 'vehicle_soon_km' => 'ga.vehicle.soon_km',
        'ac_wash_months' => 'ga.ac.wash_months', 'ac_major_months' => 'ga.ac.major_months', 'ac_days' => 'ga.ac.days', 'ac_start_time' => 'ga.ac.start_time',
        'ac_end_time' => 'ga.ac.end_time', 'ac_capacity' => 'ga.ac.capacity', 'ac_soon_days' => 'ga.ac.soon_days', 'ac_pic_notice_time' => 'ga.ac.pic_notice_time',
    ];

    // ?dari=fleet|ac: layar dibuka dari hub Setup Kendaraan atau AC, jadi Kembali ke hub itu (bila berhak); selain itu ke beranda.
    private function hub(): array
    {
        $hubs = ['fleet' => ['GVM', 'fleet.setup'], 'ac' => ['GAM', 'ac-setup.index']];
        $from = request()->query('dari');
        if (isset($hubs[$from]) && request()->user()->canAccess($hubs[$from][0])) {
            return [route($hubs[$from][1]), ['dari' => $from]];
        }

        return [route('home'), []];
    }

    public function edit()
    {
        [$back, $keep] = $this->hub();

        return view('ga-settings.form', ['back' => $back, 'keep' => $keep, 's' => [
            'vehicle_soon_days' => VehicleCare::soonDays(), 'vehicle_soon_km' => VehicleCare::soonKm(),
            'ac_wash_months' => AcCare::washMonths(), 'ac_major_months' => AcCare::majorMonths(), 'ac_days' => AcCare::visitDays(), 'ac_start_time' => AcCare::startTime(),
            'ac_end_time' => AcCare::endTime(), 'ac_capacity' => AcCare::capacity(), 'ac_soon_days' => AcCare::soonDays(), 'ac_pic_notice_time' => AcCare::picNoticeTime(),
        ], 'dayNames' => AcCare::DAYS]);
    }

    public function update(GaSettingsRequest $request): RedirectResponse
    {
        $new = $request->validated();
        $new['ac_days'] = implode(',', collect($new['ac_days'])->map(fn ($d) => (int) $d)->unique()->sort()->values()->all());

        $changes = [];
        foreach (self::KEYS as $field => $key) {
            $old = Constants::get($key);
            if ((string) $old !== (string) $new[$field]) {
                $changes[] = $key.' '.($old ?? '-').' > '.$new[$field];
                Constants::set($key, $new[$field], 'ga');
            }
        }
        if ($changes) {
            ActivityLog::write((string) $request->user()->username, (string) $request->ip(), 'Ubah pengaturan GA', implode('; ', $changes));
        }

        return redirect()->route('ga-settings.edit', $this->hub()[1])->with('status', $changes ? 'Pengaturan GA disimpan. Penjadwalan AC, Monitoring Kendaraan, dan pesan langsung memakai nilai baru; jadwal yang sudah dibuat tidak berubah.' : 'Tidak ada perubahan.');
    }
}
