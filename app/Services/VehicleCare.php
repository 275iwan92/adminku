<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDoc;
use App\Models\VehicleOdometer;
use App\Models\VehicleService;
use App\Models\VehicleTypePlan;
use App\Support\Constants;
use App\Support\Decimal;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/* REMARK
 * Tujuan       : Semua hitungan dan pencatatan GA Perawatan Aset Kendaraan di satu tempat.
 *                Dokumen: masa berlaku sekarang = berlaku-sampai terbesar per jenis (pajak 12 bln, STNK + plat 60 bln, KIR 6 bln hanya bila wajib KIR, asuransi 12 bln).
 *                Jadwal servis: tiap butir rencana jenis kendaraan dihitung dari servis terakhir butir itu (odometer + interval km, tanggal + interval bulan; yang tercapai lebih dulu menentukan). Butir yang belum pernah diservis
 *                dihitung dari catatan odometer awal (saat kendaraan didaftarkan). Status: Terlambat (lewat tanggal / km), Segera (dalam ambang hari / km), selain itu Aman. Status kendaraan = yang terburuk.
 *                Pencatatan: perpanjang dokumen, catat servis, catat odometer, masing-masing satu transaksi; odometer tidak boleh turun (dikunci per kendaraan). Modul ini TIDAK menyentuh stok gudang.
 * Sumber hulu  : m_vehicle, m_vehicle_type_plan, g_vehicle_doc, g_vehicle_service(+_line), g_vehicle_odometer, Constants (ga.vehicle.soon_days = 30, ga.vehicle.soon_km = 1000), disk "ga".
 * Output       : overview() untuk layar Monitoring / Detail; recordDoc / recordService / recordOdometer / startOdometer.
 * Dipakai oleh : FleetController, VehicleDocController, VehicleServiceController, VehicleOdometerController, VehicleController, VehicleSeeder.
 * Status       : Aktif.
 */
final class VehicleCare
{
    /** @var array<string, array{0: string, 1: int}> jenis dokumen => [label, masa berlaku dalam bulan] */
    public const DOCS = [
        'pajak' => ['Pajak tahunan', 12],
        'stnk' => ['STNK dan plat 5 tahunan', 60],
        'kir' => ['KIR', 6],
        'asuransi' => ['Asuransi', 12],
    ];

    public const STATUS = ['aman' => 'Aman', 'segera' => 'Segera', 'terlambat' => 'Terlambat'];

    public const TONES = ['Aman' => 'success', 'Segera' => 'warn', 'Terlambat' => 'danger'];

    private const RANK = ['aman' => 0, 'segera' => 1, 'terlambat' => 2];

    public const DEFAULT_SOON_DAYS = 30;

    public const DEFAULT_SOON_KM = 1000;

    public static function soonDays(): int
    {
        return max(0, (int) Constants::get('ga.vehicle.soon_days', self::DEFAULT_SOON_DAYS));
    }

    public static function soonKm(): int
    {
        return max(0, (int) Constants::get('ga.vehicle.soon_km', self::DEFAULT_SOON_KM));
    }

    /** Berlaku sampai bawaan = tanggal bayar / uji + masa berlaku jenis dokumen (bulan kalender, tanpa meluap: 31 Agu + 6 bln = 28 Feb). */
    public static function validUntil(CarbonInterface|string $paidAt, string $type): Carbon
    {
        return Carbon::parse($paidAt instanceof CarbonInterface ? $paidAt->toDateString() : $paidAt)->startOfDay()->addMonthsNoOverflow(self::DOCS[$type][1]);
    }

    /** Selisih hari kalender dari $from ke $to (positif bila $to di masa depan). */
    public static function days(CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) Carbon::parse($from->toDateString())->diffInDays(Carbon::parse($to->toDateString()), false);
    }

    public static function daysText(int $days): string
    {
        return $days === 0 ? 'hari ini' : ($days > 0 ? "$days hari lagi" : 'lewat '.abs($days).' hari');
    }

    public static function kmText(int $km): string
    {
        return $km >= 0 ? self::km($km).' lagi' : 'lewat '.self::km(abs($km));
    }

    public static function km(int|float|string|null $km): string
    {
        return number_format((float) $km, 0, ',', '.').' km';
    }

    /** Rupiah untuk layar: tanpa desimal bila bulat, dua desimal bila tidak (nilai dihitung presisi lewat Decimal). */
    public static function rp(float|int|string|null $amount): string
    {
        $v = Decimal::round(Decimal::of($amount), 2);

        return 'IDR '.number_format($v, fmod($v, 1) === 0.0 ? 0 : 2, ',', '.');
    }

    /** Biaya servis dalam satu tahun kalender, semua kendaraan atau satu kendaraan (presisi desimal). */
    public static function yearCost(?int $year = null, ?int $vehicleId = null): float
    {
        return Decimal::round(Decimal::of(VehicleService::query()->whereYear('serviced_at', $year ?? today()->year)->when($vehicleId, fn ($q) => $q->where('vehicle_id', $vehicleId))->sum('cost')), 2);
    }

    /**
     * Status dan jadwal sekumpulan kendaraan dengan jumlah kueri tetap (tidak per kendaraan).
     *
     * @param  Collection<int, Vehicle>  $vehicles
     * @return array<int, array{status: string, docs: array<string, array>, items: list<array>, next: ?array}> id kendaraan => hasil
     */
    public static function overview(Collection $vehicles, ?CarbonInterface $today = null): array
    {
        if ($vehicles->isEmpty()) {
            return [];
        }
        $today = Carbon::parse(($today ?? today())->toDateString());
        $vehicles = new EloquentCollection($vehicles->all());
        $vehicles->loadMissing('type.plans');
        $ids = $vehicles->pluck('id')->all();
        $docs = self::currentDocs($ids);
        $last = self::lastServices($ids);
        $base = VehicleOdometer::query()->whereIn('vehicle_id', $ids)->where('source', 'awal')->get()->keyBy('vehicle_id');

        $out = [];
        foreach ($vehicles as $v) {
            $out[$v->id] = self::compute($v, $docs[$v->id] ?? [], $last[$v->id] ?? [], $base[$v->id] ?? null, $today);
        }

        return $out;
    }

    private static function compute(Vehicle $v, array $docs, array $last, ?VehicleOdometer $base, Carbon $today): array
    {
        $soonDays = self::soonDays();
        $soonKm = self::soonKm();
        $rank = [];

        $docRows = [];
        foreach (self::DOCS as $type => [$label]) {
            $required = $type !== 'kir' || $v->kir_required;
            /** @var VehicleDoc|null $doc */
            $doc = $docs[$type] ?? null;
            $days = $doc ? self::days($today, $doc->valid_until) : null;
            $status = match (true) {
                ! $required => null,
                $doc !== null => $days < 0 ? 'terlambat' : ($days <= $soonDays ? 'segera' : 'aman'),
                $type === 'asuransi' => null,   // asuransi belum dicatat tidak menandai kendaraan; yang lain (pajak, STNK, KIR wajib) harus tercatat
                default => 'segera',
            };
            $status && $rank[] = $status;
            $docRows[$type] = [
                'type' => $type, 'label' => $label, 'required' => $required, 'doc' => $doc, 'valid_until' => $doc?->valid_until, 'days' => $days, 'status' => $status,
                'text' => ! $required ? 'Tidak wajib' : ($doc ? $doc->valid_until->format('d-m-Y').' · '.self::daysText($days) : 'Belum dicatat'),
            ];
        }

        // Pemakaian rata-rata (km per hari) sejak odometer awal: dipakai mengubah sisa km menjadi perkiraan hari, supaya butir "terdekat" bisa dibandingkan antara butir berbasis km dan berbasis bulan.
        $rate = $base ? max(0, ($v->odometer_km - $base->km) / max(1, self::days(Carbon::parse($base->read_at->toDateString()), $today))) : 0;
        $items = [];
        foreach ($v->type->plans as $plan) {
            /** @var VehicleTypePlan $plan */
            $item = self::item($v, $plan, $last[$plan->id] ?? null, $base, $today, $soonDays, $soonKm, $rate);
            $rank[] = $item['status'];
            $items[] = $item;
        }
        $next = collect($items)->sortBy([fn ($a, $b) => self::RANK[$b['status']] <=> self::RANK[$a['status']], fn ($a, $b) => $a['eta'] <=> $b['eta']])->first();   // terburuk dulu, lalu yang paling cepat jatuh tempo

        return [
            'status' => collect($rank)->sortByDesc(fn ($s) => self::RANK[$s])->first() ?? 'aman',
            'docs' => $docRows, 'items' => $items, 'next' => $next,
        ];
    }

    private static function item(Vehicle $v, VehicleTypePlan $plan, ?array $last, ?VehicleOdometer $base, Carbon $today, int $soonDays, int $soonKm, float $rate): array
    {
        // Dasar hitungan: servis terakhir butir ini; belum pernah = catatan odometer awal; tanpa keduanya = hari ini (belum ada yang jatuh tempo).
        $baseAt = $last['at'] ?? ($base ? Carbon::parse($base->read_at->toDateString()) : $today->copy());
        $baseKm = $last['km'] ?? ($base?->km ?? $v->odometer_km);
        $nextKm = $plan->interval_km ? $baseKm + $plan->interval_km : null;
        $nextAt = $plan->interval_months ? $baseAt->copy()->addMonthsNoOverflow($plan->interval_months) : null;
        $kmLeft = $nextKm === null ? null : $nextKm - $v->odometer_km;
        $daysLeft = $nextAt === null ? null : self::days($today, $nextAt);
        $kmRatio = $nextKm === null ? null : ($v->odometer_km - $baseKm) / $plan->interval_km;
        $dayRatio = $nextAt === null ? null : self::days($baseAt, $today) / max(1, self::days($baseAt, $nextAt));
        $status = match (true) {
            ($kmLeft !== null && $kmLeft < 0) || ($daysLeft !== null && $daysLeft < 0) => 'terlambat',
            ($kmLeft !== null && $kmLeft <= $soonKm) || ($daysLeft !== null && $daysLeft <= $soonDays) => 'segera',
            default => 'aman',
        };
        $ratio = max($kmRatio ?? 0, $dayRatio ?? 0);   // kemajuan = kriteria yang lebih dulu tercapai
        $left = collect([$kmLeft === null ? null : self::kmText($kmLeft), $daysLeft === null ? null : self::daysText($daysLeft)])->filter()->implode(' / ');

        return [
            'plan' => $plan, 'name' => $plan->name, 'interval' => $plan->intervalLabel(), 'done' => $last !== null,
            'last_at' => $last['at'] ?? null, 'last_km' => $last['km'] ?? null, 'base_at' => $baseAt, 'base_km' => $baseKm,
            'next_km' => $nextKm, 'next_at' => $nextAt, 'km_left' => $kmLeft, 'days_left' => $daysLeft,
            'ratio' => $ratio, 'eta' => min(array_filter([$daysLeft, $kmLeft !== null && $rate > 0 ? $kmLeft / $rate : null], fn ($d) => $d !== null) ?: [PHP_INT_MAX]), 'progress' => (int) round(max(0, min(1, $ratio)) * 100), 'status' => $status,
            'text' => $plan->name.' · '.$left, 'left' => $left,
        ];
    }

    /** Dokumen berlaku-sampai terbesar per kendaraan dan jenis. @return array<int, array<string, VehicleDoc>> */
    private static function currentDocs(array $ids): array
    {
        $map = [];
        $docs = VehicleDoc::query()->whereIn('vehicle_id', $ids)->orderByDesc('valid_until')->orderByDesc('id')
            ->get(['id', 'vehicle_id', 'doc_type', 'doc_no', 'paid_at', 'valid_until', 'cost', 'attachment_path', 'attachment_name']);
        foreach ($docs as $doc) {
            $map[$doc->vehicle_id][$doc->doc_type] ??= $doc;
        }

        return $map;
    }

    /** Servis terakhir tiap butir rencana. @return array<int, array<int, array{at: Carbon, km: int}>> */
    private static function lastServices(array $ids): array
    {
        $map = [];
        $rows = DB::table('g_vehicle_service_line as l')->join('g_vehicle_service as s', 's.id', '=', 'l.vehicle_service_id')
            ->whereIn('s.vehicle_id', $ids)->whereNotNull('l.vehicle_type_plan_id')
            ->orderBy('s.serviced_at')->orderBy('s.odometer_km')->orderBy('s.id')
            ->get(['s.vehicle_id', 'l.vehicle_type_plan_id', 's.serviced_at', 's.odometer_km']);
        foreach ($rows as $r) {   // urut naik: yang terakhir menimpa
            $map[$r->vehicle_id][$r->vehicle_type_plan_id] = ['at' => Carbon::parse(substr((string) $r->serviced_at, 0, 10)), 'km' => (int) $r->odometer_km];
        }

        return $map;
    }

    // ---- Pencatatan -------------------------------------------------------------------------------------------------------------

    /** Odometer awal saat kendaraan didaftarkan: dasar jadwal bagi butir yang belum pernah diservis. */
    public static function startOdometer(Vehicle $vehicle, User $by, ?CarbonInterface $at = null): VehicleOdometer
    {
        return VehicleOdometer::create([
            'vehicle_id' => $vehicle->id, 'read_at' => $at ?? today(), 'km' => $vehicle->odometer_km, 'source' => 'awal', 'recorded_by' => $by->name, 'created_by' => $by->id,
        ]);
    }

    /**
     * Catat odometer (cepat). Kilometer tidak boleh lebih kecil dari catatan terakhir.
     *
     * @param  array{read_at: string, km: int|string, recorded_by?: ?string}  $data
     */
    public static function recordOdometer(Vehicle $vehicle, array $data, ?UploadedFile $photo, User $by): VehicleOdometer
    {
        $path = $photo?->store("odometer/{$vehicle->id}", 'ga');
        try {
            return DB::transaction(function () use ($vehicle, $data, $photo, $path, $by) {
                $v = Vehicle::query()->lockForUpdate()->findOrFail($vehicle->id);
                self::assertOdometer($v, (int) $data['km'], 'km');
                $row = VehicleOdometer::create([
                    'vehicle_id' => $v->id, 'read_at' => $data['read_at'], 'km' => (int) $data['km'], 'source' => 'manual', 'recorded_by' => $data['recorded_by'] ?? $by->name,
                    'photo_path' => $path, 'photo_name' => $photo ? mb_substr($photo->getClientOriginalName(), 0, 150) : null, 'created_by' => $by->id,
                ]);
                self::raiseOdometer($v, (int) $data['km'], $data['read_at']);

                return $row;
            });
        } catch (\Throwable $e) {
            $path && Storage::disk('ga')->delete($path);
            throw $e;
        }
    }

    /**
     * Catat servis: servis + pekerjaan yang dikerjakan + catatan odometer (sumber servis). Pekerjaan harus butir rencana jenis kendaraan ini.
     *
     * @param  array{serviced_at: string, odometer_km: int|string, workshop?: ?string, cost: float|int|string, note?: ?string, jobs?: list<int|string>}  $data
     */
    public static function recordService(Vehicle $vehicle, array $data, User $by): VehicleService
    {
        return DB::transaction(function () use ($vehicle, $data, $by) {
            $v = Vehicle::query()->lockForUpdate()->findOrFail($vehicle->id);
            $km = (int) $data['odometer_km'];
            self::assertOdometer($v, $km, 'odometer_km');
            $plans = VehicleTypePlan::query()->where('vehicle_type_id', $v->vehicle_type_id)->whereIn('id', $data['jobs'] ?? [])->orderBy('sort')->orderBy('id')->get();
            if ($plans->count() !== count(array_unique($data['jobs'] ?? []))) {
                throw ValidationException::withMessages(['jobs' => 'Pekerjaan yang dipilih bukan bagian dari jadwal kendaraan ini.']);
            }
            $service = VehicleService::create([
                'vehicle_id' => $v->id, 'serviced_at' => $data['serviced_at'], 'odometer_km' => $km, 'workshop' => $data['workshop'] ?? null,
                'cost' => Decimal::round(Decimal::of($data['cost']), 2), 'note' => $data['note'] ?? null, 'created_by' => $by->id,
            ]);
            foreach ($plans as $plan) {
                $service->lines()->create(['vehicle_type_plan_id' => $plan->id, 'name' => $plan->name]);
            }
            VehicleOdometer::create(['vehicle_id' => $v->id, 'read_at' => $data['serviced_at'], 'km' => $km, 'source' => 'servis', 'recorded_by' => $by->name, 'created_by' => $by->id]);
            self::raiseOdometer($v, $km, $data['serviced_at']);

            return $service;
        });
    }

    /**
     * Perpanjang dokumen. Berlaku sampai harus setelah tanggal bayar / uji; KIR hanya untuk kendaraan wajib KIR.
     *
     * @param  array{doc_type: string, doc_no?: ?string, paid_at: string, valid_until: string, cost: float|int|string, note?: ?string}  $data
     */
    public static function recordDoc(Vehicle $vehicle, array $data, ?UploadedFile $file, User $by): VehicleDoc
    {
        if (! isset(self::DOCS[$data['doc_type']])) {
            throw ValidationException::withMessages(['doc_type' => 'Jenis dokumen tidak dikenal.']);
        }
        if ($data['doc_type'] === 'kir' && ! $vehicle->kir_required) {
            throw ValidationException::withMessages(['doc_type' => 'Kendaraan ini tidak wajib KIR.']);
        }
        if (Carbon::parse($data['valid_until'])->lte(Carbon::parse($data['paid_at']))) {
            throw ValidationException::withMessages(['valid_until' => 'Berlaku sampai harus setelah tanggal bayar / uji.']);
        }
        $path = $file?->store("doc/{$vehicle->id}", 'ga');
        try {
            return VehicleDoc::create([
                'vehicle_id' => $vehicle->id, 'doc_type' => $data['doc_type'], 'doc_no' => $data['doc_no'] ?? null, 'paid_at' => $data['paid_at'], 'valid_until' => $data['valid_until'],
                'cost' => Decimal::round(Decimal::of($data['cost']), 2), 'note' => $data['note'] ?? null,
                'attachment_path' => $path, 'attachment_name' => $file ? mb_substr($file->getClientOriginalName(), 0, 150) : null, 'created_by' => $by->id,
            ]);
        } catch (\Throwable $e) {
            $path && Storage::disk('ga')->delete($path);
            throw $e;
        }
    }

    /** Odometer tidak boleh turun: pesan menyebut angka terakhir dan tanggalnya. */
    private static function assertOdometer(Vehicle $v, int $km, string $field): void
    {
        if ($km < $v->odometer_km) {
            $when = $v->odometer_at ? ' ('.$v->odometer_at->format('d-m-Y').')' : '';
            throw ValidationException::withMessages([$field => 'Odometer '.self::km($km).' lebih kecil dari catatan terakhir '.self::km($v->odometer_km)."$when. Periksa angkanya: odometer tidak boleh turun."]);
        }
    }

    private static function raiseOdometer(Vehicle $v, int $km, string $readAt): void
    {
        $at = Carbon::parse($readAt)->startOfDay();
        $newer = $km > $v->odometer_km || $v->odometer_at === null || $at->gt($v->odometer_at);
        $v->update(['odometer_km' => $km, 'odometer_at' => $newer ? $at : $v->odometer_at]);
    }
}
