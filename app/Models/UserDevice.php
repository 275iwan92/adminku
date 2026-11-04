<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/* REMARK
 * Tujuan       : Satu sesi login pengguna (fasilitas Perangkat Saya): nama perangkat dari User-Agent, IP, terakhir aktif.
 * Dipakai oleh : TrackDevice, DeviceController, AuthController (logout).
 * Status       : Aktif.
 */
class UserDevice extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_active_at' => 'datetime', 'created_at' => 'datetime'];
    }

    // "Chrome di Windows": cukup untuk dikenali pemiliknya; urutan pola penting (Edge/Opera memuat kata Chrome, Chrome memuat Safari).
    public static function describe(?string $agent): string
    {
        $agent = (string) $agent;
        $browser = collect(['Edg' => 'Edge', 'OPR' => 'Opera', 'Firefox' => 'Firefox', 'Chrome' => 'Chrome', 'Safari' => 'Safari'])->first(fn ($n, $k) => str_contains($agent, $k)) ?? 'Browser';
        $os = collect(['Windows' => 'Windows', 'Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Mac OS' => 'Mac', 'Linux' => 'Linux'])->first(fn ($n, $k) => str_contains($agent, $k));

        return $browser.($os ? " di {$os}" : '');
    }
}
