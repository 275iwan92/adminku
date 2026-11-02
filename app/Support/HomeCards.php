<?php

namespace App\Support;

use App\Enums\StockDocumentType;
use App\Models\AcUnit;
use App\Models\Batch;
use App\Models\Item;
use App\Models\StockDocument;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AcCare;
use App\Services\VehicleCare;
use Devlib\Locale;

/* REMARK
 * Tujuan       : Isi Beranda global (desktop dan HP) di bawah banner: satu kartu per modul (Stok, GA Kendaraan, GA AC) berisi tiga angka dan tautan ke layar pembuka modul itu. Kartu hanya untuk yang berhak modulnya (STB, GVM, GAM).
 *                Angka banner dan kartu dihitung sekali (kendaraan Segera + Terlambat, barang di titik pesan ulang) supaya jumlah kueri tetap kecil dan tidak ada angka yang berbeda di dua tempat.
 * Sumber hulu  : Item / Batch / StockDocument (Stok), VehicleCare::overview (Kendaraan), AcCare::overview dan scheduledBetween (AC), HomeHero (banner).
 * Output       : page() = ['hero' => ..., 'cards' => [kode, judul, ikon, nada, tautan, angka[]]].
 * Dipakai oleh : HomeController, HpController::home.
 * Status       : Aktif.
 */
final class HomeCards
{
    /** @return array{hero: array, cards: list<array>} */
    public static function page(User $user): array
    {
        $stock = $user->canAccess('STB');
        $below = $stock || $user->canAccess('ITM') ? Item::query()->belowReorderPoint()->withAvailable()->count() : null;
        $pending = $user->isSupervisor() && $user->canAccess('ADJ') ? StockDocument::query()->whereIn('status', ['approval', 'cancel_pending'])->count() : null;
        $fleet = $user->canAccess('GVM') ? self::fleet() : null;

        return [
            'hero' => HomeHero::build($user, $user->canAccess('ITM') ? $below : null, $pending, vehicles: $fleet ? $fleet['late'] + $fleet['soon'] : null),
            'cards' => array_values(array_filter([
                $stock ? self::stock($user, (int) $below) : null,
                $fleet ? self::vehicles($fleet) : null,
                $user->canAccess('GAM') ? self::ac() : null,
            ])),
        ];
    }

    /** "Rp 842,6 jt" / "Rp 1,2 M": ringkas supaya tiga angka muat satu baris kartu; nilai penuh ada di title. */
    public static function shortMoney(float $v): string
    {
        return match (true) {
            $v >= 1e9 => 'Rp '.Locale::number($v / 1e9, 1).' M',
            $v >= 1e6 => 'Rp '.Locale::number($v / 1e6, 1).' jt',
            default => 'Rp '.Locale::number($v, 0),
        };
    }

    private static function stock(User $user, int $below): array
    {
        if ($user->isSupervisor()) {
            $value = round((float) (Batch::query()->selectRaw('SUM(stock_qty * avg_cost) v')->value('v') ?? 0), 2);
            $third = ['value' => self::shortMoney($value), 'label' => 'Nilai stok', 'title' => 'Rp '.Locale::number($value, 0)];
        } else {
            $third = ['value' => Locale::number(StockDocument::query()->where('type', StockDocumentType::Receiving)->whereIn('status', ['confirmed', 'cancel_pending'])->whereDate('doc_at', today())->count(), 0), 'label' => 'Penerimaan hari ini'];
        }

        return self::card('STB', 'Stok', 'fa-solid fa-boxes-packing', 'teal', 'Beranda Stok', [
            ['value' => Locale::number(Item::query()->where('is_active', true)->count(), 0), 'label' => 'Barang aktif'],
            $third,
            ['value' => Locale::number($below, 0), 'label' => 'Pesan ulang', 'alert' => $below > 0],
        ]);
    }

    /** @return array{active: int, late: int, soon: int} */
    private static function fleet(): array
    {
        $vehicles = Vehicle::query()->where('is_active', true)->get();
        $by = collect(VehicleCare::overview($vehicles))->countBy('status');

        return ['active' => $vehicles->count(), 'late' => $by['terlambat'] ?? 0, 'soon' => $by['segera'] ?? 0];
    }

    private static function vehicles(array $f): array
    {
        return self::card('GVM', 'GA · Kendaraan', 'fa-solid fa-car-side', 'slate', 'Monitoring Kendaraan', [
            ['value' => Locale::number($f['active'], 0), 'label' => 'Aktif'],
            ['value' => Locale::number($f['late'], 0), 'label' => 'Terlambat', 'alert' => $f['late'] > 0],
            ['value' => Locale::number($f['soon'], 0), 'label' => 'Segera', 'alert' => $f['soon'] > 0],
        ]);
    }

    private static function ac(): array
    {
        $units = AcUnit::query()->where('is_active', true)->get();
        $late = collect(AcCare::overview($units))->where('status', 'terlambat')->count();
        $week = AcCare::scheduledBetween(today()->startOfWeek(), today()->startOfWeek()->addDays(6));

        return self::card('GAM', 'GA · AC', 'fa-solid fa-snowflake', 'coral', 'Perawatan AC', [
            ['value' => Locale::number($units->count(), 0), 'label' => 'AC'],
            ['value' => Locale::number($late, 0), 'label' => 'Terlambat', 'alert' => $late > 0],
            ['value' => Locale::number($week, 0), 'label' => 'Minggu ini'],
        ]);
    }

    private static function card(string $code, string $title, string $icon, string $tone, string $link, array $stats): array
    {
        return compact('code', 'title', 'icon', 'tone', 'link', 'stats');
    }
}
