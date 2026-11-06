<?php

namespace App\Http\Controllers;

use App\Models\FacAsset;
use App\Models\FacOrder;
use App\Services\FacCare;
use App\Services\FacCustody;

/* REMARK
 * Tujuan       : Panel Perawatan Gedung dan Aset (GA, modul FAM): dashboard (aset aktif, perlu perawatan, tiket terbuka, jadwal terlambat, biaya bulan ini), "Perlu tindakan" berwarna, grafik biaya perawatan 6 bulan,
 *                tiket per status dan per kategori; Kartu Aset (riwayat semua perawatan + total biaya, jadwal, garansi); hub Setup. Hanya-baca: tidak menulis apa pun.
 * Sumber hulu  : FacCare (dashboard, costByMonth, ticketsBy*, overview), g_fac_order, m_fac_asset.
 * Output       : layar Dashboard, Kartu Aset, Setup.
 * Dipakai oleh : routes/web.php (fac-care.index, fac-care.show, fac-setup.index).
 * Status       : Aktif.
 */
class FacCareController extends Controller
{
    public function index()
    {
        $n = FacCare::dashboard();
        $trend = FacCare::costByMonth();
        $money = fn (float $v) => 'Rp '.number_format($v, 0, ',', '.');
        $u = request()->user();

        $todo = [];
        $broken = $n['broken'];
        $urgent = FacOrder::query()->whereIn('status', FacOrder::OPEN)->where('priority', 'urgent')->count();
        $urgent && $todo[] = ['danger', $urgent, 'tiket Darurat belum selesai', 'fa-solid fa-triangle-exclamation', route('fac-orders.index')];
        $broken && $todo[] = ['danger', $broken, 'aset Rusak', 'fa-solid fa-screwdriver-wrench', route('fac-assets.index')];
        $n['late'] && $todo[] = ['danger', $n['late'], 'jadwal perawatan Terlambat', 'fa-solid fa-calendar-xmark', route('fac-plans.index')];
        $n['waiting'] && $todo[] = ['warn', $n['waiting'], 'tiket menunggu persetujuan Supervisor', 'fa-solid fa-stamp', route('fac-orders.index')];
        $n['soon'] && $todo[] = ['warn', $n['soon'], 'jadwal perawatan Segera jatuh tempo', 'fa-solid fa-calendar-day', route('fac-plans.index')];
        // Penggunaan aset: terlambat kembali dan hilang (merah), di luar kantor, dalam perbaikan, pemegang keluar, temuan opname.
        $cu = FacCustody::counts();
        $left = FacCustody::leftHolders();
        $found = FacCustody::findings()->count();
        $cu['overdue'] && array_unshift($todo, ['danger', $cu['overdue'], 'aset terlambat dikembalikan', 'fa-solid fa-clock', route('reports.fac-holders', ['late' => 1])]);
        $cu['lost'] && $todo[] = ['danger', $cu['lost'], 'aset Hilang (tindak lanjuti)', 'fa-solid fa-circle-question', route('fac-assets.index')];
        $left->isNotEmpty() && $todo[] = ['danger', $left->count(), 'karyawan sudah keluar masih memegang aset: '.$left->pluck('name')->take(3)->implode(', '), 'fa-solid fa-user-xmark', route('reports.fac-holders')];
        $found && $todo[] = ['warn', $found, 'aset tidak ditemukan saat opname', 'fa-solid fa-magnifying-glass', route('fac-checks.index')];
        $inside = $cu['out'] - $cu['overdue'];
        $inside > 0 && $todo[] = ['warn', $inside, 'aset sedang di luar kantor (masih dalam waktu)', 'fa-solid fa-house-user', route('reports.fac-holders')];
        $cu['repair'] && $todo[] = ['warn', $cu['repair'], 'aset Dalam perbaikan', 'fa-solid fa-screwdriver-wrench', route('fac-assets.index')];
        $need = $n['need'] - $broken;
        $need > 0 && $todo[] = ['warn', $need, 'aset Perlu perawatan', 'fa-solid fa-wrench', route('fac-assets.index')];

        return view('fac-care.index', [
            'stats' => [
                ['value' => (string) $n['assets'], 'label' => 'Aset aktif', 'alert' => false],
                ['value' => (string) $n['need'], 'label' => 'Perlu perawatan', 'alert' => $n['need'] > 0],
                ['value' => (string) $n['open'], 'label' => 'Tiket terbuka', 'alert' => $n['open'] > 0],
                ['value' => (string) $n['late'], 'label' => 'Jadwal terlambat', 'alert' => $n['late'] > 0],
                ['value' => $money($n['month_cost']), 'label' => 'Biaya bulan ini', 'alert' => false],
            ],
            'outside' => FacCustody::outside(8), 'todo' => $todo, 'trend' => $trend, 'trendMax' => max([1.0, ...array_column($trend, 'value')]), 'money' => $money,
            'byStatus' => FacCare::ticketsByStatus(), 'byCategory' => FacCare::ticketsByCategory(),
            'can' => fn (string $m) => $u->canAccess($m), 'write' => fn (string $m) => $u->canWrite($m),
        ]);
    }

    // Kartu Aset: data aset, jadwal, dan semua perawatan (tiket) dengan total biaya Disetujui.
    public function show(FacAsset $asset)
    {
        $asset->load('category', 'place.parent');
        $orders = FacOrder::query()->with('vendor')->where('asset_id', $asset->id)->orderByDesc('reported_at')->orderByDesc('id')->get();
        $plans = $asset->plans()->where('is_active', true)->get();
        $done = $orders->where('status', 'approved');

        return view('fac-care.show', [
            'asset' => $asset, 'orders' => $orders, 'plans' => $plans, 'over' => FacCare::overview($plans),
            'total' => $done->sum(fn (FacOrder $o) => $o->total()), 'labor' => $done->sum(fn ($o) => (float) $o->labor_cost), 'material' => $done->sum(fn ($o) => (float) $o->material_cost),
            'year' => $done->filter(fn ($o) => ($o->finished_at ?? $o->reported_at)->gte(today()->subYear()))->sum(fn (FacOrder $o) => $o->total()),
        ]);
    }

    public function setup()
    {
        return view('fac-setup.index');
    }
}
