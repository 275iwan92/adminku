<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnitRequest;
use App\Models\Unit;
use App\Support\Palette;
use Devlib\Locale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Master Satuan (pcs, box, set, ...) beserta konversinya (1 box = 12 pcs, berlaku untuk semua item). Hanya Supervisor.
 *                Satuan yang dinonaktifkan tidak muncul di pilihan item baru; satuan tidak dihapus (item memakainya).
 * Sumber hulu  : m_unit, m_unit_conversion, jumlah item pemakai (i_batch.unit).
 * Output       : grid Units, form Unit dengan tabel konversi.
 * Dipakai oleh : routes/web.php (units.*, grup supervisor).
 * Status       : Aktif.
 */
class UnitController extends Controller
{
    public function index()
    {
        $rows = Unit::query()->withCount('products')->with('conversions')->orderBy('code')->get()->map(fn (Unit $u) => [
            'id' => $u->id, 'code' => $u->code, 'name' => $u->name, 'kind' => Unit::KINDS[$u->kind] ?? $u->kind, 'hue' => Palette::unit($u->kind), 'items' => $u->products_count, 'active' => $u->is_active,
            'conv' => $u->conversions->map(fn ($c) => '1 '.$u->code.' = '.Locale::decimal($c->factor, 6).' '.$c->to_unit)->implode('; '),
        ])->all();

        return view('units.index', ['rows' => $rows]);
    }

    public function create()
    {
        return view('units.form', ['unit' => new Unit] + $this->formData(null));
    }

    public function show(Unit $unit)
    {
        return view('units.form', ['unit' => $unit->loadCount('products')] + $this->formData($unit));
    }

    public function store(UnitRequest $request): RedirectResponse|JsonResponse
    {
        $unit = DB::transaction(function () use ($request) {
            $unit = Unit::create($request->safe()->only(['code', 'name', 'kind']));
            $this->syncConversions($unit, $request->validated('conversions', []));

            return $unit;
        });

        if ($request->expectsJson()) {   // tambah cepat dari dropdown; id = kode (kunci dropdown), name = label dropdown
            return response()->json(['id' => $unit->code, 'name' => "{$unit->code} · {$unit->name}"], 201);
        }

        return redirect()->route('units.show', $unit)->with('status', 'Satuan disimpan.');
    }

    public function update(UnitRequest $request, Unit $unit): RedirectResponse
    {
        $action = $request->input('_action');
        if (in_array($action, ['activate', 'deactivate'], true)) {
            $unit->update(['is_active' => $action === 'activate']);

            return redirect()->route('units.show', $unit)->with('status', $action === 'activate' ? 'Satuan diaktifkan.' : 'Satuan dinonaktifkan.');
        }

        DB::transaction(function () use ($request, $unit) {
            $unit->update(array_filter($request->safe()->only(['name', 'kind'])));  // kode tidak berubah setelah disimpan
            $this->syncConversions($unit, $request->validated('conversions', []));
        });

        return redirect()->route('units.show', $unit)->with('status', 'Satuan disimpan.');
    }

    public function toggle(Unit $unit): JsonResponse
    {
        $unit->update(['is_active' => ! $unit->is_active]);

        return response()->json(['active' => $unit->is_active]);
    }

    // Baris konversi = salinan penuh dari form (hapus lalu isi ulang).
    private function syncConversions(Unit $unit, array $rows): void
    {
        $unit->conversions()->delete();
        foreach ($rows as $row) {
            $unit->conversions()->create(['to_unit' => $row['to_unit'], 'factor' => $row['factor'], 'created_by' => auth()->id(), 'updated_by' => auth()->id()]);
        }
    }

    // Pilihan satuan tujuan + baris konversi (dari input lama bila validasi gagal).
    private function formData(?Unit $unit): array
    {
        $rows = old('conversions') ?? $unit?->conversions->map(fn ($c) => ['to_unit' => $c->to_unit, 'factor' => $c->factor])->all() ?? [];

        return [
            'targets' => Unit::query()->when($unit, fn ($q) => $q->where('code', '!=', $unit->code))->orderBy('code')->get()->mapWithKeys(fn (Unit $u) => [$u->code => "{$u->code} · {$u->name}"])->all(),
            'conversions' => array_values($rows),
        ];
    }
}
