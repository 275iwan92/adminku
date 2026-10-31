<?php

namespace App\Support;

use App\Models\Constant;
use Illuminate\Support\Facades\Schema;

/* REMARK
 * Tujuan       : Pengaturan aplikasi kecil key / value (tabel sys_constant): jadwal Stok Opname (opname.frequency, opname.weekday, opname.day_of_month, opname.schedule_set_at); modul Perusahaan memakai tabel yang sama.
 *                Dibaca sekali per permintaan (cache statis); aman bila tabel belum ada atau kosong (mengembalikan bawaan).
 * Output       : get($key, $default), set($key, $value, $group), forget() (untuk tes).
 * Dipakai oleh : App\Services\CountSchedule.
 * Status       : Aktif.
 */
final class Constants
{
    /** @var array<string, ?string>|null */
    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        Constant::query()->updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value, 'group' => $group]);
        self::$cache = null;
    }

    public static function forget(): void
    {
        self::$cache = null;
    }

    /** @return array<string, ?string> */
    private static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = Schema::hasTable('sys_constant') ? Constant::query()->pluck('value', 'key')->all() : [];
        }

        return self::$cache;
    }
}
