<?php

namespace App\Support;

use App\Models\ProductUnit;
use App\Models\UnitConversion;

/* REMARK
 * Tujuan       : Mengubah jumlah antar satuan: faktor satuan masuk milik BARANG lebih dulu (m_item_unit, hanya ke satuan stok barang itu), lalu master konversi global: langsung (1 box = 12 pcs), kebalikannya (1 / faktor), atau satuan yang sama (faktor 1). Tanpa rantai (box > pcs > gram tidak dihitung otomatis).
 * Sumber hulu  : m_item_unit, m_unit_conversion.
 * Output       : factor(from, to, productId) atau null bila tidak ada aturannya; convert(qty, from, to); exact(...).
 * Dipakai oleh : StockDocuments (Penerimaan dan Pengeluaran: satuan baris dikonversi ke satuan stok barang).
 * Status       : Aktif.
 */
final class UnitConverter
{
    // Faktor satuan masuk milik barang (1 {from} = n satuan stok); $to harus satuan stok barang itu. Null bila barang belum punya.
    private static function own(?int $productId, string $from): ?string
    {
        return $productId ? ProductUnit::query()->where('product_id', $productId)->where('unit', $from)->value('factor') : null;
    }

    // Berapa {to} dalam 1 {from}; null bila tidak ada aturan. $productId = barang yang diterima (faktor miliknya didahulukan).
    public static function factor(string $from, string $to, ?int $productId = null): ?float
    {
        if ($from === $to) {
            return 1.0;
        }
        if ($own = self::own($productId, $from)) {
            return (float) $own;
        }
        if ($direct = UnitConversion::query()->where('from_unit', $from)->where('to_unit', $to)->value('factor')) {
            return (float) $direct;
        }
        $inverse = UnitConversion::query()->where('from_unit', $to)->where('to_unit', $from)->value('factor');

        return $inverse ? 1 / (float) $inverse : null;
    }

    public static function convert(float $qty, string $from, string $to): ?float
    {
        $exact = self::exact(Decimal::of($qty), $from, $to);

        return $exact === null ? null : Decimal::round($exact, 6);
    }

    /**
     * $x x faktor({from} ke {to}) tanpa float (string desimal presisi; pembulatan di pemanggil). $reverse = $x / faktor, mis. biaya per satuan {from} menjadi biaya per satuan {to}.
     * Kebalikan memakai baris konversi yang ada (x / g), bukan 1/g yang sudah dibulatkan, jadi 1 box = 3 pcs lalu kembali lagi tetap tepat. Null bila tak ada aturannya.
     */
    public static function exact(string $x, string $from, string $to, bool $reverse = false, ?int $productId = null): ?string
    {
        if ($from === $to) {
            return $x;
        }
        if ($own = self::own($productId, $from)) {
            return $reverse ? Decimal::div($x, Decimal::of($own)) : Decimal::mul($x, Decimal::of($own));
        }
        if ($direct = UnitConversion::query()->where('from_unit', $from)->where('to_unit', $to)->value('factor')) {
            return $reverse ? Decimal::div($x, Decimal::of($direct)) : Decimal::mul($x, Decimal::of($direct));
        }
        $inverse = UnitConversion::query()->where('from_unit', $to)->where('to_unit', $from)->value('factor');
        if (! $inverse) {
            return null;
        }

        return $reverse ? Decimal::mul($x, Decimal::of($inverse)) : Decimal::div($x, Decimal::of($inverse));
    }
}
