<?php

namespace App\Http\Controllers;

use App\Support\Opening;
use Devlib2\Device;
use Illuminate\Http\Request;

/* REMARK
 * Tujuan       : Layar pembuka kelompok menu / modul (Data Master, Transaksi, Laporan, GA, HR, Purchase), dibuka dengan mengklik induknya di panel kiri (desktop, sebagai tab) atau di menu HP.
 *                Isinya hanya yang boleh dilihat pengguna; tanpa satu pun butir yang boleh = 403.
 * Sumber hulu  : App\Support\Opening::build.
 * Output       : view opening (desktop) atau hp.opening (HP).
 * Dipakai oleh : route opening; Menu::layers (kunci open pada induk), HpController::group.
 * Status       : Aktif.
 */
class OpeningController extends Controller
{
    public function __invoke(Request $request, string $section)
    {
        $page = Opening::build($section, $request->user());
        abort_if($page === null, 403, 'Anda tidak punya akses ke layar ini.');

        return Device::view($request) === Device::PHONE
            ? view('hp.opening', ['title' => $page['title'], 'active' => ['transaksi' => 'trx', 'laporan' => 'report'][$section] ?? '', 'back' => route('home'), 'page' => $page])
            : view('opening', ['page' => $page]);
    }
}
