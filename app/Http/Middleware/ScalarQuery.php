<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/* REMARK
 * Tujuan       : Parameter query (?q=, ?from=, ?month=, ...) selalu teks: nilai bertipe array (?from[]=x) dibuang pada permintaan GET, supaya tidak ada layar yang memperlakukannya sebagai teks lalu berakhir 500
 *                ("Array to string conversion"). Tidak ada rute GET yang memakai query berbentuk array; isian formulir (POST / PUT) tidak disentuh.
 * Dipakai oleh : bootstrap/app.php (grup web).
 * Status       : Aktif.
 */
class ScalarQuery
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET') && array_filter($request->query->all(), 'is_array')) {
            $request->query->replace(array_filter($request->query->all(), fn ($v) => ! is_array($v)));
        }

        return $next($request);
    }
}
