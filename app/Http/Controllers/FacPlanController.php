<?php

namespace App\Http\Controllers;

use App\Models\AcVendor;
use App\Models\FacAsset;
use App\Models\FacPlace;
use App\Models\FacPlan;
use App\Services\FacCare;
use App\Support\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/* REMARK
 * Tujuan       : Jadwal perawatan berkala / preventif (GA Gedung dan Aset, modul FAJ): per aset ATAU per gedung / ruang; periode Mingguan / Bulanan / Triwulan / Tahunan; tanggal berikutnya dihitung otomatis
 *                (selesai terakhir + periode, atau tanggal mulai); status Aman / Segera / Terlambat. Tombol Buat Tiket membuka Perintah Kerja baru dari jadwal; saat tiketnya disetujui, jadwal maju otomatis.
 * Sumber hulu  : g_fac_plan, FacCare::overview.
 * Output       : grid Jadwal, form Jadwal.
 * Dipakai oleh : routes/web.php (fac-plans.*).
 * Status       : Aktif.
 */
class FacPlanController extends Controller
{
    public function index()
    {
        ['plans' => $plans, 'over' => $over] = FacCare::activePlans();
        $off = FacPlan::query()->with('asset', 'place.parent', 'vendor')->where('is_active', false)->get();
        $rows = $plans->sortBy(fn ($p) => $over[$p->id]['days'])->map(fn (FacPlan $p) => $this->row($p, $over[$p->id]))->values()
            ->merge($off->map(fn (FacPlan $p) => $this->row($p, null)))->all();

        return view('fac-plans.index', ['rows' => $rows]);
    }

    public function create(Request $request)
    {
        return view('fac-plans.form', $this->formData(new FacPlan(['period' => 'monthly', 'start_at' => today(), 'asset_id' => $request->integer('asset') ?: null])));
    }

    public function show(FacPlan $plan)
    {
        return view('fac-plans.form', $this->formData($plan));
    }

    public function store(Request $request): RedirectResponse
    {
        $plan = FacPlan::create($this->validated($request));

        return redirect()->route('fac-plans.show', $plan)->with('status', 'Jadwal disimpan.');
    }

    public function update(Request $request, FacPlan $plan): RedirectResponse
    {
        $action = $request->input('_action');
        if (in_array($action, ['activate', 'deactivate'], true)) {
            $plan->update(['is_active' => $action === 'activate']);

            return redirect()->route('fac-plans.show', $plan)->with('status', $action === 'activate' ? 'Jadwal diaktifkan.' : 'Jadwal dinonaktifkan.');
        }
        $plan->update($this->validated($request));

        return redirect()->route('fac-plans.show', $plan)->with('status', 'Jadwal disimpan.');
    }

    public function toggle(FacPlan $plan): JsonResponse
    {
        $plan->update(['is_active' => ! $plan->is_active]);

        return response()->json(['active' => $plan->is_active]);
    }

    private function row(FacPlan $p, ?array $o): array
    {
        return [
            'id' => $p->id, 'title' => $p->title, 'target' => $p->targetText(), 'period' => FacCare::PERIODS[$p->period], 'vendor' => $p->vendor?->name ?? 'Internal',
            'last' => $p->last_done_at?->format('Y-m-d'), 'next' => $o ? $o['due']->format('d-m-Y').' · '.$o['due_text'] : '-', 'status' => $o ? $o['label'] : 'Nonaktif', 'active' => $p->is_active,
        ];
    }

    private function validated(Request $request): array
    {
        $request->merge(['title' => Text::of($request->input('title')), 'asset_id' => $request->input('asset_id') ?: null, 'place_id' => $request->input('place_id') ?: null, 'vendor_id' => $request->input('vendor_id') ?: null]);
        $d = $request->validate([
            'title' => ['required', 'string', 'max:150'], 'asset_id' => ['nullable', 'integer', 'exists:m_fac_asset,id'], 'place_id' => ['nullable', 'integer', 'exists:m_fac_place,id'],
            'period' => ['required', Rule::in(array_keys(FacCare::PERIODS))], 'start_at' => ['required', 'date'], 'last_done_at' => ['nullable', 'date'],
            'vendor_id' => ['nullable', 'integer', 'exists:m_ac_vendor,id'], 'note' => ['nullable', 'string', 'max:255'],
        ], [], ['title' => 'Judul', 'period' => 'Periode', 'start_at' => 'Tanggal mulai']);
        if (empty($d['asset_id']) === empty($d['place_id'])) {
            throw ValidationException::withMessages(['asset_id' => 'Pilih satu saja: aset ATAU gedung / ruang.']);
        }
        $d['note'] = Text::of($d['note'] ?? '') ?: null;

        return $d;
    }

    private function formData(FacPlan $plan): array
    {
        return [
            'plan' => $plan, 'over' => $plan->exists ? FacCare::overview(collect([$plan]))[$plan->id] : null,
            'assets' => FacAsset::query()->where('is_active', true)->when($plan->asset_id, fn ($q) => $q->orWhere('id', $plan->asset_id))->orderBy('asset_code')->get()->mapWithKeys(fn ($a) => [$a->id => $a->asset_code.' · '.$a->name])->all(),
            'places' => FacPlace::query()->with('parent')->where('is_active', true)->when($plan->place_id, fn ($q) => $q->orWhere('id', $plan->place_id))->get()->sortBy(fn ($p) => $p->path())->mapWithKeys(fn ($p) => [$p->id => $p->path()])->all(),
            'vendors' => AcVendor::query()->where('is_active', true)->when($plan->vendor_id, fn ($q) => $q->orWhere('id', $plan->vendor_id))->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }
}
