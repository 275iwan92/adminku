<?php

namespace App\Services;

use App\Support\Constants;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/* REMARK
 * Tujuan       : Semua hitungan dan pencatatan GA Perawatan AC di satu tempat: pengaturan (sys_constant), kode otomatis, jatuh tempo cuci rutin / servis besar, status Aman / Segera / Terlambat,
 *                penjadwalan dengan kapasitas harian per vendor, susun otomatis dan siklus kunjungan. Modul ini TIDAK menyentuh stok gudang.
 * Sumber hulu  : Constants (ga.ac.*), m_ac_*, g_ac_*.
 * Output       : lihat metode.
 * Dipakai oleh : AcScheduleController, AcVisitController, AcPlanController, controller master AC, AcSeeder.
 * Status       : Aktif.
 */
final class AcCare
{
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
}
