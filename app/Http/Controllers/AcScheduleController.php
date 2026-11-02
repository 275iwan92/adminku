<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcScheduleRequest;
use App\Models\AcArea;
use App\Models\AcUnit;
use App\Models\AcVendor;
use App\Services\AcCare;
use App\Services\AcMessage;
use App\Support\Constants;
use App\Support\Text;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/* REMARK
 * Tujuan       : Penjadwalan AC (GA, modul GAS), satu layar: kotak ringkasan (total AC, terlambat dicuci, jatuh tempo dalam ambang, terjadwal minggu ini), KALENDER minggu Senin-Sabtu dengan isi / kapasitas per hari
 *                (klik hari = tanggal kunjungan), DAFTAR AC yang perlu dibersihkan (centang, paling terlambat dulu, saring per area), Jadwalkan yang dipilih, Susun otomatis, dan pemilih kapasitas harian.
 *                Semua aturan (hari / jam / kapasitas per vendor / satu AC satu pesanan terbuka) ditegakkan AcCare di server. Pesanan yang dibuat berstatus Draf; pemberitahuan dikirim dari Kunjungan AC.
 * Sumber hulu  : AcCare::overview / load / schedule / autoPlan, m_ac_unit, m_ac_vendor, m_ac_area, Constants (ga.ac.capacity).
 * Output       : layar Penjadwalan; redirect ke layar yang sama dengan pesan hasil.
 * Dipakai oleh : routes/web.php (ac-schedule.*).
 * Status       : Aktif.
 */
class AcScheduleController extends Controller
{
    public function index(Request $request)
    {
        $vendors = AcVendor::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
        $vendorId = (int) Text::of($request->query('vendor'));
        $vendorId = isset($vendors[$vendorId]) ? $vendorId : (array_key_first($vendors) ?? 0);

        $picked = $this->day(Text::of($request->query('date')));
        $week = ($this->day(Text::of($request->query('week'))) ?? $picked ?? (today()->isSunday() ? today()->addDay() : today()))->copy()->startOfWeek();
        $days = collect(range(0, 5))->map(fn ($i) => $week->copy()->addDays($i));
        $load = $vendorId ? AcCare::load($vendorId, $week, $week->copy()->addDays(5)) : [];
        $cap = AcCare::capacity();
        $allowed = AcCare::visitDays();
        $calendar = $days->map(fn (Carbon $d) => [
            'date' => $d, 'iso' => $d->toDateString(), 'n' => $load[$d->toDateString()] ?? 0, 'past' => $d->lt(today()), 'closed' => ! in_array($d->dayOfWeekIso, $allowed, true),
        ]);
        $selected = $picked ?? $calendar->first(fn ($c) => ! $c['past'] && ! $c['closed'] && $c['n'] < $cap)['date'] ?? $calendar->first(fn ($c) => ! $c['past'] && ! $c['closed'])['date'] ?? null;

        $units = AcUnit::query()->with('room.area')->where('is_active', true)->get();
        $over = AcCare::overview($units);
        $areaId = (int) Text::of($request->query('area'));
        $all = Text::of($request->query('show')) === 'semua';
        $pick = (int) Text::of($request->query('pick'));   // AC yang ditandai dari kartu Denah AC
        $rows = $units->filter(fn ($u) => ! $areaId || $u->room->area_id === $areaId)
            ->filter(fn ($u) => $all || $u->id === $pick || $over[$u->id]['open'] || $over[$u->id]['status'] !== 'aman')
            ->sortBy([fn ($a, $b) => (bool) $over[$a->id]['open'] <=> (bool) $over[$b->id]['open'], fn ($a, $b) => $over[$a->id]['days'] <=> $over[$b->id]['days'], fn ($a, $b) => strcmp($a->asset_code, $b->asset_code)])
            ->map(fn (AcUnit $u) => ['unit' => $u, 'o' => $over[$u->id]])->values();

        return view('ac-schedule.index', [
            'vendors' => $vendors, 'vendorId' => $vendorId, 'calendar' => $calendar, 'week' => $week, 'selected' => $selected, 'cap' => $cap, 'rows' => $rows, 'pick' => $pick,
            'areas' => AcArea::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(), 'areaId' => $areaId, 'all' => $all,
            'time' => AcCare::startTime(), 'days' => AcCare::dayNames($allowed), 'window' => AcCare::timeText(AcCare::startTime()).' - '.AcCare::timeText(AcCare::endTime()),
            'summary' => [
                'total' => $units->count(), 'late' => collect($over)->where('status', 'terlambat')->count(), 'soon' => collect($over)->where('status', 'segera')->count(),
                'week' => AcCare::scheduledBetween(today()->startOfWeek(), today()->startOfWeek()->addDays(6)),
            ],
            'canWrite' => $request->user()->canWrite('GAS'), 'canVisits' => $request->user()->canAccess('GAV'),
            'dateText' => $selected ? AcMessage::dateText($selected) : null, 'left' => $selected && $vendorId ? max(0, $cap - AcCare::used($vendorId, $selected)) : 0,
        ]);
    }

    public function store(AcScheduleRequest $request): RedirectResponse
    {
        $order = AcCare::schedule(AcVendor::findOrFail($request->validated('vendor_id')), $request->validated('date'), $request->validated('time'), $request->validated('units'), $request->user());
        $n = count($request->validated('units'));

        return redirect()->route('ac-schedule.index', ['vendor' => $order->vendor_id, 'date' => $order->visit_date->toDateString()])
            ->with('status', $n.' AC dijadwalkan pada '.AcMessage::dateText($order->visit_date).' (pesanan '.$order->no.', Draf). Kirim pemberitahuan dari Kunjungan AC.');
    }

    public function auto(AcScheduleRequest $request): RedirectResponse
    {
        $vendor = AcVendor::findOrFail($request->validated('vendor_id'));
        $r = AcCare::autoPlan($vendor, $request->validated('date'), $request->user());
        $back = ['vendor' => $vendor->id] + ($r['orders'] ? ['date' => $r['orders'][0]->visit_date->toDateString()] : []);
        if (! $r['orders']) {
            return redirect()->route('ac-schedule.index', $back)->with('status', 'Tidak ada AC yang perlu dijadwalkan: semua berstatus Aman atau sudah terjadwal.');
        }
        $first = $r['orders'][0]->visit_date;
        $last = end($r['orders'])->visit_date;

        return redirect()->route('ac-schedule.index', $back)
            ->with('status', 'Disusun otomatis: '.$r['units'].' AC dalam '.count($r['orders']).' kunjungan ('.$first->format('d-m-Y').($last->isSameDay($first) ? '' : ' sampai '.$last->format('d-m-Y')).'), status Draf. Kirim pemberitahuan dari Kunjungan AC.');
    }

    public function capacity(AcScheduleRequest $request): RedirectResponse
    {
        Constants::set('ga.ac.capacity', (int) $request->validated('capacity'), 'ga');

        return back()->with('status', 'Kapasitas harian diubah menjadi '.(int) $request->validated('capacity').' AC per vendor. Jadwal yang sudah dibuat tidak berubah.');
    }

    private function day(string $value): ?Carbon
    {
        try {
            $d = Carbon::createFromFormat('Y-m-d', $value);

            return $d && $d->format('Y-m-d') === $value ? $d->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
