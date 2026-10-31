<?php

namespace App\Services;

use App\Models\StockMovement;
use App\Support\Decimal;
use Devlib\Locale;
use Illuminate\Support\Carbon;

/* REMARK
 * Tujuan       : Deret nilai persediaan (saldo x biaya rata-rata semua barang) untuk grafik di opening screen: satu bulan per tampilan,
 *                titik per minggu (tanggal 7, 14, 21, 28, lalu akhir bulan atau hari ini), dengan navigasi bulan sebelum/sesudahnya.
 * Sumber hulu  : rm_stockcard (balance_qty, balance_avg_cost sesudah tiap pergerakan).
 * Output       : ['labels' => [...], 'values' => [...], 'month' => Carbon, 'title' => 'Oktober 2026', 'prev' => 'YYYY-MM'|null, 'next' => 'YYYY-MM'|null].
 * Dipakai oleh : HomeController (hanya Supervisor).
 * Status       : Aktif.
 */
final class StockValuation
{
    // shortcut: memuat seluruh pergerakan sampai akhir bulan ke memori, cukup untuk puluhan ribu baris; upgrade ke tabel ringkasan harian bila lebih besar.
    public static function series(?string $month = null, ?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy()->endOfDay();
        $first = StockMovement::query()->min('moved_at');
        $min = $first ? Carbon::parse($first)->startOfMonth() : $today->copy()->startOfMonth();
        $max = $today->copy()->startOfMonth();
        $start = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $month) ? Carbon::createFromFormat('!Y-m', $month) : $max->copy();
        $start = $start->max($min)->min($max);
        $end = $start->copy()->endOfMonth()->min($today);

        $cutoffs = collect([7, 14, 21, 28])->map(fn ($d) => $start->copy()->day($d)->endOfDay())->filter(fn ($c) => $c < $end->copy()->startOfDay())->push($end)->values();
        $moves = StockMovement::query()->where('moved_at', '<=', $end)->orderBy('moved_at')->orderBy('id')->get(['batch_id', 'moved_at', 'balance_qty', 'balance_avg_cost']);

        $value = [];
        $i = 0;
        $labels = [];
        $values = [];
        foreach ($cutoffs as $cutoff) {
            while ($i < $moves->count() && $moves[$i]->moved_at <= $cutoff) {
                $value[$moves[$i]->batch_id] = [$moves[$i]->balance_qty, $moves[$i]->balance_avg_cost];
                $i++;
            }
            $labels[] = Locale::at($cutoff)->translatedFormat('\s/\d d M');
            $values[] = Decimal::total($value, 0);
        }

        return [
            'labels' => $labels, 'values' => $values, 'month' => $start, 'title' => Locale::at($start)->translatedFormat('F Y'),
            'prev' => $start > $min ? $start->copy()->subMonthNoOverflow()->format('Y-m') : null,
            'next' => $start < $max ? $start->copy()->addMonthNoOverflow()->format('Y-m') : null,
        ];
    }
}
