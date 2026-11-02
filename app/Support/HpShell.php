<?php

namespace App\Support;

use App\Models\User;
use Devlib\Device;

/* REMARK
 * Tujuan       : Data kerangka tampilan HP (x-dl::phone-shell): menu utama besar di panel Menu (Beranda, Stok, Transaksi, Opname; tombol kelima Menu = semua modul) dan modul panel Menu, disaring menurut akses pengguna.
 *                Modul yang belum punya tampilan HP dibuka lewat hp.open (halaman desktop di dalam kerangka); Stok Opname punya tampilan HP sendiri (hp.counts).
 * Sumber hulu  : Modules (daftar modul), Menu (modul menurut akses), User::canAccess.
 * Output       : tabs(), modules(), url(kode), tabFor(kode).
 * Dipakai oleh : view hp.layout, HpController.
 * Status       : Aktif.
 */
final class HpShell
{
    // Modul yang punya tampilan HP sendiri: kode => nama rute.
    private const OWN = ['SO' => 'hp.counts', 'STB' => 'stock.home'];

    // Perangkat ini ponsel asli menurut UA (bukan jendela komputer yang dipersempit): ponsel selalu Tampilan HP, tanpa tombol Tampilan Desktop.
    public static function isRealPhone(): bool
    {
        return Device::detect(request()->userAgent(), request()->header('Sec-CH-UA-Mobile')) === Device::PHONE;
    }

    // Alamat peralihan tampilan untuk x-dl::phone-shell / phone-login: ponsel asli tidak punya (null = tombol tidak digambar); jendela komputer yang sempit tetap perlu jalan kembali ke desktop.
    public static function deviceAction(): ?string
    {
        return self::isRealPhone() ? null : route('ui.set');
    }

    public static function url(string $code): string
    {
        return isset(self::OWN[$code]) ? route(self::OWN[$code]) : route('hp.open', $code);
    }

    // Menu::modules dengan alamat tiap modul diganti alamat tampilan HP.
    public static function modules(User $user): array
    {
        return array_map(fn (array $parent) => ['children' => array_map(fn (array $m) => ['url' => self::url($m['code'])] + $m, $parent['children'])] + $parent, Menu::modules($user));
    }

    public static function tabs(User $user): array
    {
        $stok = collect(['R-SUM', 'ITM'])->first(fn ($c) => $user->canAccess($c));
        $tabs = [
            ['key' => 'home', 'label' => 'Beranda', 'icon' => 'fa-solid fa-house', 'url' => route('home')],
            $stok ? ['key' => 'stock', 'label' => 'Stok', 'icon' => 'fa-solid fa-boxes-stacked', 'url' => self::url($stok)] : null,
            ['key' => 'trx', 'label' => 'Transaksi', 'icon' => 'fa-solid fa-right-left', 'url' => route('hp.group', 'TRX')],
            $user->canAccess('SO') ? ['key' => 'count', 'label' => 'Opname', 'icon' => 'fa-solid fa-clipboard-check', 'url' => route('hp.counts')] : null,
        ];

        return array_values(array_filter($tabs));
    }

    // Menu utama yang ditandai untuk satu modul: Ringkasan Stok / Barang = Stok, modul Transaksi = Transaksi, Stok Opname = Opname, Laporan = Laporan, sisanya tidak ada.
    public static function tabFor(string $code): string
    {
        return match (true) {
            in_array($code, ['R-SUM', 'ITM'], true) => 'stock',
            $code === 'SO' => 'count',
            (Modules::all()[$code][0] ?? null) === 'TRX' => 'trx',
            (Modules::all()[$code][0] ?? null) === 'RPT' => 'report',
            default => '',
        };
    }
}
