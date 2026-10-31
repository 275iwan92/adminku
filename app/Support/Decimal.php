<?php

namespace App\Support;

/* REMARK
 * Tujuan       : Aritmetika presisi (bcmath) untuk NILAI uang / biaya / konversi: tanpa kesalahan float, dibulatkan setengah-menjauhi-nol hanya sekali di akhir, pada skala kolom database.
 *                Angka masuk berupa float/string dari kolom decimal (<= 10 desimal), perantara berupa string; hasil akhir float supaya tipe di layar dan laporan tetap sama.
 *                Kuantitas (qty) sengaja tetap memakai pembulatan 3 desimal dan toleransi lama.
 * Dipakai oleh : StockLedger, StockDocuments, UnitConverter, Batch, ReportController, StockDocumentController, StockDocumentPrint, StockValuation.
 * Status       : Aktif.
 */
final class Decimal
{
    private const SCALE = 20;   // skala perantara; pembagian dipotong di sini, lalu dibulatkan sekali oleh round()

    public static function of(float|int|string|null $v): string
    {
        return number_format((float) $v, 10, '.', '');
    }

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::SCALE);
    }

    public static function mul(string $a, string $b): string
    {
        return bcmul($a, $b, self::SCALE);
    }

    public static function div(string $a, string $b): string
    {
        return bcdiv($a, $b, self::SCALE);
    }

    /** Setengah menjauhi nol pada $scale desimal. */
    public static function round(string $n, int $scale): float
    {
        $half = '0.'.str_repeat('0', $scale).'5';

        return (float) bcadd($n, bccomp($n, '0', self::SCALE) < 0 ? '-'.$half : $half, $scale);
    }

    /** qty x biaya, dibulatkan sekali. */
    public static function amount(float|int|string|null $qty, float|int|string|null $cost, int $scale = 2): float
    {
        return self::round(self::mul(self::of($qty), self::of($cost)), $scale);
    }

    /** Jumlah qty x biaya dari banyak baris, dibulatkan sekali di akhir. @param iterable<array{0: mixed, 1: mixed}> $pairs */
    public static function total(iterable $pairs, int $scale = 2): float
    {
        $sum = '0';
        foreach ($pairs as [$qty, $cost]) {
            $sum = self::add($sum, self::mul(self::of($qty), self::of($cost)));
        }

        return self::round($sum, $scale);
    }
}
