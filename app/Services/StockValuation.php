<?php

namespace App\Services;

use App\Models\StockMovement;
use App\Support\Decimal;
use Devlib2\Locale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
        // Nilai tiap titik = jumlah (saldo x biaya rata-rata) dari pergerakan terakhir tiap batch sampai titik itu, dihitung di database (tanpa memuat seluruh buku besar ke memori); SUM di MySQL desimal persis, dibulatkan sekali.
        $labels = [];
        $values = [];
        foreach ($cutoffs as $cutoff) {
            $last = DB::table('rm_stockcard')->where('moved_at', '<=', $cutoff)->selectRaw('max(id) as id')->groupBy('batch_id');
            $sum = DB::table('rm_stockcard as r')->joinSub($last, 'm', 'm.id', '=', 'r.id')->selectRaw('sum(r.balance_qty * r.balance_avg_cost) as v')->value('v');
            $labels[] = Locale::at($cutoff)->translatedFormat('\s/\d d M');
            $values[] = Decimal::round(Decimal::of($sum ?? 0), 0);
        }

        return [
            'labels' => $labels, 'values' => $values, 'month' => $start, 'title' => Locale::at($start)->translatedFormat('F Y'),
            'prev' => $start > $min ? $start->copy()->subMonthNoOverflow()->format('Y-m') : null,
            'next' => $start < $max ? $start->copy()->addMonthNoOverflow()->format('Y-m') : null,
        ];
    }
}
