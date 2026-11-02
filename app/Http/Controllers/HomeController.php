<?php

namespace App\Http\Controllers;

use App\Support\HomeCards;
use App\Support\Menu;
use Devlib\Device;
use Illuminate\Http\Request;

/* REMARK
 * Tujuan       : Layar awal setelah login (semua peran): Layer Shell (menu per peran) dengan Beranda global: banner sapaan dan satu kartu ringkas per modul yang boleh dilihat pengguna.
 *                Isi khusus Stok (angka stok, titik pesan ulang, kedaluwarsa, pengingat opname) ada di Beranda Stok (StockHomeController).
 * Sumber hulu  : HomeCards::page (banner + kartu modul), Menu::layers.
 * Output       : view home (desktop) atau hp.home lewat HpController (HP).
 * Dipakai oleh : route home (tujuan redirect login).
 * Status       : Aktif.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        if (Device::view($request) === Device::PHONE) {
            return app(HpController::class)->home($request);
        }
        $user = $request->user();

        return view('home', HomeCards::page($user) + [
            'modules' => Menu::layers($user),
            'user' => ['name' => $user->name, 'email' => $user->email, 'role' => $user->roleLabel()],
            'open' => $request->query('open'),
            'fresh' => (bool) session('fresh'),
        ]);
    }
}
