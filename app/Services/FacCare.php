<?php

namespace App\Services;

use App\Models\FacAsset;
use App\Models\FacOrder;
use App\Models\FacPlan;
use App\Support\Constants;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Hitungan GA Perawatan Gedung dan Aset di satu tempat: label dan nada (periode, kondisi, prioritas, status), jatuh tempo jadwal berkala (tanggal berikutnya otomatis; Aman / Segera / Terlambat seperti AcCare),
 *                angka dashboard, biaya perawatan per bulan / aset / gedung (hanya Perintah Kerja Disetujui, menurut tanggal selesai), dan penutupan jadwal saat tiketnya disetujui.
 * Sumber hulu  : Constants (ga.fac.soon_days = 14), m_fac_*, g_fac_*.
 * Output       : overview(), dashboard(), costByMonth(), ticketsBy*(), onApproved().
 * Dipakai oleh : FacCareController, FacOrderController, FacPlanController, FacReportController, HomeCards, Opening, FacSeeder.
 * Status       : Aktif.
 */
final class FacCare
{
    public const PERIODS = ['weekly' => 'Mingguan', 'monthly' => 'Bulanan', 'quarterly' => 'Triwulan', 'yearly' => 'Tahunan'];

    public const CONDITIONS = ['baik' => 'Baik', 'perlu' => 'Perlu perawatan', 'rusak' => 'Rusak', 'dihapus' => 'Dihapus'];

    public const CONDITION_TONES = ['Baik' => 'success', 'Perlu perawatan' => 'warn', 'Rusak' => 'danger', 'Dihapus' => 'neutral'];

    public const PRIORITIES = ['low' => 'Rendah', 'normal' => 'Normal', 'high' => 'Tinggi', 'urgent' => 'Darurat'];

    public const PRIORITY_TONES = ['Rendah' => 'neutral', 'Normal' => 'info', 'Tinggi' => 'warn', 'Darurat' => 'danger'];

    public const STATUS = ['draft' => 'Draf', 'approval' => 'Diajukan', 'approved' => 'Selesai', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'];

    public const STATUS_TONES = ['Draf' => 'neutral', 'Diajukan' => 'warn', 'Selesai' => 'success', 'Ditolak' => 'danger', 'Dibatalkan' => 'danger'];

    public const DUE = ['aman' => 'Aman', 'segera' => 'Segera', 'terlambat' => 'Terlambat'];

    public const DUE_TONES = ['Aman' => 'success', 'Segera' => 'warn', 'Terlambat' => 'danger'];

    public static function soonDays(): int
    {
        return max(0, (int) Constants::get('ga.fac.soon_days', 14));
    }

    /** Tanggal jatuh tempo berikutnya: belum pernah dikerjakan = tanggal mulai; selesai terakhir + periode bila sudah. */
    public static function nextDue(FacPlan $p): Carbon
    {
        if (! $p->last_done_at) {
            return Carbon::parse($p->start_at->toDateString());
        }
        $d = Carbon::parse($p->last_done_at->toDateString());

        return match ($p->period) {
            'weekly' => $d->addWeek(),
            'quarterly' => $d->addMonthsNoOverflow(3),
            'yearly' => $d->addYearNoOverflow(),
            default => $d->addMonthNoOverflow(),
        };
    }

    /**
     * Status tiap jadwal.
     *
     * @param  Collection<int, FacPlan>  $plans
     * @return array<int, array{status: string, label: string, days: int, due: Carbon, due_text: string}>
     */
    public static function overview(Collection $plans, ?CarbonInterface $today = null): array
    {
        $today ??= today();
        $out = [];
        foreach ($plans as $p) {
            $due = self::nextDue($p);
            $days = VehicleCare::days($today, $due);
            $status = $days < 0 ? 'terlambat' : ($days <= self::soonDays() ? 'segera' : 'aman');
            $out[$p->id] = ['status' => $status, 'label' => self::DUE[$status], 'days' => $days, 'due' => $due, 'due_text' => VehicleCare::daysText($days)];
        }

        return $out;
    }

    /** Jadwal aktif beserta statusnya (relasi dimuat bila $relations, untuk layar daftar). */
    public static function activePlans(bool $relations = true): array
    {
        $plans = FacPlan::query()->when($relations, fn ($q) => $q->with('asset', 'place.parent', 'vendor'))->where('is_active', true)->get();

        return ['plans' => $plans, 'over' => self::overview($plans)];
    }

    /** Angka dashboard (kueri tetap: 4, atau 5 dengan biaya bulan ini). */
    public static function dashboard(bool $withCost = true): array
    {
        $a = FacAsset::query()->where('is_active', true)->selectRaw('count(*) n, sum(cond = ?) perlu, sum(cond = ?) rusak', ['perlu', 'rusak'])->first();
        $by = collect(self::activePlans(false)['over'])->countBy('status');
        $t = FacOrder::query()->whereIn('status', FacOrder::OPEN)->selectRaw('count(*) open, sum(status = ?) waiting', ['approval'])->first();

        return [
            'assets' => (int) $a->n, 'need' => (int) $a->perlu + (int) $a->rusak, 'broken' => (int) $a->rusak, 'open' => (int) $t->open, 'waiting' => (int) $t->waiting,
            'late' => $by['terlambat'] ?? 0, 'soon' => $by['segera'] ?? 0, 'month_cost' => $withCost ? self::costInRange(today()->startOfMonth(), today()->endOfMonth()) : 0.0,
        ];
    }

    public static function costInRange(CarbonInterface $from, CarbonInterface $to): float
    {
        return (float) FacOrder::query()->where('status', 'approved')->whereBetween(DB::raw('date(coalesce(finished_at, reported_at))'), [$from->toDateString(), $to->toDateString()])->sum(DB::raw('labor_cost + material_cost'));
    }

    /** Biaya Disetujui per bulan (termasuk bulan ini). @return list<array{label: string, value: float, tenaga: float, material: float}> */
    public static function costByMonth(int $months = 6): array
    {
        $from = today()->startOfMonth()->subMonths($months - 1);
        $rows = FacOrder::query()->where('status', 'approved')->whereBetween(DB::raw('date(coalesce(finished_at, reported_at))'), [$from->toDateString(), today()->endOfMonth()->toDateString()])
            ->get(['finished_at', 'reported_at', 'labor_cost', 'material_cost'])->groupBy(fn ($o) => ($o->finished_at ?? $o->reported_at)->format('Y-m'));
        $out = [];
        for ($i = 0; $i < $months; $i++) {
            $m = $from->copy()->addMonths($i);
            $g = $rows->get($m->format('Y-m'), collect());
            $out[] = ['label' => $m->translatedFormat('M'), 'tenaga' => (float) $g->sum('labor_cost'), 'material' => (float) $g->sum('material_cost'), 'value' => (float) $g->sum(fn ($o) => (float) $o->labor_cost + (float) $o->material_cost)];
        }

        return $out;
    }

    /** Jumlah tiket per status. @return list<array{label: string, value: int, tone: string}> */
    public static function ticketsByStatus(): array
    {
        $n = FacOrder::query()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        return collect(self::STATUS)->map(fn ($label, $k) => ['label' => $label, 'value' => (int) ($n[$k] ?? 0), 'tone' => self::STATUS_TONES[$label]])->values()->all();
    }

    /** Tiket terbuka per kategori aset. @return list<array{label: string, value: int}> */
    public static function ticketsByCategory(): array
    {
        return DB::table('g_fac_order as o')->join('m_fac_asset as a', 'a.id', '=', 'o.asset_id')->join('m_fac_category as c', 'c.id', '=', 'a.category_id')
            ->whereIn('o.status', FacOrder::OPEN)->groupBy('c.name')->orderByDesc(DB::raw('count(*)'))->get(['c.name as label', DB::raw('count(*) as value')])
            ->map(fn ($r) => ['label' => $r->label, 'value' => (int) $r->value])->all();
    }

    /** Tiket Disetujui menutup jadwalnya (dikerjakan pada tanggal selesai); aset Perlu perawatan / Rusak kembali Baik bila tak ada tiket terbuka lain. */
    public static function onApproved(FacOrder $o): void
    {
        $o->plan_id && FacPlan::query()->whereKey($o->plan_id)->update(['last_done_at' => ($o->finished_at ?? today())->toDateString()]);
        if ($o->asset_id && ! FacOrder::query()->where('asset_id', $o->asset_id)->where('id', '!=', $o->id)->whereIn('status', FacOrder::OPEN)->exists()) {
            FacAsset::query()->whereKey($o->asset_id)->whereIn('cond', ['perlu', 'rusak'])->update(['cond' => 'baik']);
        }
    }
}
