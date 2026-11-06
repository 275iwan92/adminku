<?php

namespace App\Http\Controllers;

use App\Models\FacAsset;
use App\Models\FacOrder;
use App\Services\FacCare;

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
            'todo' => $todo, 'trend' => $trend, 'trendMax' => max([1.0, ...array_column($trend, 'value')]), 'money' => $money,
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
