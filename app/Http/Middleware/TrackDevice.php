<?php

namespace App\Http\Middleware;

use App\Models\UserDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/* REMARK
 * Tujuan       : Mencatat sesi login pengguna ke user_devices (Perangkat Saya). Tulis hanya bila baris belum ada atau sudah > 1 menit, jadi murah.
 * Dipakai oleh : bootstrap/app.php (grup web, sesudah sesi mulai).
 * Status       : Aktif.
 */
class TrackDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        if (($user = $request->user()) && $request->hasSession()) {
            $device = UserDevice::firstOrNew(['session_id' => $request->session()->getId()]);
            if (! $device->exists || $device->last_active_at->lt(now()->subMinute())) {
                $device->fill(['user_id' => $user->id, 'ip' => $request->ip(), 'agent' => mb_substr((string) $request->userAgent(), 0, 255), 'last_active_at' => now()]);
                $device->created_at ??= now();
                $device->save();
            }
        }

        return $next($request);
    }
}
