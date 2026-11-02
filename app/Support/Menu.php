<?php

namespace App\Support;

use App\Models\User;
use App\Services\CountSchedule;

/* REMARK
 * Tujuan       : Menu Layer Shell dari daftar modul (App\Support\Modules), disaring menurut akses pengguna (peran + Grup). Modul tanpa akses tidak tampil.
 * Output       : modules() = daftar induk datar (HP, tes); layers() = array $modules untuk x-dl::layer-shell desktop (Stok membungkus Transaksi + Laporan) (code item dipakai juga oleh ?open= dan data-open-tab).
 * Dipakai oleh : HomeController.
 * Status       : Aktif.
 */
final class Menu
{
    public static function modules(User $user): array
    {
        $parents = [];
        foreach (Modules::all() as $code => [$parent, $label, $icon, $type, $route]) {
            if (! $user->canAccess($code)) {
                continue;
            }
            $url = is_array($route) ? route($route[0], $route[1]) : route($route);
            $badge = $code === 'SO' ? CountSchedule::badge() : null;   // lencana: Stok Opname sedang berjalan atau terlambat
            $parents[$parent][] = ['code' => $code, 'label' => $label, 'url' => $url, 'type' => $type, 'icon' => $icon]
                + ($badge ? ['badge' => $badge['badge'], 'badgeTone' => $badge['tone'], 'badgeTitle' => $badge['title']] : []);
        }

        if ($user->isSuperAdmin()) {   // menu super admin: tidak ada di Modules / matriks Grup, jadi tidak terlihat siapa pun yang lain
            $parents['SEC'][] = ['code' => 'LOG', 'label' => 'Log Aktivitas', 'url' => route('super.log'), 'type' => 'grid', 'icon' => 'fa-solid fa-clock-rotate-left'];
            $parents['SEC'][] = ['code' => 'UAT', 'label' => 'Akun UAT', 'url' => route('super.accounts'), 'type' => 'grid', 'icon' => 'fa-solid fa-key'];
        }

        $out = [];
        foreach (Modules::PARENTS as $code => [$label, $icon]) {
            if (isset($parents[$code])) {
                $out[] = ['code' => $code, 'short' => $code, 'label' => $label, 'icon' => $icon, 'pin' => $code === 'SEC', 'children' => $parents[$code]];
            }
        }

        return $out;
    }

    // Menu desktop: Transaksi dan Laporan jadi dua subkelompok di bawah satu induk "Stok" (Data Master, Stok, Keamanan). Kode modul dan hak akses tidak berubah; HP tetap memakai modules().
    public static function layers(User $user): array
    {
        $stok = ['TRX', 'RPT'];
        $parents = collect(self::modules($user))->keyBy('code');
        $groups = collect($stok)->filter(fn ($c) => $parents->has($c))
            ->map(fn ($c) => ['label' => $parents[$c]['label'], 'icon' => $parents[$c]['icon'], 'items' => $parents[$c]['children']])->values()->all();

        // Induk GA: modul nyata (Perawatan Aset Kendaraan) dari Modules, lalu layar sementara Perawatan Gedung dan Perawatan AC yang belum dibuat (muncul bagi siapa pun yang punya modul GA; Administrator selalu).
        $soon = fn (string $code, string $label, string $icon, string $name) => ['code' => $code, 'label' => $label, 'url' => route('soon', $name), 'type' => 'form', 'icon' => $icon];
        $out = [];
        foreach ($parents as $code => $p) {
            if ($code === 'GA') {
                $p['children'] = [...$p['children'], $soon('GA-BLD', 'Perawatan Gedung', 'fa-solid fa-building', 'gedung'), $soon('GA-AC', 'Perawatan AC', 'fa-solid fa-snowflake', 'ac')];
            }
            if (! in_array($code, $stok)) {
                $out[] = $p;
            } elseif ($groups && ! collect($out)->contains('code', 'STK')) {
                $out[] = ['code' => 'STK', 'short' => 'STK', 'label' => 'Stok', 'icon' => 'fa-solid fa-boxes-packing', 'children' => $groups];
            }
        }

        // Modul Jays berikutnya (belum dibuat): HR berisi Employee dan Recruitment, Purchase berisi PR, PO, PV. Hanya Administrator yang melihat.
        if ($user->isAdministrator()) {
            $next = [
                ['code' => 'HR', 'short' => 'HR', 'label' => 'HR', 'icon' => 'fa-solid fa-user-tie', 'children' => [
                    $soon('HR-EMP', 'Employee', 'fa-solid fa-id-card', 'employee'),
                    $soon('HR-REC', 'Recruitment', 'fa-solid fa-user-plus', 'recruitment'),
                ]],
                ['code' => 'PUR', 'short' => 'PUR', 'label' => 'Purchase', 'icon' => 'fa-solid fa-cart-shopping', 'children' => [
                    $soon('PUR-PR', 'Permohonan Pembelian (PR)', 'fa-solid fa-file-pen', 'pr'),
                    $soon('PUR-PO', 'Purchase Order (PO)', 'fa-solid fa-file-invoice', 'po'),
                    $soon('PUR-PV', 'Penerimaan Pembelian (PV)', 'fa-solid fa-truck-ramp-box', 'pv'),
                ]],
            ];
            $at = collect($out)->search(fn ($p) => $p['code'] === 'SEC');
            array_splice($out, $at === false ? count($out) : $at, 0, $next);
        }

        return $out;
    }
}
