<?php

namespace App\Http\Controllers;

use App\Models\FacCategory;
use App\Models\FacOrder;
use App\Models\FacPlace;
use Devlib\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/* REMARK
 * Tujuan       : Laporan Biaya Perawatan (GA, modul FAR): tiket Perintah Kerja yang Disetujui dalam periode (menurut tanggal selesai), dikelompokkan per aset / gedung / kategori, dengan biaya tenaga, material, dan total. Ekspor lewat komponen laporan DevLib.
 * Sumber hulu  : g_fac_order (Disetujui), m_fac_asset, m_fac_place, m_fac_category.
 * Output       : view reports.fac-cost.
 * Dipakai oleh : routes/web.php (reports.fac-cost).
 * Status       : Aktif.
 */
class FacReportController extends Controller
{
    public function cost(Request $request)
    {
        $parse = fn (?string $v, Carbon $default) => rescue(fn () => $v ? Carbon::parse($v) : $default, $default, false);
        $from = $parse($request->query('from'), now()->startOfYear())->startOfDay();
        $to = $parse($request->query('to'), now())->endOfDay();
        $to = $to->lt($from) ? $from->copy()->endOfDay() : $to;
        $category = $request->integer('category');
        $building = $request->integer('building');

        $rows = FacOrder::query()->with('asset.category', 'place.parent', 'vendor')->where('status', 'approved')
            ->whereRaw('date(coalesce(finished_at, reported_at)) between ? and ?', [$from->toDateString(), $to->toDateString()])
            ->when($category, fn ($q) => $q->whereHas('asset', fn ($a) => $a->where('category_id', $category)))
            ->when($building, fn ($q) => $q->whereHas('place', fn ($p) => $p->where('id', $building)->orWhere('parent_id', $building)))
            ->get()->sortBy(fn ($o) => ($o->finished_at ?? $o->reported_at)->format('Y-m-d').$o->id)->map(function (FacOrder $o) {
                $place = $o->place;

                return [
                    'date' => Locale::d($o->finished_at ?? $o->reported_at), 'no' => $o->number(), 'title' => $o->title,
                    'asset' => $o->asset ? $o->asset->asset_code.' · '.$o->asset->name : 'Tanpa aset ('.($place?->name ?? '-').')',
                    'building' => $place ? ($place->parent?->name ?? $place->name) : 'Tanpa gedung', 'category' => $o->asset?->category?->name ?? 'Tanpa kategori',
                    'handler' => $o->handler === 'vendor' ? ($o->vendor?->name ?? 'Vendor') : 'Internal: '.($o->technician ?: '-'),
                    'labor' => (float) $o->labor_cost, 'material' => (float) $o->material_cost, 'total' => $o->total(),
                ];
            })->values()->all();

        return view('reports.fac-cost', [
            'from' => $from, 'to' => $to, 'rows' => $rows, 'category' => $category, 'building' => $building,
            'categories' => FacCategory::query()->orderBy('name')->pluck('name', 'id')->all(),
            'buildings' => FacPlace::query()->whereNull('parent_id')->orderBy('name')->pluck('name', 'id')->all(),
            'count' => count($rows),
        ]);
    }
}
