<?php

namespace App\Support;

use App\Models\User;
use App\Models\Vehicle;
use App\Services\AcCare;
use App\Services\VehicleCare;
use Carbon\CarbonInterface;

/* REMARK
 * Tujuan       : Data banner Beranda (desktop dan HP): sapaan menurut jam, keterangan peran / Grup / perusahaan, empat angka ringkasan (hanya yang boleh dilihat pengguna), tanggal, dan tempat cuaca.
 * Sumber hulu  : User (peran, Grup, hak modul), Company (nama perusahaan, tempat cuaca), VehicleCare::overview (GVM), AcCare::scheduledBetween (GAS); angka pesan ulang dan menunggu persetujuan dihitung Beranda lalu diberikan.
 * Output       : array untuk partial home-hero.
 * Dipakai oleh : HomeController, HpController::home.
 * Status       : Aktif.
 */
final class HomeHero
{
    /** Sapaan menurut jam (pagi < 11, siang < 15, sore < 19, selain itu malam); sama dengan skrip jam di partial home-hero. */
    public static function greeting(int $hour): string
    {
        return 'Selamat '.($hour < 11 ? 'pagi' : ($hour < 15 ? 'siang' : ($hour < 19 ? 'sore' : 'malam')));
    }

    /**
     * @param  int|null  $below  jumlah barang di / bawah titik pesan ulang; null = tidak ditampilkan
     * @param  int|null  $pending  dokumen menunggu persetujuan; null = pengguna tidak berhak
     */
    public static function build(User $user, ?int $below, ?int $pending, ?CarbonInterface $now = null): array
    {
        $now = ($now ?? now())->copy()->timezone(config('devlib.timezone'));
        $name = strtok(trim((string) $user->name), ' ') ?: $user->username;
        $weather = Company::weather();

        $stats = array_values(array_filter([
            $below !== null ? ['label' => 'Di titik pesan ulang', 'value' => $below, 'alert' => $below > 0] : null,
            $pending !== null ? ['label' => 'Menunggu persetujuan', 'value' => $pending, 'alert' => $pending > 0] : null,
            $user->canAccess('GVM') ? ['label' => 'Kendaraan perlu tindakan', 'value' => self::vehiclesNeedingAction(), 'alert' => true] : null,
            $user->canAccess('GAS') ? ['label' => 'AC terjadwal minggu ini', 'value' => AcCare::scheduledBetween(today()->startOfWeek(), today()->startOfWeek()->addDays(6)), 'alert' => false] : null,
        ]));
        foreach ($stats as &$s) {
            $s['alert'] = $s['alert'] && $s['value'] > 0;
        }
        unset($s);

        return [
            'greeting' => self::greeting($now->hour),
            'name' => $name,
            'line' => array_values(array_unique(array_filter([$user->roleLabel(), $user->group?->name, Company::get()['name']]))),
            'stats' => $stats,
            'time' => $now->format('H:i:s'),
            'date' => $now->locale('id')->translatedFormat('l, j F Y'),
            'tz' => config('devlib.timezone'),
            'tzLabel' => config('devlib.timezone_label', 'WIB'),
            'now' => $now->getTimestampMs(),
            'weather' => $weather,
        ];
    }

    private static function vehiclesNeedingAction(): int
    {
        $over = VehicleCare::overview(Vehicle::query()->where('is_active', true)->get());

        return collect($over)->whereIn('status', ['segera', 'terlambat'])->count();
    }
}
