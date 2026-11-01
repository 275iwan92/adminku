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

    /** Satu-satunya sumber presisi qty (kolom decimal(15,3)): validasi, pembulatan buku besar, dan toleransi saldo mengikuti ini. */
    public const QTY_SCALE = 3;

    public const QTY_EPS = 0.0005;

    /** Jumlah desimal bermakna (tanpa nol di ujung) pada angka / teks numerik. */
    public static function decimals(float|int|string $v): int
    {
        return strlen(substr(rtrim(self::of($v), '0'), strpos(self::of($v), '.') + 1));   // of() selalu memuat titik
    }

    /** Qty untuk pesan ke pengguna: format Indonesia (koma desimal), tanpa nol di ujung. */
    public static function fmtQty(float|int|string|null $v): string
    {
        return rtrim(rtrim(number_format((float) $v, self::QTY_SCALE, ',', '.'), '0'), ',');
    }

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
