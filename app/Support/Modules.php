<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Routing\Route;

/* REMARK
 * Tujuan       : Daftar modul (layar menu) aplikasi, satu-satunya sumber untuk: menu Layer Shell, pengaturan akses Grup terhadap Modul, daftar modul, dan penjagaan rute.
 *                Akses = peran cukup (min) DAN modul dicentang di Grup pengguna; pengguna tanpa Grup memakai bawaan peran. Administrator selalu penuh.
 * Output       : all() (kode => definisi), defaults($level), forRoute(Route), PARENTS.
 * Dipakai oleh : Menu, User::canAccess, EnsureModuleAccess, GroupController, ModuleController.
 * Status       : Aktif.
 */
final class Modules
{
    // min = peran paling rendah yang boleh memakai modul: entry < supervisor < administrator.
    public const LEVELS = ['entry' => 1, 'supervisor' => 2, 'administrator' => 3];

    public const PARENTS = [
        'MST' => ['Data Master', 'fa-solid fa-boxes-stacked'],
        'TRX' => ['Transaksi', 'fa-solid fa-right-left'],
        'RPT' => ['Laporan', 'fa-solid fa-chart-column'],
        'GA' => ['Personalia', 'fa-solid fa-building-user'],
        'HR' => ['SDM', 'fa-solid fa-user-tie'],
        'PUR' => ['Pembelian', 'fa-solid fa-cart-shopping'],
        'DLV' => ['Pengiriman', 'fa-solid fa-route'],
        'KEU' => ['Keuangan', 'fa-solid fa-building-columns'],
        'SEC' => ['Keamanan', 'fa-solid fa-shield-halved'],
    ];

    // Modul GA yang dibuka lewat tombol di panel (kode => panel yang membukanya: GVM Perawatan Kendaraan, GAM Perawatan AC) dan tidak tampil di menu samping bagi yang punya salah satu panelnya;
    // pengguna tanpa panel (mis. sopir hanya GVO) tetap melihatnya. Pengaturan GA (GAP) dibuka dari Setup kedua panel. Hak Grup dan penjagaan rute tetap per kode.
    public const MENU_UNDER = [
        'GVD' => ['GVM'], 'GVS' => ['GVM'], 'GVO' => ['GVM'], 'GVK' => ['GVM'], 'GVT' => ['GVM'],
        'GAS' => ['GAM'], 'GAV' => ['GAM'], 'GAK' => ['GAM'], 'GAA' => ['GAM'], 'GAR' => ['GAM'], 'GAE' => ['GAM'],
        'GAP' => ['GVM', 'GAM'],
        'KSR' => ['KSM', 'KSK'],
    ];

    // kode => [induk, label, ikon, tipe tab, nama rute daftar, peran minimal, awalan rute yang dijaga]
    public static function all(): array
    {
        return [
            'ITM' => ['MST', 'Barang', 'fa-solid fa-box', 'grid', 'products.index', 'entry', ['products.', 'hp.find']],
            'SUP' => ['MST', 'Pemasok', 'fa-solid fa-truck', 'grid', 'suppliers.index', 'entry', ['suppliers.']],
            'DEP' => ['MST', 'Departemen', 'fa-solid fa-building', 'grid', 'departments.index', 'entry', ['departments.']],
            'RCP' => ['MST', 'Penerima', 'fa-solid fa-user-tag', 'grid', 'recipients.index', 'entry', ['recipients.']],
            'UOM' => ['MST', 'Satuan', 'fa-solid fa-ruler', 'grid', 'units.index', 'supervisor', ['units.']],
            'CAT' => ['MST', 'Kategori', 'fa-solid fa-tags', 'grid', 'categories.index', 'supervisor', ['categories.']],
            'LOC' => ['MST', 'Lokasi Penyimpanan', 'fa-solid fa-warehouse', 'grid', 'locations.index', 'supervisor', ['locations.']],
            'CMP' => ['MST', 'Perusahaan', 'fa-solid fa-city', 'form', 'company.edit', 'administrator', ['company.edit', 'company.update']],
            'OPN' => ['TRX', 'Saldo Awal', 'fa-solid fa-flag-checkered', 'grid', ['docs.index', 'opening'], 'supervisor', []],
            'RCV' => ['TRX', 'Penerimaan Barang', 'fa-solid fa-truck-ramp-box', 'grid', ['docs.index', 'receiving'], 'entry', []],
            'ISS' => ['TRX', 'Pengeluaran Barang', 'fa-solid fa-dolly', 'grid', ['docs.index', 'issuance'], 'entry', []],
            'ADJ' => ['TRX', 'Penyesuaian Stok', 'fa-solid fa-scale-balanced', 'grid', ['docs.index', 'adjustment'], 'supervisor', []],
            'SO' => ['TRX', 'Stok Opname', 'fa-solid fa-clipboard-check', 'grid', 'stock-counts.index', 'entry', ['stock-counts.', 'hp.count']],
            'ORD' => ['TRX', 'Riwayat Pemesanan', 'fa-solid fa-clock-rotate-left', 'grid', 'reorder-orders.index', 'entry', ['reorder-orders.']],
            'SJL' => ['DLV', 'Surat Jalan', 'fa-solid fa-file-lines', 'grid', 'delivery-notes.index', 'entry', ['delivery-notes.']],
            'PGK' => ['DLV', 'Perencanaan Pengiriman', 'fa-solid fa-route', 'grid', 'trips.index', 'entry', ['trips.']],
            'STB' => ['RPT', 'Beranda Stok', 'fa-solid fa-gauge-high', 'form', 'stock.home', 'entry', ['stock.']],
            'R-CARD' => ['RPT', 'Kartu Stok', 'fa-solid fa-clipboard-list', 'report', 'reports.stock-card', 'entry', ['reports.stock-card']],
            'R-SUM' => ['RPT', 'Ringkasan Stok', 'fa-solid fa-table-list', 'report', 'reports.summary', 'entry', ['reports.summary']],
            'R-IN' => ['RPT', 'Laporan Penerimaan', 'fa-solid fa-file-arrow-down', 'report', 'reports.receiving', 'entry', ['reports.receiving']],
            'R-OUT' => ['RPT', 'Laporan Pengeluaran', 'fa-solid fa-file-arrow-up', 'report', 'reports.issuance', 'entry', ['reports.issuance']],
            'R-EXP' => ['RPT', 'Laporan Kedaluwarsa', 'fa-solid fa-hourglass-end', 'report', 'reports.expiry', 'entry', ['reports.expiry']],
            'R-ADJ' => ['RPT', 'Laporan Opname & Penyesuaian', 'fa-solid fa-file-circle-check', 'report', 'reports.adjustment', 'supervisor', ['reports.adjustment']],
            'GVM' => ['GA', 'Perawatan Kendaraan', 'fa-solid fa-car-side', 'grid', 'fleet.index', 'entry', ['fleet.']],
            'GVD' => ['GA', 'Perpanjang Dokumen Kendaraan', 'fa-solid fa-file-signature', 'form', 'vehicle-docs.create', 'entry', ['vehicle-docs.']],
            'GVS' => ['GA', 'Catat Servis Kendaraan', 'fa-solid fa-screwdriver-wrench', 'form', 'vehicle-services.create', 'entry', ['vehicle-services.']],
            'GVO' => ['GA', 'Catat Odometer', 'fa-solid fa-gauge-high', 'form', 'vehicle-odometers.create', 'entry', ['vehicle-odometers.']],
            'GVK' => ['GA', 'Master Kendaraan', 'fa-solid fa-truck-pickup', 'grid', 'vehicles.index', 'entry', ['vehicles.']],
            'GVT' => ['GA', 'Jenis Kendaraan', 'fa-solid fa-list-check', 'grid', 'vehicle-types.index', 'entry', ['vehicle-types.']],
            'GAM' => ['GA', 'Perawatan AC', 'fa-solid fa-snowflake', 'grid', 'ac-care.index', 'entry', ['ac-care.', 'ac-setup.']],
            'GAS' => ['GA', 'Penjadwalan AC', 'fa-solid fa-calendar-check', 'form', 'ac-schedule.index', 'entry', ['ac-schedule.']],
            'GAV' => ['GA', 'Kunjungan AC', 'fa-solid fa-truck-fast', 'grid', 'ac-visits.index', 'entry', ['ac-visits.']],
            'GAK' => ['GA', 'Master AC', 'fa-solid fa-snowflake', 'grid', 'ac-units.index', 'entry', ['ac-units.']],
            'GAA' => ['GA', 'Area dan Gedung AC', 'fa-solid fa-building', 'grid', 'ac-areas.index', 'entry', ['ac-areas.']],
            'GAR' => ['GA', 'Ruang AC', 'fa-solid fa-door-open', 'grid', 'ac-rooms.index', 'entry', ['ac-rooms.']],
            'GAE' => ['GA', 'Vendor AC', 'fa-solid fa-handshake', 'grid', 'ac-vendors.index', 'entry', ['ac-vendors.']],
            'GAP' => ['GA', 'Pengaturan GA', 'fa-solid fa-sliders', 'form', 'ga-settings.edit', 'supervisor', ['ga-settings.']],
            'HRE' => ['HR', 'Karyawan', 'fa-solid fa-id-card', 'grid', 'employees.index', 'supervisor', ['employees.']],
            'HRR' => ['HR', 'Rekrutmen', 'fa-solid fa-user-plus', 'grid', 'candidates.index', 'supervisor', ['candidates.', 'recruit-steps.']],
            'HRD' => ['HR', 'Kedisiplinan', 'fa-solid fa-gavel', 'grid', 'disciplines.index', 'supervisor', ['disciplines.']],
            'PUP' => ['PUR', 'Permintaan Pembelian', 'fa-solid fa-file-pen', 'grid', 'pr.index', 'entry', ['pr.']],
            'PUK' => ['PUR', 'Keputusan Pesanan', 'fa-solid fa-scale-balanced', 'form', 'po-decision.index', 'entry', ['po-decision.']],
            'PUO' => ['PUR', 'Pesanan Pembelian (PO)', 'fa-solid fa-file-invoice', 'grid', 'po.index', 'entry', ['po.']],
            'PUV' => ['PUR', 'Penerimaan Pembelian (PV)', 'fa-solid fa-truck-ramp-box', 'grid', 'pv.index', 'entry', ['pv.']],
            'KSM' => ['KEU', 'Uang Masuk', 'fa-solid fa-arrow-down-to-bracket', 'grid', 'cash-in.index', 'entry', ['cash-in.', 'cash-sources.']],
            'KSK' => ['KEU', 'Uang Keluar', 'fa-solid fa-arrow-up-from-bracket', 'grid', 'cash-out.index', 'entry', ['cash-out.', 'cash-purposes.']],
            'KSR' => ['KEU', 'Kas / Rekening', 'fa-solid fa-building-columns', 'grid', 'cash-accounts.index', 'supervisor', ['cash-accounts.']],
            'KSB' => ['KEU', 'Kartu Saldo Kas', 'fa-solid fa-id-card', 'report', 'cash-card.index', 'entry', ['cash-card.']],
            'R-KFLOW' => ['KEU', 'Laporan Arus Kas', 'fa-solid fa-book', 'report', 'reports.cash-flow', 'entry', ['reports.cash-flow']],
            'R-KIN' => ['KEU', 'Laporan Penerimaan', 'fa-solid fa-file-arrow-down', 'report', 'reports.cash-receipts', 'entry', ['reports.cash-receipts']],
            'R-KOUT' => ['KEU', 'Laporan Pengeluaran', 'fa-solid fa-file-arrow-up', 'report', 'reports.cash-disbursements', 'entry', ['reports.cash-disbursements']],
            'USR' => ['SEC', 'Pengguna', 'fa-solid fa-users-gear', 'grid', 'users.index', 'administrator', ['users.']],
            'GRP' => ['SEC', 'Grup', 'fa-solid fa-people-group', 'grid', 'groups.index', 'administrator', ['groups.']],
            'SMP' => ['SEC', 'Data Contoh', 'fa-solid fa-rotate', 'grid', 'sample.index', 'administrator', ['sample.']],
            'MOD' => ['SEC', 'Daftar Modul', 'fa-solid fa-table-list', 'grid', 'modules.index', 'administrator', ['modules.']],
        ];
    }

    public static function levelOf(User $user): string
    {
        return $user->isAdministrator() ? 'administrator' : ($user->isSupervisor() ? 'supervisor' : 'entry');
    }

    public static function levelAllows(string $min, string $level): bool
    {
        return self::LEVELS[$level] >= self::LEVELS[$min];
    }

    // Modul bawaan satu peran (dipakai Grup bawaan dan pengguna tanpa Grup). Keamanan, GA, dan HR tidak otomatis: GA dan HR hanya lewat hak Grup (Administrator penuh).
    public static function defaults(string $level): array
    {
        return array_keys(array_filter(self::all(), fn ($m) => ! in_array($m[0], ['SEC', 'GA', 'HR', 'PUR', 'KEU'], true) && self::levelAllows($m[5], $level)));
    }

    // Kode modul yang menjaga rute ini (null = rute bebas, mis. beranda, logout, lookup).
    public static function forRoute(Route $route): ?string
    {
        $name = (string) $route->getName();
        if (str_starts_with($name, 'docs.')) {
            return ['opening' => 'OPN', 'receiving' => 'RCV', 'issuance' => 'ISS', 'adjustment' => 'ADJ'][$route->parameter('type')] ?? null;
        }
        foreach (self::all() as $code => $m) {
            foreach ($m[6] as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    return $code;
                }
            }
        }

        return null;
    }
}
