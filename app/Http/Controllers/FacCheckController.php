<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\FacAsset;
use App\Models\FacPlace;
use App\Services\FacCustody;
use App\Support\Text;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Opname Aset (GA, modul FAO): cek fisik berkala aset per orang (yang dibawa / dipegangnya) atau per ruang (yang seharusnya ada di kantor). Tiap aset dicentang "ditemukan"; yang tidak ditemukan menjadi temuan
 *                (tampil di Perlu tindakan sampai pemeriksaan berikutnya menemukannya). Pemeriksaan tidak mengubah posisi aset; tindak lanjutnya lewat Serah Terima (pengembalian atau laporan hilang).
 * Sumber hulu  : g_fac_check, g_fac_check_line, m_fac_asset (FacCustody::scope).
 * Output       : grid Opname, form pemeriksaan, hasil pemeriksaan.
 * Dipakai oleh : routes/web.php (fac-checks.*).
 * Status       : Aktif.
 */
class FacCheckController extends Controller
{
    public function index()
    {
        $rows = DB::table('g_fac_check as c')->leftJoin('m_employee as e', 'e.id', '=', 'c.employee_id')->leftJoin('m_fac_place as p', 'p.id', '=', 'c.place_id')->leftJoin('users as u', 'u.id', '=', 'c.created_by')
            ->selectRaw('c.id, c.checked_at, e.name as emp, p.name as place, u.name as by_name, (select count(*) from g_fac_check_line l where l.check_id = c.id) n, (select count(*) from g_fac_check_line l where l.check_id = c.id and l.found = 0) missing')
            ->orderByDesc('c.checked_at')->orderByDesc('c.id')->get()->map(fn ($r) => [
                'id' => $r->id, 'date' => substr($r->checked_at, 0, 10), 'scope' => $r->emp ? 'Orang: '.$r->emp : 'Ruang: '.$r->place, 'n' => (int) $r->n, 'missing' => (int) $r->missing,
                'result' => $r->missing ? 'Ada selisih' : 'Cocok', 'by' => $r->by_name,
            ])->all();

        return view('fac-checks.index', ['rows' => $rows, 'findings' => FacCustody::findings()->count()]);
    }

    public function create(Request $request)
    {
        $emp = $request->integer('employee') ?: null;
        $place = $request->integer('place') ?: null;

        return view('fac-checks.form', [
            'emp' => $emp, 'place' => $place, 'assets' => ($emp || $place) ? FacCustody::scope($emp, $place) : collect(),
            'employees' => Employee::query()->orderBy('name')->get()->mapWithKeys(fn ($e) => [$e->id => $e->code.' · '.$e->name.($e->is_active ? '' : ' (keluar)')])->all(),
            'places' => FacPlace::query()->with('parent')->where('is_active', true)->get()->sortBy(fn ($p) => $p->path())->mapWithKeys(fn ($p) => [$p->id => $p->path()])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:m_employee,id'], 'place_id' => ['nullable', 'integer', 'exists:m_fac_place,id'], 'checked_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'], 'found' => ['nullable', 'array'], 'found.*' => ['integer'], 'asset' => ['required', 'array', 'min:1'], 'asset.*' => ['integer', 'exists:m_fac_asset,id'],
        ], ['asset.required' => 'Tidak ada aset yang diperiksa.'], ['checked_at' => 'Tanggal']);
        abort_if(empty($d['employee_id']) === empty($d['place_id']), 422, 'Pilih orang ATAU ruang.');

        $check = DB::transaction(function () use ($d, $request) {
            $id = DB::table('g_fac_check')->insertGetId([
                'checked_at' => $d['checked_at'], 'employee_id' => $d['employee_id'] ?? null, 'place_id' => $d['place_id'] ?? null, 'note' => Text::of($d['note'] ?? '') ?: null,
                'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $allowed = FacCustody::scope($d['employee_id'] ?? null, $d['place_id'] ?? null)->pluck('id')->all();   // hanya aset yang memang dalam cakupan
            $found = array_map('intval', $d['found'] ?? []);
            foreach (array_values(array_intersect(array_map('intval', $d['asset']), $allowed)) as $assetId) {
                DB::table('g_fac_check_line')->insert(['check_id' => $id, 'asset_id' => $assetId, 'found' => in_array($assetId, $found, true)]);
            }

            return $id;
        });

        return redirect()->route('fac-checks.show', $check)->with('status', 'Opname aset disimpan.');
    }

    public function show(int $check)
    {
        $c = DB::table('g_fac_check')->where('id', $check)->first();
        abort_unless($c, 404);
        $lines = DB::table('g_fac_check_line as l')->join('m_fac_asset as a', 'a.id', '=', 'l.asset_id')->where('l.check_id', $check)->orderBy('a.asset_code')->get(['a.asset_code', 'a.name', 'l.found', 'a.id as asset_id']);

        return view('fac-checks.show', [
            'c' => $c, 'lines' => $lines, 'who' => $c->employee_id ? Employee::query()->find($c->employee_id)?->name : FacPlace::query()->with('parent')->find($c->place_id)?->path(),
        ]);
    }
}
