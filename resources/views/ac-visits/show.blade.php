{{-- REMARK
 Tujuan       : Layar satu Kunjungan AC (GA, modul GAV): empat tahap berurutan (Draf, Terkirim, Dikonfirmasi vendor, Selesai) beserta waktu dan petugasnya, data kunjungan, tombol tahap berikutnya (Kirim dengan konfirmasi,
                Tandai dikonfirmasi vendor, Tandai selesai), Jadwal ulang, Batalkan, cetak Berita Acara; pratinjau pesan vendor (tautan Buka WhatsApp) dan pesan salinan PIC (siap kirim sehari sebelumnya pukul 15.00);
                daftar AC dengan foto sebelum / sesudah (opsional) dan riwayat jadwal ulang.
 Sumber hulu  : AcVisitController@show (order, lines, vendorMsg, pic, canWrite, defaultDone, newDate, waNumber, window, days).
 Input        : lihat sumber hulu.
 Output       : POST ac-visits.send / confirm / complete / reschedule / cancel / photo.
 Dipakai oleh : route ac-visits.show (Buka dari Kunjungan AC; tautan dari Penjadwalan AC).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Kunjungan AC'])
@section('body')
    @php
        use App\Services\AcCare;
        use App\Services\AcMessage;
        $st = $order->status;
        $label = AcCare::ORDER_STATUS[$st];
        $rank = ['draft' => 0, 'rescheduled' => 0, 'sent' => 1, 'confirmed' => 2, 'done' => 3][$st] ?? -1;
        $stamp = fn ($at, $by) => $at ? $at->format('d-m-Y H:i').($by ? ' · '.$by->name : '') : 'Belum';
        $stages = [
            ['Draf', $st === 'rescheduled' ? 'Dijadwal ulang' : 'Draf', 'Dibuat '.$order->created_at->format('d-m-Y H:i').' · '.$order->creator->name],
            ['Terkirim', 'Terkirim', $stamp($order->sent_at, $order->sender)],
            ['Dikonfirmasi', 'Dikonfirmasi vendor', $stamp($order->confirmed_at, $order->confirmer)],
            ['Selesai', 'Selesai', $stamp($order->done_at, $order->finisher)],
        ];
        $open = $order->isOpen();
        $linesOpts = $lines->mapWithKeys(fn ($l) => [$l->id => $l->unit->label()])->all();
        $e = fn (string $k) => $errors->first($k) ?: null;
    @endphp
    @include('partials.flash')
    <x-dl::headline>Kunjungan · {{ $order->no }} <x-dl::badge :tone="AcCare::ORDER_TONES[$label]" :label="$label" /></x-dl::headline>
    <x-dl::kpi-row>
        @foreach ($stages as $i => [$short, $name, $sub])
            <x-dl::stat-box :label="($i + 1).'. '.$short" :value="$name" :tone="$st === 'cancelled' ? 'neutral' : ($rank > $i ? 'success' : ($rank === $i ? ($st === 'rescheduled' ? 'warn' : 'accent') : 'neutral'))" :sub="$sub" />
        @endforeach
    </x-dl::kpi-row>
    <x-dl::form.row :cols="4">
        <x-dl::field.display label="Tanggal kunjungan" :value="raw(AcMessage::dateText($order->visit_date))" />
        <x-dl::field.display label="Jam mulai" :value="raw(AcCare::timeText($order->timeText()))" />
        <x-dl::field.display label="Vendor" :value="raw($order->vendor->name.($order->vendor->contact_name ? ' · '.$order->vendor->contact_name : ''))" />
        <x-dl::field.display label="Jumlah AC" :value="raw($lines->count().' unit')" />
    </x-dl::form.row>
    @if ($order->reschedule_reason)
        <x-dl::alert tone="warn" :message="raw('Dijadwal ulang: '.$order->reschedule_reason)" />
    @endif
    @if ($st === 'cancelled')
        <x-dl::alert tone="danger" :message="raw('Dibatalkan '.$order->cancelled_at->format('d-m-Y H:i').' oleh '.($order->canceller?->name ?? '-').': '.$order->cancel_reason)" />
    @endif

    <x-dl::action-bar align="start">
        <x-dl::button variant="secondary" icon="back" label="Kembali" :href="route('ac-visits.index')" />
        @if ($canWrite)
            @if (in_array($st, ['draft', 'rescheduled', 'sent'], true))
                <x-dl::button icon="fa-paper-plane" :label="$st === 'sent' ? 'Kirim ulang' : 'Kirim'" data-dl-open="send-modal" />
            @endif
            @if ($st === 'sent')
                <x-dl::action-form :action="route('ac-visits.confirm', $order)" style="display: inline">
                    <x-dl::button type="submit" variant="secondary" icon="fa-circle-check" label="Tandai dikonfirmasi vendor" />
                </x-dl::action-form>
            @endif
            @if ($st === 'confirmed')
                <x-dl::button icon="fa-flag-checkered" label="Tandai selesai" data-dl-open="done-modal" />
            @endif
            @if ($open)
                <x-dl::button variant="secondary" icon="fa-calendar-days" label="Jadwal ulang" data-dl-open="move-modal" />
                <x-dl::button variant="secondary" icon="fa-ban" label="Batalkan" data-dl-open="cancel-modal" />
            @endif
        @endif
        <x-dl::button variant="secondary" icon="print" label="Cetak Berita Acara" :href="route('ac-visits.print', $order)" target="_blank" rel="noopener" />
    </x-dl::action-bar>

    <x-dl::divider label="AC dalam kunjungan ini" />
    <div class="dl-table-wrap dl-table--stack">
        <table class="dl-table is-striped">
            <thead><tr><th>AC</th><th>Area dan ruang</th><th>PIC</th><th>Pekerjaan</th><th>Selesai</th><th>Foto sebelum</th><th>Foto sesudah</th></tr></thead>
            <tbody>
                @foreach ($lines as $l)
                    <tr>
                        <td data-label="AC"><b>{{ $l->unit->label() }}</b><br><small>{{ $l->unit->typeText() }}</small></td>
                        <td data-label="Area dan ruang">{{ $l->unit->room->area->name }}<br><small>{{ $l->unit->room->name }}</small></td>
                        <td data-label="PIC">{{ $l->unit->room->pic_name ?: '—' }}</td>
                        <td data-label="Pekerjaan">{{ AcCare::JOBS[$l->job] }}</td>
                        <td data-label="Selesai">{{ $l->done_at?->format('d-m-Y') ?? '—' }}</td>
                        <td data-label="Foto sebelum">@if ($l->photo_before_path)<a href="{{ route('ac-visits.file', [$l, 'before']) }}" target="_blank" rel="noopener">{{ $l->photo_before_name ?: 'Lihat' }}</a>@else — @endif</td>
                        <td data-label="Foto sesudah">@if ($l->photo_after_path)<a href="{{ route('ac-visits.file', [$l, 'after']) }}" target="_blank" rel="noopener">{{ $l->photo_after_name ?: 'Lihat' }}</a>@else — @endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($canWrite && in_array($st, ['confirmed', 'done'], true))
        <x-dl::action-form :action="route('ac-visits.photo', $order)" enctype="multipart/form-data">
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="line_id" label="AC yang difoto" :options="$linesOpts" :value="old('line_id', array_key_first($linesOpts))" :error="$e('line_id')" />
                <x-dl::field.select name="kind" label="Foto" :options="['before' => 'Sebelum dikerjakan', 'after' => 'Sesudah dikerjakan']" :value="old('kind', 'before')" :error="$e('kind')" />
                <x-dl::field.upload name="photo" label="Foto bukti" accept=".jpg,.jpeg,.png,image/jpeg,image/png" :error="$e('photo')" hint="Opsional (dianjurkan). Dari ponsel, pilihan kamera muncul saat memilih berkas. JPG atau PNG, paling besar 5 MB." />
            </x-dl::form.row>
            <x-dl::action-bar align="start">
                <x-dl::button type="submit" variant="secondary" size="sm" icon="fa-camera" label="Unggah foto" />
            </x-dl::action-bar>
        </x-dl::action-form>
    @endif

    <x-dl::divider label="Pesan untuk vendor" />
    <p class="dl-note">
        E-mail ke: <b>{{ $order->vendor->email ?: 'belum ada alamat e-mail' }}</b> · WhatsApp: <b>{{ $waNumber ? '+'.$waNumber : 'belum ada nomor yang sah' }}</b>.
        Tombol Kirim mengirim e-mail dan menandai Terkirim; pesan WhatsApp dikirim lewat tombol Buka WhatsApp (pesan sudah terisi). Tidak ada yang terkirim sebelum Anda menekan tombolnya.
    </p>
    <x-dl::field.text name="preview_vendor" label="Pratinjau pesan" :multiline="true" :readonly="true" :value="$vendorMsg['text']" />
    <x-dl::action-bar align="start">
        @if ($vendorMsg['wa'])
            <x-dl::button variant="secondary" icon="fa-whatsapp" label="Buka WhatsApp" :href="$vendorMsg['wa']" target="_blank" rel="noopener" />
        @endif
    </x-dl::action-bar>

    <x-dl::divider label="Salinan untuk PIC area" />
    <p class="dl-note">Pesan siap kirim untuk PIC tiap ruang, disarankan dikirim {{ $pic['send_at']->format('d-m-Y') }} pukul {{ $pic['send_at']->format('H.i') }} (sehari sebelum kunjungan). Tidak terkirim otomatis.</p>
    @forelse ($pic['notices'] as $i => $n)
        <x-dl::field.text :name="'preview_pic_'.$i" :label="'Untuk '.$n['pic'].' · '.$n['count'].' AC'.($n['phone'] ? ' · '.$n['phone'] : '')" :multiline="true" :readonly="true" :value="$n['text']" />
        <x-dl::action-bar align="start">
            @if ($n['wa'])
                <x-dl::button variant="secondary" size="sm" icon="fa-whatsapp" label="Buka WhatsApp PIC" :href="$n['wa']" target="_blank" rel="noopener" />
            @endif
            @if ($n['email'])
                <x-dl::button variant="secondary" size="sm" icon="email" label="E-mail PIC" :href="'mailto:'.$n['email'].'?subject='.rawurlencode(AcMessage::subject(['date' => $order->visit_date, 'lines' => range(1, $n['count']), 'no' => $order->no])).'&body='.rawurlencode($n['text'])" />
            @endif
        </x-dl::action-bar>
    @empty
        <p class="dl-note">Belum ada PIC pada ruang-ruang di kunjungan ini. Isi PIC di master Ruang AC.</p>
    @endforelse
    @if ($pic['missing'])
        <x-dl::alert tone="warn" :message="raw('Ruang tanpa PIC: '.implode(', ', $pic['missing']).'.')" />
    @endif

    @if ($order->reschedules->isNotEmpty())
        <x-dl::divider label="Riwayat jadwal ulang" />
        <div class="dl-table-wrap dl-table--stack">
            <table class="dl-table is-striped">
                <thead><tr><th>Dipindah pada</th><th>Dari</th><th>Ke</th><th>Alasan</th><th>Oleh</th></tr></thead>
                <tbody>
                    @foreach ($order->reschedules->sortByDesc('id') as $h)
                        <tr>
                            <td data-label="Dipindah pada">{{ $h->created_at->format('d-m-Y H:i') }}</td>
                            <td data-label="Dari">{{ $h->from_date->format('d-m-Y') }} {{ AcCare::timeText($h->from_time) }}</td>
                            <td data-label="Ke">{{ $h->to_date->format('d-m-Y') }} {{ AcCare::timeText($h->to_time) }}</td>
                            <td data-label="Alasan">{{ $h->reason }}</td>
                            <td data-label="Oleh">{{ $h->creator->name }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($canWrite && in_array($st, ['draft', 'rescheduled', 'sent'], true))
        <x-dl::modal id="send-modal" title="Kirim pemberitahuan" icon="fa-paper-plane" size="sm">
            <x-dl::action-form :action="route('ac-visits.send', $order)" id="send-form">
                <p class="dl-note">Kirim pemberitahuan kunjungan {{ $order->no }} ({{ AcMessage::dateText($order->visit_date) }}, {{ $lines->count() }} AC) ke vendor {{ $order->vendor->name }}?</p>
                <p class="dl-note">{{ $order->vendor->email ? 'E-mail akan dikirim ke '.$order->vendor->email.' sekarang.' : 'Vendor tidak punya e-mail: hanya status yang berubah.' }} Pesan WhatsApp dikirim lewat tombol Buka WhatsApp sesudahnya.</p>
            </x-dl::action-form>
            <x-slot:footer><x-dl::button type="submit" form="send-form" icon="fa-paper-plane" label="Kirim" /></x-slot:footer>
        </x-dl::modal>
    @endif
    @if ($canWrite && $st === 'confirmed')
        <x-dl::modal id="done-modal" title="Tandai selesai" icon="fa-flag-checkered" size="sm">
            <x-dl::action-form :action="route('ac-visits.complete', $order)" id="done-form">
                <x-dl::field.date name="done_at" label="Tanggal selesai" :value="old('done_at', $defaultDone->format('Y-m-d'))" required hint="Jatuh tempo berikutnya tiap AC dihitung dari tanggal ini. Tidak sebelum tanggal kunjungan, tidak setelah hari ini." />
            </x-dl::action-form>
            <x-slot:footer><x-dl::button type="submit" form="done-form" icon="save" label="Tandai selesai" /></x-slot:footer>
        </x-dl::modal>
    @endif
    @if ($canWrite && $open)
        <x-dl::modal id="move-modal" title="Jadwal ulang" icon="fa-calendar-days" size="sm">
            <x-dl::action-form :action="route('ac-visits.reschedule', $order)" id="move-form">
                <x-dl::field.date name="date" label="Tanggal baru" :value="old('date', $newDate->format('Y-m-d'))" required hint="Hanya hari {{ $days }}; kapasitas harian vendor dicek." />
                <x-dl::field.time name="time" label="Jam mulai" :value="old('time', $order->timeText())" hint="Antara {{ $window }}." />
                <x-dl::field.text name="reason" label="Alasan" :value="old('reason')" required maxlength="255" hint="Wajib; tersimpan di riwayat jadwal ulang." />
            </x-dl::action-form>
            <x-slot:footer><x-dl::button type="submit" form="move-form" icon="save" label="Jadwal ulang" /></x-slot:footer>
        </x-dl::modal>
        <x-dl::modal id="cancel-modal" title="Batalkan kunjungan" icon="fa-ban" size="sm">
            <x-dl::action-form :action="route('ac-visits.cancel', $order)" id="cancel-form">
                <p class="dl-note">Kunjungan yang dibatalkan tetap tersimpan sebagai catatan, dan AC-nya bisa dijadwalkan lagi.</p>
                <x-dl::field.text name="reason" label="Alasan" :value="old('reason')" required maxlength="255" />
            </x-dl::action-form>
            <x-slot:footer><x-dl::button type="submit" form="cancel-form" variant="danger" icon="fa-ban" label="Batalkan kunjungan" /></x-slot:footer>
        </x-dl::modal>
    @endif
@endsection
