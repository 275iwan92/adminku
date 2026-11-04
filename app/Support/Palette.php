<?php

namespace App\Support;

/* REMARK
 * Tujuan       : Sudut warna (hue 0-359) untuk kotak identitas di grid master supaya mudah dikenali: kategori ATK = hijau, Sparepart = biru, Tools = ungu (tiap item bervariasi sedikit di dalam keluarga warnanya);
 *                satuan menurut jenisnya (berat = oranye, panjang = toska, volume = biru langit, hitungan = indigo, luas = magenta, lainnya = merah muda).
 * Sumber hulu  : kode kategori items, kolom m_unit.kind.
 * Output       : int hue untuk kolom 'hue' pada x-dl::grid (identity).
 * Dipakai oleh : ItemController@index, UnitController@index.
 * Status       : Aktif.
 */
final class Palette
{
    private const CATEGORY = ['ATK' => 135, 'SPR' => 215, 'TLS' => 275];

    private const UNIT_KIND = ['weight' => 25, 'length' => 175, 'volume' => 200, 'count' => 250, 'area' => 320, 'other' => 345];

    // Hue dasar kategori + selisih -18..+18 dari seed (kode item), item yang sama selalu berwarna sama.
    public static function item(string $categoryCode, string $seed): int
    {
        $base = self::CATEGORY[$categoryCode] ?? (crc32($categoryCode) % 360);

        return ($base + (crc32($seed) % 37) - 18 + 360) % 360;
    }

    private const DEPT_ICON = ['admin' => 'fa-building', 'gudang' => 'fa-warehouse', 'hr' => 'fa-users', 'hrd' => 'fa-users', 'maintenance' => 'fa-screwdriver-wrench', 'produksi' => 'fa-industry', 'quality' => 'fa-circle-check', 'qc' => 'fa-circle-check', 'finance' => 'fa-coins', 'keuangan' => 'fa-coins', 'purchasing' => 'fa-cart-shopping', 'ga' => 'fa-toolbox'];

    // Ikon FA departemen menurut kata pertama namanya; bawaan fa-sitemap.
    public static function departmentIcon(string $name): string
    {
        return self::DEPT_ICON[mb_strtolower(strtok($name, ' ') ?: '')] ?? 'fa-sitemap';
    }

    public static function unit(string $kind): int
    {
        return self::UNIT_KIND[$kind] ?? self::UNIT_KIND['other'];
    }
}
