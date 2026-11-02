<?php

namespace App\Http\Controllers;

use App\Models\AcArea;
use App\Models\AcOrderLine;
use App\Models\AcUnit;
use App\Services\AcCare;
use App\Support\Text;
use Illuminate\Http\Request;

/* REMARK
 * Tujuan       : Denah AC (GA, modul GAD): pilih area / lantai, lihat denah (gambar unggahan, atau denah skematis digambar dari persegi ruang sampai denah asli diunggah) dengan titik AC berwarna menurut status
 *                (Aman / Segera / Terlambat); klik titik membuka kartu detail (kode ruang, kode aset, tipe, cuci rutin terakhir dan berikutnya, servis besar berikutnya, jadwal, Jadwalkan, riwayat dan foto).
 *                Titik ditaruh pada posisi persen (x, y) di denah; AC tanpa posisi diletakkan di tengah ruangnya. Gambar denah privat di disk "ga", hanya lewat controller ini.
 * Sumber hulu  : m_ac_area, m_ac_room, m_ac_unit, AcCare::overview, g_ac_service_line (riwayat satu AC).
 * Output       : layar Denah AC, berkas gambar denah.
 * Dipakai oleh : routes/web.php (ac-plan.*).
 * Status       : Aktif.
 */
class AcPlanController extends Controller
{
    private const HISTORY = 10;

    public function index(Request $request)
    {
        $areas = AcArea::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'plan_path', 'plan_w', 'plan_h']);
        $area = $areas->firstWhere('id', (int) Text::of($request->query('area'))) ?? $areas->first();

        $units = $area ? AcUnit::query()->with('room.area')->where('is_active', true)->whereHas('room', fn ($q) => $q->where('area_id', $area->id))->orderBy('asset_code')->get() : collect();
        $over = AcCare::overview($units);
        $dots = $units->map(function (AcUnit $u) use ($over) {
            $r = $u->room;

            return ['unit' => $u, 'o' => $over[$u->id], 'x' => $u->pos_x ?? $r->x + $r->w / 2, 'y' => $u->pos_y ?? $r->y + $r->h / 2];
        });
        $rooms = $area && ! $area->plan_path ? $area->rooms()->where('is_active', true)->orderBy('code')->get() : collect();
        $pick = $dots->firstWhere('unit.id', (int) Text::of($request->query('unit')));
        $history = $pick ? AcOrderLine::query()->with('order:id,no,visit_date,status')->where('unit_id', $pick['unit']->id)->whereNotNull('done_at')->orderByDesc('done_at')->orderByDesc('id')->limit(self::HISTORY)->get() : collect();
        $count = fn (string $s) => $dots->filter(fn ($d) => $d['o']['status'] === $s)->count();

        return view('ac-plan.index', [
            'areas' => $areas->pluck('name', 'id')->all(), 'area' => $area, 'dots' => $dots, 'rooms' => $rooms, 'pick' => $pick, 'history' => $history, 'historyLimit' => self::HISTORY,
            'counts' => ['aman' => $count('aman'), 'segera' => $count('segera'), 'terlambat' => $count('terlambat')],
            'canSchedule' => $request->user()->canWrite('GAS'), 'canVisits' => $request->user()->canAccess('GAV'), 'canUnit' => $request->user()->canAccess('GAK'),
        ]);
    }

    public function image(AcArea $area)
    {
        return AcAreaController::planResponse($area);
    }
}
