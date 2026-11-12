<?php

namespace App\Http\Middleware;

use App\Support\HpShell;
use Closure;
use Devlib2\Device;
use Illuminate\Http\Request;

/* REMARK
 * Tujuan       : Ponsel asli selalu Tampilan HP sampai keluar atau ganti perangkat: cookie ui=desktop (sisa pilihan lama) di ponsel diabaikan di setiap permintaan.
 *                Tombol Tampilan Desktop tidak digambar di ponsel (HpShell::deviceAction); middleware ini menutup jalan lain (cookie lama, POST /ui langsung).
 *                Sebaliknya, peramban komputer (UA dikenali, bukan ponsel) dengan cookie ui=phone sisa pilihan manual lama (tanpa penanda ui_auto) dilepas dari tampilan HP: tombol kembali sudah tidak ada, jadi cookie itu dibuang
 *                dari permintaan ini dan dihapus dari peramban. Jendela komputer yang menyempit pindah sendiri (ui_auto=1) dan tidak tersentuh.
 * Sumber hulu  : Devlib2\Device (nama cookie ui), HpShell::isRealPhone (UA ponsel).
 * Output       : cookie ui dibuang dari permintaan ini bila bernilai desktop di ponsel, atau bernilai phone sisa pilihan manual di komputer.
 * Dipakai oleh : bootstrap/app.php (grup web).
 * Status       : Aktif.
 */
class PhoneStaysPhone
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->cookie(Device::COOKIE) === Device::DESKTOP && HpShell::isRealPhone()) {
            $request->cookies->remove(Device::COOKIE);
        }

        $cookie = $request->cookie(Device::COOKIE);
        $staleChoice = Device::normalize($cookie) === Device::PHONE && ! Device::autoChosen($request) && ! HpShell::isRealPhone() && str_starts_with((string) $request->userAgent(), 'Mozilla/');
        if ($staleChoice) {
            $request->cookies->remove(Device::COOKIE);
        }

        $response = $next($request);
        if ($staleChoice) {
            $response->headers->clearCookie(Device::COOKIE);
        }

        return $response;
    }
}
