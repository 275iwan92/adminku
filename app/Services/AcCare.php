<?php

namespace App\Services;

use App\Models\AcOrder;
use App\Models\AcUnit;
use App\Models\AcVendor;
use App\Models\User;
use App\Support\Constants;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/* REMARK
 * Tujuan       : Semua hitungan dan penjadwalan GA Perawatan AC di satu tempat: pengaturan (sys_constant ga.ac.*), kode otomatis, jatuh tempo cuci rutin (3 bulan) dan servis besar (12 bulan) per AC,
 *                status Aman / Segera / Terlambat, penjadwalan dengan kapasitas harian PER VENDOR yang ditegakkan di server (transaksi + kunci baris vendor), satu AC satu pesanan terbuka, dan susun otomatis
 *                (paling terlambat dulu, hari demi hari sesuai kapasitas dan hari kunjungan yang diizinkan). Servis besar sudah mencakup cuci: pekerjaan terakhir apa pun menggeser jatuh tempo cuci.
 *                Siklus pesanan (kirim, konfirmasi, selesai, jadwal ulang, batal) ada di AcVisits; pesan pemberitahuan di AcMessage. Modul ini TIDAK menyentuh stok gudang.
 * Sumber hulu  : Constants (ga.ac.wash_months = 3, major_months = 12, capacity = 3, days = 1,2,3,4,5, start_time = 09:00, end_time = 16:00, soon_days = 30), m_ac_*, g_ac_*.
 * Output       : overview() untuk layar Penjadwalan / Denah; schedule(), autoPlan(), weekLoad(), used(), openLines().
 * Dipakai oleh : AcScheduleController, AcVisitController, AcPlanController, controller master AC, AcSeeder.
 * Status       : Aktif.
 */
final class AcCare
{
    public const JOBS = ['wash' => 'Cuci rutin', 'major' => 'Servis besar'];

    public const STATUS = ['aman' => 'Aman', 'segera' => 'Segera', 'terlambat' => 'Terlambat'];

    public const TONES = ['Aman' => 'success', 'Segera' => 'warn', 'Terlambat' => 'danger'];

    public const ORDER_STATUS = ['draft' => 'Draf', 'sent' => 'Terkirim', 'confirmed' => 'Dikonfirmasi vendor', 'done' => 'Selesai', 'rescheduled' => 'Dijadwal ulang', 'cancelled' => 'Dibatalkan'];

    public const ORDER_TONES = ['Draf' => 'neutral', 'Terkirim' => 'info', 'Dikonfirmasi vendor' => 'violet', 'Selesai' => 'success', 'Dijadwal ulang' => 'warn', 'Dibatalkan' => 'danger'];

    public const DAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    // ---- Pengaturan (sys_constant, grup "ga") -----------------------------------------------------------------------------------

    public static function washMonths(): int
    {
        return max(1, min(60, (int) Constants::get('ga.ac.wash_months', 3)));
    }

    public static function majorMonths(): int
    {
        return max(1, min(120, (int) Constants::get('ga.ac.major_months', 12)));
    }

    public static function soonDays(): int
    {
        return max(0, (int) Constants::get('ga.ac.soon_days', 30));
    }

    /** Kapasitas kunjungan: jumlah AC per hari PER VENDOR. */
    public static function capacity(): int
    {
        return max(1, min(20, (int) Constants::get('ga.ac.capacity', 3)));
    }

    /** Hari kunjungan yang diizinkan (1 = Senin ... 7 = Minggu). Kosong / tidak valid = Senin sampai Jumat. */
    public static function visitDays(): array
    {
        $days = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) Constants::get('ga.ac.days', '1,2,3,4,5'))), fn ($d) => $d >= 1 && $d <= 7)));

        return $days ?: [1, 2, 3, 4, 5];
    }

    public static function startTime(): string
    {
        return self::hhmm(Constants::get('ga.ac.start_time'), '09:00');
    }

    public static function endTime(): string
    {
        return self::hhmm(Constants::get('ga.ac.end_time'), '16:00');
    }

    /** Pukul berapa salinan pemberitahuan PIC dikirim (sehari sebelum kunjungan). */
    public static function picNoticeTime(): string
    {
        return self::hhmm(Constants::get('ga.ac.pic_notice_time'), '15:00');
    }

    private static function hhmm(mixed $value, string $default): string
    {
        return is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : $default;
    }

    public static function dayNames(array $days): string
    {
        return implode(', ', array_map(fn ($d) => self::DAYS[$d], $days));
    }

    public static function timeText(string $hhmm): string
    {
        return str_replace(':', '.', substr($hhmm, 0, 5));
    }

    // ---- Kode otomatis ----------------------------------------------------------------------------------------------------------

    /** Kode dari nama: beberapa kata = huruf depan tiap kata (maks 4), satu kata = tiga huruf pertama. "Ruang Meeting" -> RM, "Gudang" -> GUD. */
    public static function codeFrom(string $name): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', Str::ascii($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $code = count($words) > 1 ? implode('', array_map(fn ($w) => $w[0], $words)) : substr($words[0] ?? 'X', 0, 3);

        return strtoupper(substr($code, 0, 4));
    }

    /** Kode otomatis yang belum dipakai di tabel: dasar dari nama, bila bentrok ditambah angka 2, 3, ... */
    public static function uniqueCode(string $table, string $name, string $column = 'code'): string
    {
        $base = self::codeFrom($name);
        $code = $base;
        for ($n = 2; DB::table($table)->where($column, $code)->exists(); $n++) {
            $code = $base.$n;
        }

        return $code;
    }

    // ---- Jatuh tempo dan status -------------------------------------------------------------------------------------------------

    /**
     * Jatuh tempo sekumpulan AC dengan jumlah kueri tetap (tidak per AC). Cuci rutin terakhir = yang paling baru dari cuci awal, servis besar awal, dan semua pekerjaan SELESAI (servis besar mencakup cuci);
     * servis besar terakhir = servis besar awal atau yang SELESAI. Tanpa riwayat apa pun: tanggal pasang, lalu tanggal AC didaftarkan. Jatuh tempo AC = yang lebih dulu dari cuci berikutnya dan servis besar berikutnya.
     *
     * @param  Collection<int, AcUnit>  $units
     * @return array<int, array<string, mixed>> id unit => hasil
     */
    public static function overview(Collection $units, ?CarbonInterface $today = null): array
    {
        if ($units->isEmpty()) {
            return [];
        }
        $today = Carbon::parse(($today ?? today())->toDateString());
        $units = new EloquentCollection($units->all());
        $units->loadMissing('room.area');
        $ids = $units->pluck('id')->all();
        $done = self::lastDone($ids);
        $open = self::openLines($ids);

        $out = [];
        foreach ($units as $u) {
            $out[$u->id] = self::compute($u, $done[$u->id] ?? [], $open[$u->id] ?? null, $today);
        }

        return $out;
    }

    private static function compute(AcUnit $u, array $done, ?array $open, Carbon $today): array
    {
        $fallback = Carbon::parse(($u->installed_at ?? $u->created_at ?? $today)->toDateString());
        $pick = fn (array $dates) => ($dates = array_values(array_filter($dates))) ? collect($dates)->max() : null;
        $lastWash = $pick([$u->last_wash_at, $u->last_major_at, $done['wash'] ?? null, $done['major'] ?? null]);
        $lastMajor = $pick([$u->last_major_at, $done['major'] ?? null]);
        $nextWash = Carbon::parse(($lastWash ?? $fallback)->toDateString())->addMonthsNoOverflow(self::washMonths());
        $nextMajor = Carbon::parse(($lastMajor ?? $fallback)->toDateString())->addMonthsNoOverflow(self::majorMonths());
        $due = $nextWash->lt($nextMajor) ? $nextWash : $nextMajor;
        $days = VehicleCare::days($today, $due);
        $status = $days < 0 ? 'terlambat' : ($days <= self::soonDays() ? 'segera' : 'aman');

        return [
            'unit' => $u, 'status' => $status, 'days' => $days, 'due' => $due, 'next_wash' => $nextWash, 'next_major' => $nextMajor,
            'last_wash' => $lastWash ? Carbon::parse($lastWash->toDateString()) : null, 'last_major' => $lastMajor ? Carbon::parse($lastMajor->toDateString()) : null,
            'job' => self::jobFor($nextMajor, $due->gt($today) ? $due : $today), 'open' => $open,
            'due_text' => VehicleCare::daysText($days), 'label' => self::STATUS[$status],
        ];
    }

    /** Servis besar bila jatuh tempo servis besar sudah lewat atau berdekatan (dalam ambang "segera") dengan tanggal kunjungan; selain itu cuci rutin. */
    public static function jobFor(CarbonInterface $nextMajor, CarbonInterface $visit): string
    {
        return Carbon::parse($nextMajor->toDateString())->lte(Carbon::parse($visit->toDateString())->addDays(self::soonDays())) ? 'major' : 'wash';
    }

    /** Tanggal selesai terakhir per AC dan jenis. @return array<int, array<string, Carbon>> */
    private static function lastDone(array $ids): array
    {
        $map = [];
        $rows = DB::table('g_ac_service_line')->whereIn('unit_id', $ids)->whereNotNull('done_at')->groupBy('unit_id', 'job')->get(['unit_id', 'job', DB::raw('max(done_at) as d')]);
        foreach ($rows as $r) {
            $map[$r->unit_id][$r->job] = Carbon::parse(substr((string) $r->d, 0, 10));
        }

        return $map;
    }

    /**
     * Pesanan terbuka (Draf, Terkirim, Dikonfirmasi, Dijadwal ulang) per AC: yang paling awal.
     *
     * @return array<int, array{order_id: int, no: string, date: Carbon, time: string, status: string}>
     */
    public static function openLines(array $unitIds): array
    {
        $map = [];
        $rows = DB::table('g_ac_service_line as l')->join('g_ac_service_order as o', 'o.id', '=', 'l.order_id')
            ->whereIn('l.unit_id', $unitIds)->whereIn('o.status', AcOrder::OPEN)->orderBy('o.visit_date')->orderBy('o.id')
            ->get(['l.unit_id', 'o.id as order_id', 'o.no', 'o.visit_date', 'o.visit_time', 'o.status']);
        foreach ($rows as $r) {
            $map[$r->unit_id] ??= ['order_id' => $r->order_id, 'no' => $r->no, 'date' => Carbon::parse(substr((string) $r->visit_date, 0, 10)), 'time' => substr((string) $r->visit_time, 0, 5), 'status' => $r->status];
        }

        return $map;
    }

    // ---- Kapasitas dan hari kunjungan -------------------------------------------------------------------------------------------

    /** AC yang sudah dijadwalkan (bukan batal) pada tanggal itu untuk vendor itu. */
    public static function used(int $vendorId, CarbonInterface|string $date, ?int $exceptOrder = null): int
    {
        return (int) DB::table('g_ac_service_line as l')->join('g_ac_service_order as o', 'o.id', '=', 'l.order_id')
            ->where('o.vendor_id', $vendorId)->whereDate('o.visit_date', $date instanceof CarbonInterface ? $date->toDateString() : $date)->where('o.status', '!=', 'cancelled')
            ->when($exceptOrder, fn ($q) => $q->where('o.id', '!=', $exceptOrder))->count();
    }

    /** AC terjadwal per tanggal untuk satu vendor dalam rentang (kalender). @return array<string, int> 'Y-m-d' => jumlah */
    public static function load(int $vendorId, CarbonInterface $from, CarbonInterface $to): array
    {
        $out = [];
        $rows = DB::table('g_ac_service_line as l')->join('g_ac_service_order as o', 'o.id', '=', 'l.order_id')
            ->where('o.vendor_id', $vendorId)->whereDate('o.visit_date', '>=', $from->toDateString())->whereDate('o.visit_date', '<=', $to->toDateString())->where('o.status', '!=', 'cancelled')
            ->groupBy('o.visit_date')->get(['o.visit_date', DB::raw('count(l.id) as n')]);
        foreach ($rows as $r) {
            $out[substr((string) $r->visit_date, 0, 10)] = (int) $r->n;
        }

        return $out;
    }

    /** Jumlah AC terjadwal (bukan batal) pada rentang tanggal untuk semua vendor (kotak ringkasan "terjadwal minggu ini"). */
    public static function scheduledBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) DB::table('g_ac_service_line as l')->join('g_ac_service_order as o', 'o.id', '=', 'l.order_id')
            ->whereDate('o.visit_date', '>=', $from->toDateString())->whereDate('o.visit_date', '<=', $to->toDateString())->where('o.status', '!=', 'cancelled')->count();
    }

    /** Tanggal, hari, dan jam kunjungan harus sah: bukan masa lalu, hari yang diizinkan, jam dalam jendela kunjungan. */
    public static function assertSlot(string $date, string $time): Carbon
    {
        try {
            $day = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['date' => 'Tanggal kunjungan tidak valid.']);
        }
        if ($day->format('Y-m-d') !== $date) {
            throw ValidationException::withMessages(['date' => 'Tanggal kunjungan tidak valid.']);
        }
        if ($day->lt(today())) {
            throw ValidationException::withMessages(['date' => 'Tanggal kunjungan tidak boleh di masa lalu.']);
        }
        if (! in_array($day->dayOfWeekIso, self::visitDays(), true)) {
            throw ValidationException::withMessages(['date' => 'Hari '.self::DAYS[$day->dayOfWeekIso].' tidak diizinkan untuk kunjungan. Hari yang diizinkan: '.self::dayNames(self::visitDays()).'.']);
        }
        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) || $time < self::startTime() || $time > self::endTime()) {
            throw ValidationException::withMessages(['time' => 'Jam kunjungan harus antara '.self::timeText(self::startTime()).' dan '.self::timeText(self::endTime()).'.']);
        }

        return $day;
    }

    /** Menolak bila $n AC tambahan melebihi kapasitas harian vendor. Panggil di dalam transaksi yang sudah mengunci baris vendor. */
    public static function assertCapacity(AcVendor $vendor, CarbonInterface $day, int $n, ?int $exceptOrder = null): void
    {
        $left = max(0, self::capacity() - self::used($vendor->id, $day, $exceptOrder));
        if ($n > $left) {
            throw ValidationException::withMessages(['date' => $left === 0
                ? 'Tanggal '.$day->format('d-m-Y').' sudah penuh (kapasitas '.self::capacity().' AC per hari). Pilih tanggal lain.'
                : 'Tanggal '.$day->format('d-m-Y').' hanya muat '.$left.' AC lagi (kapasitas '.self::capacity().' AC per hari), Anda memilih '.$n.' AC.']);
        }
    }

    // ---- Penjadwalan ------------------------------------------------------------------------------------------------------------

    /**
     * Jadwalkan AC pada satu tanggal untuk satu vendor (pesanan berstatus Draf; bila vendor sudah punya pesanan Draf pada tanggal itu, AC ditambahkan ke sana).
     * Dijaga di server: tanggal / hari / jam, AC aktif, satu AC satu pesanan terbuka, dan kapasitas harian vendor (baris vendor dikunci supaya dua petugas yang menjadwalkan bersamaan tidak melewati kapasitas).
     *
     * @param  list<int|string>  $unitIds
     */
    public static function schedule(AcVendor $vendor, string $date, ?string $time, array $unitIds, User $by): AcOrder
    {
        $time = $time ?: self::startTime();
        $day = self::assertSlot($date, $time);
        $ids = array_values(array_unique(array_map('intval', $unitIds)));
        if (! $ids) {
            throw ValidationException::withMessages(['units' => 'Pilih minimal satu AC yang akan dijadwalkan.']);
        }

        return DB::transaction(function () use ($vendor, $day, $time, $ids, $by) {
            $v = self::lockVendor($vendor);
            $units = AcUnit::query()->with('room.area')->whereIn('id', $ids)->where('is_active', true)->get();
            if ($units->count() !== count($ids)) {
                throw ValidationException::withMessages(['units' => 'Ada AC yang tidak ditemukan atau sudah nonaktif.']);
            }
            self::assertNoOpen($units);
            self::assertCapacity($v, $day, $units->count());

            return self::addLines($v, $day, $time, $units, self::overview($units), $by);
        });
    }

    /**
     * Susun otomatis: AC aktif berstatus Segera / Terlambat yang belum terjadwal, paling terlambat dulu, diisi hari demi hari
     * (mulai $from, paling cepat hari ini) sesuai kapasitas harian dan hari kunjungan yang diizinkan. Setiap hari menjadi (atau menambah) satu pesanan Draf.
     *
     * @return array{orders: list<AcOrder>, units: int}
     */
    public static function autoPlan(AcVendor $vendor, ?string $from, User $by): array
    {
        $start = Carbon::parse($from ?: today()->toDateString())->startOfDay();
        $start = $start->lt(today()) ? today() : $start;

        return DB::transaction(function () use ($vendor, $start, $by) {
            $v = self::lockVendor($vendor);
            $units = AcUnit::query()->with('room.area')->where('is_active', true)->get();
            $over = self::overview($units);
            $queue = $units->filter(fn ($u) => ! $over[$u->id]['open'] && $over[$u->id]['status'] !== 'aman')
                ->sortBy([fn ($a, $b) => $over[$a->id]['days'] <=> $over[$b->id]['days'], fn ($a, $b) => strcmp($a->asset_code, $b->asset_code)])->values();
            $orders = [];
            $total = $queue->count();
            for ($day = $start->copy(), $i = 0; $queue->isNotEmpty() && $i < 400; $day->addDay(), $i++) {
                $left = self::capacity() - self::used($v->id, $day);
                if ($left <= 0 || ! in_array($day->dayOfWeekIso, self::visitDays(), true)) {
                    continue;
                }
                $orders[] = self::addLines($v, $day->copy(), self::startTime(), $queue->splice(0, $left), $over, $by);
            }

            return ['orders' => $orders, 'units' => $total - $queue->count()];
        });
    }

    private static function lockVendor(AcVendor $vendor): AcVendor
    {
        $v = AcVendor::query()->lockForUpdate()->findOrFail($vendor->id);
        if (! $v->is_active) {
            throw ValidationException::withMessages(['vendor_id' => 'Vendor ini tidak aktif.']);
        }

        return $v;
    }

    private static function assertNoOpen(Collection $units): void
    {
        $open = self::openLines($units->pluck('id')->all());
        if ($open) {
            $u = $units->firstWhere('id', array_key_first($open));
            $o = $open[$u->id];
            throw ValidationException::withMessages(['units' => $u->label().' sudah terjadwal pada '.$o['date']->format('d-m-Y').' ('.$o['no'].'). Satu AC tidak boleh punya dua pesanan terbuka.']);
        }
    }

    private static function addLines(AcVendor $vendor, Carbon $day, string $time, Collection $units, array $over, User $by): AcOrder
    {
        $order = AcOrder::query()->where('vendor_id', $vendor->id)->whereDate('visit_date', $day->toDateString())->where('status', 'draft')->orderBy('id')->first()
            ?? self::createOrder($vendor, $day, $time, $by);
        foreach ($units as $u) {
            $order->lines()->create(['unit_id' => $u->id, 'job' => self::jobFor($over[$u->id]['next_major'], $day)]);
        }

        return $order;
    }

    // Nomor ACV-YYMM-NNNN; dua penyimpan bersamaan bisa membaca nomor terakhir yang sama: kolom unik menolak yang kalah, lalu nomor berikutnya diambil lagi (bacaan terkunci).
    private static function createOrder(AcVendor $vendor, Carbon $day, string $time, User $by): AcOrder
    {
        for ($try = 1; ; $try++) {
            $stem = 'ACV-'.now()->format('ym').'-';
            $last = AcOrder::query()->where('no', 'like', $stem.'%')->orderByDesc('no')->when($try > 1, fn ($q) => $q->lockForUpdate())->value('no');
            $no = $stem.str_pad((string) ($last ? (int) substr($last, -4) + 1 : 1), 4, '0', STR_PAD_LEFT);
            try {
                return AcOrder::create(['no' => $no, 'vendor_id' => $vendor->id, 'visit_date' => $day->toDateString(), 'visit_time' => $time, 'status' => 'draft', 'created_by' => $by->id]);
            } catch (UniqueConstraintViolationException $e) {
                if ($try >= 5) {
                    throw $e;
                }
            }
        }
    }
}
