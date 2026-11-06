<?php

namespace App\Support;

use App\Enums\StockDocumentType;
use App\Models\AcUnit;
use App\Models\Batch;
use App\Models\CashAccount;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\DeliveryTrip;
use App\Models\Item;
use App\Models\StockDocument;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AcCare;
use App\Services\VehicleCare;
use Devlib\Locale;

/* REMARK
 * Tujuan       : Isi Beranda global (desktop dan HP) di bawah banner: satu kartu per modul (Stok, Pengiriman, Keuangan, GA Kendaraan, GA AC) berisi tiga angka dan tautan ke layar pembuka modul itu. Kartu hanya untuk yang berhak modulnya (STB, GVM, GAM).
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
                $user->canAccess('PGK') ? self::delivery() : null,
                self::cash($user),
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
        $active = Item::query()->where('is_active', true)->count();
        if ($user->isSupervisor()) {
            $value = round((float) (Batch::query()->selectRaw('SUM(stock_qty * avg_cost) v')->value('v') ?? 0), 2);
            $third = ['value' => self::shortMoney($value), 'label' => 'Nilai stok', 'title' => 'Rp '.Locale::number($value, 0)];
        } else {
            $third = ['value' => Locale::number(StockDocument::query()->where('type', StockDocumentType::Receiving)->whereIn('status', ['confirmed', 'cancel_pending'])->whereDate('doc_at', today())->count(), 0), 'label' => 'Penerimaan hari ini'];
        }

        return self::card('STB', 'Stok', 'fa-solid fa-boxes-packing', 'teal', 'Beranda Stok', [
            ['value' => Locale::number($active, 0), 'label' => 'Barang aktif'],
            $third,
            ['value' => Locale::number($below, 0), 'label' => 'Pesan ulang', 'alert' => $below > 0],
        ], [[max(0, $active - $below), 'ok', 'Di atas titik pesan ulang'], [$below, 'bad', 'Di / di bawah titik pesan ulang']]);
    }

    /** @return array{active: int, late: int, soon: int} */
    public static function fleet(): array
    {
        $vehicles = Vehicle::query()->where('is_active', true)->get();
        $by = collect(VehicleCare::overview($vehicles))->countBy('status');

        return ['active' => $vehicles->count(), 'late' => $by['terlambat'] ?? 0, 'soon' => $by['segera'] ?? 0];
    }

    private static function vehicles(array $f): array
    {
        return self::card('GVM', 'GA · Kendaraan', 'fa-solid fa-car-side', 'slate', 'Perawatan Kendaraan', [
            ['value' => Locale::number($f['active'], 0), 'label' => 'Aktif'],
            ['value' => Locale::number($f['late'], 0), 'label' => 'Terlambat', 'alert' => $f['late'] > 0],
            ['value' => Locale::number($f['soon'], 0), 'label' => 'Segera', 'alert' => $f['soon'] > 0],
        ], [[max(0, $f['active'] - $f['late'] - $f['soon']), 'ok', 'Aman'], [$f['soon'], 'warn', 'Segera'], [$f['late'], 'bad', 'Terlambat']]);
    }

    /** @return array{units: int, late: int, week: int} */
    public static function acCounts(): array
    {
        $units = AcUnit::query()->where('is_active', true)->get();

        return [
            'units' => $units->count(),
            'late' => collect(AcCare::overview($units))->where('status', 'terlambat')->count(),
            'week' => AcCare::scheduledBetween(today()->startOfWeek(), today()->startOfWeek()->addDays(6)),
        ];
    }

    private static function ac(): array
    {
        $c = self::acCounts();

        return self::card('GAM', 'GA · AC', 'fa-solid fa-snowflake', 'coral', 'Perawatan AC', [
            ['value' => Locale::number($c['units'], 0), 'label' => 'AC'],
            ['value' => Locale::number($c['late'], 0), 'label' => 'Terlambat', 'alert' => $c['late'] > 0],
            ['value' => Locale::number($c['week'], 0), 'label' => 'Minggu ini'],
        ], [[max(0, $c['units'] - $c['late']), 'ok', 'Aman'], [$c['late'], 'bad', 'Terlambat']]);
    }

    // Pengiriman: trip Ditugaskan hari ini, yang sedang jalan (sudah ada alamat ditandai tiba tapi belum semua), dan draf yang belum ditugaskan (hari ini dan seterusnya).
    private static function delivery(): array
    {
        $n = DeliveryTrip::query()->selectRaw("sum(status = 'assigned' and date(trip_date) = ?) today, sum(status = 'draft' and date(trip_date) >= ?) draft", [today()->toDateString(), today()->toDateString()])->first();
        $running = DeliveryTrip::query()->where('status', 'assigned')->whereDate('trip_date', today())->whereHas('stops', fn ($q) => $q->whereNotNull('arrived_at'))->whereHas('stops', fn ($q) => $q->whereNull('arrived_at'))->count();
        $draft = (int) $n->draft;

        return self::card('PGK', 'Pengiriman', 'fa-solid fa-truck-fast', 'blue', 'Perencanaan Pengiriman', [
            ['value' => Locale::number((int) $n->today, 0), 'label' => 'Trip hari ini'],
            ['value' => Locale::number($running, 0), 'label' => 'Sedang jalan'],
            ['value' => Locale::number($draft, 0), 'label' => 'Belum ditugaskan', 'alert' => $draft > 0],
        ], [[$running, 'info', 'Sedang jalan'], [max(0, (int) $n->today - $running), 'ok', 'Belum / sudah selesai jalan'], [$draft, 'warn', 'Belum ditugaskan']]);
    }

    // Keuangan: saldo semua kas aktif (saldo awal + masuk - keluar, hanya Disetujui), keluar bulan ini (koral bila melebihi masuk), dan dokumen menunggu persetujuan.
    private static function cash(User $user): ?array
    {
        $open = collect(['KSM', 'KSK', 'KSR', 'R-KFLOW'])->first(fn ($c) => $user->canAccess($c));
        if (! $user->canAccess('KSM') && ! $user->canAccess('KSK')) {
            return null;
        }
        $accounts = CashAccount::query()->where('is_active', true)->get(['id', 'opening_balance']);
        $ids = $accounts->pluck('id');
        $sums = fn ($m) => $m::query()->where('status', 'approved')->whereIn('cash_account_id', $ids)->selectRaw('coalesce(sum(amount), 0) total, coalesce(sum(case when doc_date >= ? then amount end), 0) month', [now()->startOfMonth()->toDateString()])->first();
        [$ins, $outs] = [$sums(CashIn::class), $sums(CashOut::class)];
        $balance = (float) $accounts->sum('opening_balance') + (float) $ins->total - (float) $outs->total;
        [$in, $out] = [(float) $ins->month, (float) $outs->month];
        $wait = ($user->canAccess('KSM') ? CashIn::query()->where('status', 'approval')->count() : 0) + ($user->canAccess('KSK') ? CashOut::query()->where('status', 'approval')->count() : 0);

        return self::card($open, 'Keuangan', 'fa-solid fa-building-columns', 'gold', 'Uang Masuk & Keluar', [
            ['value' => self::shortMoney($balance), 'label' => 'Saldo kas', 'title' => 'Rp '.Locale::number($balance, 0)],
            ['value' => self::shortMoney($out), 'label' => 'Keluar bulan ini', 'alert' => $out > $in, 'title' => 'Masuk Rp '.Locale::number($in, 0).' · Keluar Rp '.Locale::number($out, 0)],
            ['value' => Locale::number($wait, 0), 'label' => 'Menunggu', 'alert' => $wait > 0],
        ], [[$in, 'in', 'Masuk bulan ini'], [$out, 'out', 'Keluar bulan ini']]);
    }

    /** $bar = batang proporsi tipis di bawah angka: [[nilai, kelas warna (ok|warn|bad|in|out|info), keterangan], ...]; nilai 0 = segmen tak digambar. */
    private static function card(string $code, string $title, string $icon, string $tone, string $link, array $stats, array $bar = []): array
    {
        return compact('code', 'title', 'icon', 'tone', 'link', 'stats', 'bar');
    }
}
