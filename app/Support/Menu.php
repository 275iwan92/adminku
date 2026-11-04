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
            if ($code === 'STB' || ! $user->canAccess($code) || (isset(Modules::MENU_UNDER[$code]) && collect(Modules::MENU_UNDER[$code])->contains(fn ($panel) => $user->canAccess($panel)))) {
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
        // Subkelompok Transaksi dan Laporan membuka layar pembukanya sendiri (open = alamat, code = kunci tab) saat diklik; induk di akar (Data Master, Stok, GA, HR, Purchase) memakai open yang sama, dibaca home.blade.
        $groups = collect($stok)->filter(fn ($c) => $parents->has($c))
            ->map(fn ($c) => ['label' => $parents[$c]['label'], 'icon' => $parents[$c]['icon'], 'items' => $parents[$c]['children'], 'code' => 'H-'.$c, 'open' => Opening::url($c === 'TRX' ? 'transaksi' : 'laporan')])->values()->all();

        // Induk GA: modul nyata (Perawatan Aset Kendaraan dan Perawatan AC) dari Modules, lalu layar sementara Perawatan Gedung yang belum dibuat: belum punya modul/hak Grup, jadi hanya Administrator yang melihat.
        $soon = fn (string $code, string $label, string $icon, string $name) => ['code' => $code, 'label' => $label, 'url' => route('soon', $name), 'type' => 'form', 'icon' => $icon];
        $out = [];
        foreach ($parents as $code => $p) {
            $p['open'] = ['MST' => Opening::url('master'), 'GA' => Opening::url('ga'), 'HR' => Opening::url('hr'), 'PUR' => Opening::url('purchase'), 'KAS' => Opening::url('kas')][$code] ?? null;
            if ($code === 'GA' && $user->isAdministrator()) {
                $p['children'] = [...$p['children'], $soon('GA-BLD', 'Perawatan Gedung', 'fa-solid fa-building', 'gedung')];
            }
            if ($code === 'HR') {   // Karyawan nyata (HRE), lalu Bagian = layar Departemen (hak DEP); Rekrutmen dan Kedisiplinan dari Modules.
                $bagian = $user->canAccess('DEP') ? [['code' => 'HR-DEP', 'label' => 'Bagian', 'url' => route('departments.index'), 'type' => 'grid', 'icon' => 'fa-solid fa-sitemap']] : [];
                $own = collect($p['children']);   // urutan: Karyawan, Bagian, Rekrutmen, Kedisiplinan
                $p['children'] = [...$own->where('code', 'HRE')->all(), ...$bagian, ...$own->where('code', '!=', 'HRE')->all()];
            }
            if (! in_array($code, $stok)) {
                $out[] = $p;
            } elseif ($groups && ! collect($out)->contains('code', 'STK')) {
                $out[] = ['code' => 'STK', 'short' => 'STK', 'label' => 'Stok', 'icon' => 'fa-solid fa-boxes-packing', 'open' => $user->canAccess('STB') ? route('stock.home') : null, 'children' => $groups];
            }
        }

        return $out;
    }
}
