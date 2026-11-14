<?php

namespace App\Services;

use App\Models\StockCount;
use App\Support\Constants;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/* REMARK
 * Tujuan       : Jadwal Stok Opname rutin dan pengingatnya di Beranda. Jadwal (Mingguan: hari; Bulanan: tanggal atau akhir bulan; bawaan Bulanan akhir bulan) disimpan di tabel sys_constant (opname.*).
 *                "Berikutnya" dihitung dari tanggal Stok Opname SELESAI terakhir (dibatalkan tidak dihitung), atau dari tanggal jadwal disimpan bila belum pernah ada, atau hari ini bila jadwal belum pernah disimpan.
 *                Status: biasa (masih jauh), peringatan (H-3 sampai hari H), terlambat. Hanya tampilan Beranda; tanpa notifikasi e-mail / WhatsApp.
 * Sumber hulu  : sys_constant, i_count.
 * Output       : config(), save(), next(), state() untuk Beranda, daftar Stok Opname, dan lencana menu.
 * Dipakai oleh : StockCountController (jadwal), HomeController, Menu.
 * Status       : Aktif.
 */
final class CountSchedule
{
    public const WEEKDAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    public const WARN_DAYS = 3;
    private const MEMO = 'count_schedule_state';   // kunci memo di atribut permintaan

    /** @return array{frequency: string, weekday: int, day_of_month: string} day_of_month = 1..31 atau "last" */
    public static function config(): array
    {
        $day = (string) Constants::get('opname.day_of_month', 'last');

        return [
            'frequency' => Constants::get('opname.frequency') === 'weekly' ? 'weekly' : 'monthly',
            'weekday' => min(7, max(1, (int) Constants::get('opname.weekday', 1))),
            'day_of_month' => $day === 'last' || (int) $day < 1 || (int) $day > 31 ? 'last' : (string) (int) $day,
        ];
    }

    /** @param  array<string, mixed>  $data  frequency (weekly|monthly), weekday (1..7), day_of_month (1..31 | last) */
    public static function save(array $data): void
    {
        self::endRequest();
        $freq = $data['frequency'] ?? null;
        if (! in_array($freq, ['weekly', 'monthly'], true)) {
            throw ValidationException::withMessages(['frequency' => 'Pilih frekuensi Mingguan atau Bulanan.']);
        }
        Constants::set('opname.frequency', $freq, 'opname');
        Constants::set('opname.weekday', min(7, max(1, (int) ($data['weekday'] ?? 1))), 'opname');
        $day = (string) ($data['day_of_month'] ?? 'last');
        Constants::set('opname.day_of_month', $day === 'last' ? 'last' : min(31, max(1, (int) $day)), 'opname');
        Constants::set('opname.schedule_set_at', today()->toDateString(), 'opname');
    }

    /** Tanggal terjadwal PERTAMA sesudah $after (bukan sama dengan). Bulan pendek: tanggal 31 jatuh di akhir bulan (Februari 28 / 29). */
    public static function next(Carbon $after, ?array $cfg = null): Carbon
    {
        $cfg ??= self::config();
        $after = $after->copy()->startOfDay();
        if ($cfg['frequency'] === 'weekly') {
            $d = $after->copy()->addDay();
            while ($d->isoWeekday() !== $cfg['weekday']) {
                $d->addDay();
            }

            return $d;
        }
        $monthDay = fn (Carbon $m) => $cfg['day_of_month'] === 'last' ? $m->copy()->endOfMonth()->startOfDay() : $m->copy()->startOfMonth()->day(min((int) $cfg['day_of_month'], $m->daysInMonth));
        $d = $monthDay($after);

        return $d->gt($after) ? $d : $monthDay($after->copy()->startOfMonth()->addMonth());
    }

    public static function describe(?array $cfg = null): string
    {
        $cfg ??= self::config();

        return $cfg['frequency'] === 'weekly' ? 'Mingguan, hari '.self::WEEKDAYS[$cfg['weekday']] : 'Bulanan, '.($cfg['day_of_month'] === 'last' ? 'akhir bulan' : 'tanggal '.$cfg['day_of_month']);
    }

    public static function lastDone(): ?Carbon
    {
        $date = StockCount::query()->where('status', 'done')->max('count_date');

        return $date ? Carbon::parse($date)->startOfDay() : null;
    }

    /**
     * Keadaan pengingat untuk Beranda.
     *
     * @return array{running: ?StockCount, running_progress: ?string, last: ?Carbon, days_since: ?int, next: Carbon, days_to: int, tone: string, headline: string, detail: string, schedule: string}
     */
    public static function state(?Carbon $today = null): array
    {
        // Satu permintaan memakai satu hasil (Beranda HP memanggil dari pengingat dan lencana menu): tanpa kueri ganda.
        $bag = $today === null ? self::memoBag() : null;
        if ($bag?->has(self::MEMO)) {
            return $bag->get(self::MEMO);
        }
        $state = self::compute($today);
        $bag?->set(self::MEMO, $state);

        return $state;
    }

    // Memo hanya berlaku selama satu permintaan HTTP (ditandai saat rute cocok, dibuang saat selesai): pemanggilan di luar permintaan (tes, perintah) selalu membaca data terbaru.
    public static function beginRequest($request): void
    {
        $request->attributes->set(self::MEMO.'.http', true);
    }

    public static function endRequest(): void
    {
        if (app()->bound('request')) {
            foreach (['', '.available', '.http'] as $k) {
                request()->attributes->remove(self::MEMO.$k);
            }
        }
    }

    private static function memoBag(): ?\Symfony\Component\HttpFoundation\ParameterBag
    {
        return app()->bound('request') && request()->attributes->get(self::MEMO.'.http') ? request()->attributes : null;
    }

    private static function compute(?Carbon $today): array
    {
        $today = ($today ?? today())->copy()->startOfDay();
        $last = self::lastDone();
        $setAt = Constants::get('opname.schedule_set_at');
        $anchor = $last ?? ($setAt ? Carbon::parse($setAt)->startOfDay() : $today);
        $next = self::next($anchor);
        $daysTo = (int) $today->diffInDays($next, false);
        $running = StockCount::query()->active()->withCount(['lines', 'lines as filled_lines_count' => fn ($q) => $q->where(fn ($w) => $w->where('skipped', true)->orWhereNotNull('counted_qty')->orWhereNotNull('recount_qty'))])->first();

        [$tone, $detail] = match (true) {
            $daysTo < 0 => ['danger', 'Terlambat '.abs($daysTo).' hari'],
            $daysTo === 0 => ['warn', 'Stok Opname dijadwalkan hari ini'],
            $daysTo <= self::WARN_DAYS => ['warn', "Dijadwalkan {$daysTo} hari lagi"],
            default => ['neutral', "Dijadwalkan {$daysTo} hari lagi"],
        };
        $headline = $last
            ? 'Stok Opname terakhir: '.$last->format('d-m-Y').' ('.$last->diffInDays($today).' hari lalu). Berikutnya: '.$next->format('d-m-Y').'.'
            : 'Belum pernah ada Stok Opname. Jadwal: '.self::describe().'. Berikutnya: '.$next->format('d-m-Y').'.';

        return [
            'running' => $running, 'running_progress' => $running ? "{$running->filled_lines_count} dari {$running->lines_count}" : null,
            'last' => $last, 'days_since' => $last ? (int) $last->diffInDays($today) : null, 'next' => $next, 'days_to' => $daysTo,
            'tone' => $tone, 'headline' => $headline, 'detail' => $detail, 'schedule' => self::describe(),
        ];
    }

    // Tabel opname baru ada sesudah migrasi dijalankan; Beranda dan menu tidak boleh rusak sebelum itu.
    public static function available(): bool
    {
        $bag = self::memoBag();
        if ($bag?->has(self::MEMO.'.available')) {
            return $bag->get(self::MEMO.'.available');
        }
        $ok = Schema::hasTable('i_count');
        $bag?->set(self::MEMO.'.available', $ok);

        return $ok;
    }

    /** Lencana menu: sedang berjalan atau terlambat (null = tanpa lencana). @return array{badge: string, tone: string, title: string}|null tone = badgeTone Layer Shell (partial = kuning, danger = merah) */
    public static function badge(): ?array
    {
        if (! self::available()) {
            return null;
        }
        $s = self::state();
        if ($s['running']) {
            return ['badge' => '●', 'tone' => 'partial', 'title' => "{$s['running']->doc_no} sedang berjalan"];
        }

        return $s['days_to'] < 0 ? ['badge' => '!', 'tone' => 'danger', 'title' => $s['detail']] : null;
    }
}
