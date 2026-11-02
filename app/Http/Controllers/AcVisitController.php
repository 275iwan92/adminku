<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcVisitRequest;
use App\Models\AcOrder;
use App\Models\AcOrderLine;
use App\Services\AcCare;
use App\Services\AcMessage;
use App\Services\AcVisits;
use App\Support\AcVisitPrint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/* REMARK
 * Tujuan       : Kunjungan AC (GA, modul GAV): daftar pesanan kunjungan vendor dan layar satu pesanan dengan empat tahap berurutan (Draf, Terkirim, Dikonfirmasi vendor, Selesai; plus Dijadwal ulang / Dibatalkan),
 *                pratinjau pesan untuk vendor dan salinan untuk PIC (tautan WhatsApp terisi), tombol Kirim (konfirmasi dulu; e-mail baru dikirim saat ditekan), Tandai dikonfirmasi vendor, Tandai selesai,
 *                Jadwal ulang, Batalkan, foto bukti sebelum / sesudah (opsional) dan cetak Berita Acara. Lampiran foto privat di disk "ga", hanya lewat controller ini.
 * Sumber hulu  : AcVisits (siklus), AcCare, AcMessage, AcVisitPrint, g_ac_service_*.
 * Output       : grid Kunjungan, layar Kunjungan, cetak Berita Acara, berkas foto.
 * Dipakai oleh : routes/web.php (ac-visits.*).
 * Status       : Aktif.
 */
class AcVisitController extends Controller
{
    public function index()
    {
        $rows = AcOrder::query()->with('vendor:id,name')->withCount('lines')->orderByDesc('visit_date')->orderByDesc('id')->limit(500)->get()->map(fn (AcOrder $o) => [
            'id' => $o->id, 'no' => $o->no, 'type' => $o->typeText(), 'date' => $o->visit_date->format('Y-m-d'), 'time' => AcCare::timeText($o->timeText()), 'vendor' => $o->vendor->name, 'units' => $o->lines_count,
            'status' => AcCare::ORDER_STATUS[$o->status], 'sent' => $o->sent_at?->format('d-m-Y H:i'), 'confirmed' => $o->confirmed_at?->format('d-m-Y H:i'), 'done' => $o->done_at?->format('d-m-Y H:i'), 'note' => $o->reschedule_reason ?? $o->cancel_reason,
        ])->all();

        return view('ac-visits.index', ['rows' => $rows]);
    }

    public function show(Request $request, AcOrder $order)
    {
        $order->load(['vendor', 'creator:id,name', 'sender:id,name', 'confirmer:id,name', 'finisher:id,name', 'canceller:id,name', 'lines.unit.room.area', 'lines.parts', 'reschedules.creator:id,name']);
        $sender = $request->user()->name;
        $lines = $order->lines->sortBy(fn (AcOrderLine $l) => $l->unit->room->area->name.'|'.$l->unit->room->code.'|'.$l->unit->asset_code)->values();
        $vendor = AcVisits::vendorMessage($order, $sender);
        $first = $order->visit_date->gt(today()) ? $order->visit_date : today();

        return view('ac-visits.show', [
            'order' => $order, 'lines' => $lines, 'vendorMsg' => $vendor, 'pic' => AcVisits::picNotices($order, $sender), 'canWrite' => $request->user()->canWrite('GAV'),
            'defaultDone' => $order->visit_date->gt(today()) ? today() : $order->visit_date, 'newDate' => $first, 'partNames' => \App\Models\AcOrderPart::query()->select('name')->distinct()->orderBy('name')->limit(200)->pluck('name')->all(),
            'waNumber' => AcMessage::waNumber($order->vendor->phone), 'window' => AcCare::timeText(AcCare::startTime()).' - '.AcCare::timeText(AcCare::endTime()), 'days' => AcCare::dayNames(AcCare::visitDays()),
        ]);
    }

    public function send(Request $request, AcOrder $order): RedirectResponse
    {
        $r = AcVisits::send($order, $request->user());
        $msg = 'Pemberitahuan '.$order->no.' ditandai Terkirim'.($r['email'] ? '; e-mail terkirim ke '.$r['email'] : '; vendor tidak punya e-mail, e-mail tidak dikirim').'.'
            .($r['wa'] ? ' Tekan "Buka WhatsApp" untuk mengirim pesan WhatsApp yang sudah terisi.' : ' Nomor WhatsApp vendor belum sah, lengkapi di Vendor AC.');

        return redirect()->route('ac-visits.show', $order)->with('status', $msg);
    }

    public function confirm(Request $request, AcOrder $order): RedirectResponse
    {
        AcVisits::confirm($order, $request->user());

        return redirect()->route('ac-visits.show', $order)->with('status', 'Kunjungan '.$order->no.' ditandai dikonfirmasi vendor.');
    }

    public function complete(AcVisitRequest $request, AcOrder $order): RedirectResponse
    {
        AcVisits::complete($order, $request->validated('done_at'), $request->user());

        return redirect()->route('ac-visits.show', $order)->with('status', 'Kunjungan '.$order->no.' selesai; jatuh tempo berikutnya tiap AC dihitung dari tanggal selesai.');
    }

    public function result(AcVisitRequest $request, AcOrder $order): RedirectResponse
    {
        $line = $order->lines()->with('unit.room')->findOrFail($request->validated('line_id'));
        $o = AcVisits::recordResult($line, $request->validated('done_at'), $request->safe()->only(['findings', 'cause', 'action', 'labor_cost']), $request->validated('parts') ?? [], $request->user());

        return redirect()->route('ac-visits.show', $order)->with('status', 'Hasil '.$line->unit->label().' dicatat.'.($o->status === 'done' ? ' Semua AC sudah tercatat: kunjungan '.$o->no.' Selesai.' : ''));
    }

    public function reschedule(AcVisitRequest $request, AcOrder $order): RedirectResponse
    {
        $o = AcVisits::reschedule($order, $request->validated('date'), $request->validated('time'), $request->validated('reason'), $request->user());

        return redirect()->route('ac-visits.show', $order)->with('status', 'Kunjungan '.$o->no.' dijadwal ulang ke '.AcMessage::dateText($o->visit_date).' pukul '.AcCare::timeText($o->timeText()).'. Kirim ulang pemberitahuan ke vendor.');
    }

    public function cancel(AcVisitRequest $request, AcOrder $order): RedirectResponse
    {
        AcVisits::cancel($order, $request->validated('reason'), $request->user());

        return redirect()->route('ac-visits.show', $order)->with('status', 'Kunjungan '.$order->no.' dibatalkan. AC-nya bisa dijadwalkan lagi.');
    }

    public function photo(AcVisitRequest $request, AcOrder $order): RedirectResponse
    {
        $line = $order->lines()->with('unit.room')->findOrFail($request->validated('line_id'));
        AcVisits::attachPhoto($line, $request->validated('kind'), $request->file('photo'));

        return redirect()->route('ac-visits.show', $order)->with('status', 'Foto '.($request->validated('kind') === 'before' ? 'sebelum' : 'sesudah').' '.$line->unit->label().' disimpan.');
    }

    public function print(Request $request, AcOrder $order, AcVisitPrint $print)
    {
        return view('ac-visits.print', ['order' => $order, 'data' => $print->report($order, $request->user()->name)]);
    }

    // Foto bukti (privat di disk "ga"): hanya yang punya hak modul GAV.
    public function file(AcOrderLine $line, string $kind): StreamedResponse
    {
        $path = $line->{"photo_{$kind}_path"};
        abort_unless($path && Storage::disk('ga')->exists($path), 404);
        $line->loadMissing('unit', 'order');
        $name = Str::slug($line->order->no.' '.$line->unit->asset_code.' '.($kind === 'before' ? 'sebelum' : 'sesudah')).'.'.pathinfo($path, PATHINFO_EXTENSION);

        return Storage::disk('ga')->response($path, $name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=0']);
    }
}
