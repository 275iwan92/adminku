<?php

namespace App\Http\Controllers;

use App\Models\AcOrder;
use App\Models\AcUnit;
use App\Services\AcCare;

/* REMARK
 * Tujuan       : Perawatan AC (GA, modul GAM), satu butir menu seperti Kendaraan: panel Monitoring (kartu AC aktif, Preventif terlambat / segera, Kerusakan terbuka; grid per AC dengan cuci dan servis besar berikutnya,
 *                kerusakan 12 bulan, status) dan hub Setup (Denah, Master AC, Area dan Ruang, Vendor, Pengaturan) yang membuka layar master lewat tombol. Penjadwalan, Kunjungan, dan Catat hasil dibuka dari tombol Penjadwalan.
 *                Hanya-baca: tidak menulis apa pun.
 * Sumber hulu  : AcCare::overview (jumlah kueri tetap), AcCare::breakdownStats, m_ac_unit, g_ac_service_order.
 * Output       : layar Monitoring AC dan Setup AC.
 * Dipakai oleh : routes/web.php (ac-care.index, ac-setup.index).
 * Status       : Aktif.
 */
class AcCareController extends Controller
{
    public function index()
    {
        $units = AcUnit::query()->with('room.area')->where('is_active', true)->orderBy('asset_code')->get();
        $over = AcCare::overview($units);
        $bd = AcCare::breakdownStats();
        $rank = ['aman' => 0, 'segera' => 1, 'terlambat' => 2];
        $rows = $units->map(function (AcUnit $u) use ($over, $bd, $rank) {
            $o = $over[$u->id];
            $broken = isset($bd['open'][$u->id]);

            return [
                'id' => $u->id, 'code' => $u->asset_code, 'name' => $u->room->name.' · '.$u->typeText(), 'area' => $u->room->area->name, 'room' => $u->room->name.' ('.$u->room->code.')',
                'wash' => $o['next_wash']->format('d-m-Y').' · '.$o['due_text'], 'major' => $o['next_major']->format('d-m-Y'), 'breakdown' => $bd['year'][$u->id] ?? 0,
                'status' => $broken ? 'Rusak' : $o['label'], '_rank' => $broken ? 3 : $rank[$o['status']], 'due' => $o['days'],
            ];
        })->sortBy([['_rank', 'desc'], ['due', 'asc'], ['code', 'asc']])->values()->map(fn ($r) => array_diff_key($r, ['_rank' => 1, 'due' => 1]))->all();
        $count = fn (string $status) => collect($over)->where('status', $status)->count();

        return view('ac-care.index', [
            'rows' => $rows, 'active' => $units->count(), 'late' => $count('terlambat'), 'soon' => $count('segera'), 'broken' => count($bd['open']),
            'next' => AcOrder::query()->whereIn('status', AcOrder::OPEN)->whereDate('visit_date', '>=', today())->orderBy('visit_date')->orderBy('visit_time')->first(),
        ]);
    }

    public function setup()
    {
        return view('ac-setup.index');
    }
}
