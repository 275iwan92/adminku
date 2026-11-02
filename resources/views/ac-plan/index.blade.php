{{-- REMARK
 Tujuan       : Denah AC (GA, modul GAD): pilih area / lantai, denah (gambar unggahan, atau denah skematis dari persegi ruang) dengan titik AC berwarna menurut status (Aman / Segera / Terlambat); klik titik = kartu detail
                (kode ruang · kode aset, tipe, cuci rutin terakhir / berikutnya, servis besar berikutnya, jadwal, tombol Jadwalkan, riwayat dan foto). Titik adalah tautan (tanpa skrip): kartu dirender di server.
 Sumber hulu  : AcPlanController@index (areas, area, dots, rooms, pick, history, historyLimit, counts, canSchedule, canVisits, canUnit).
 Input        : lihat sumber hulu.
 Output       : GET saring area; tautan titik ?area=&unit=; tombol Jadwalkan membuka Penjadwalan AC dengan AC itu terpilih.
 Dipakai oleh : route ac-plan.index (menu GA > Denah AC).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Denah AC'])
@section('body')
    @php
        use App\Services\AcCare;
        $tone = AcCare::TONES;
        $at = fn (?\Carbon\CarbonInterface $d) => $d?->format('d-m-Y') ?? 'Belum tercatat';
    @endphp
    @include('partials.flash')
    @if (! $area)
        <x-dl::empty-state title="Belum ada area" message="Tambahkan area dan ruang di menu Area dan Gedung AC lebih dulu." icon="fa-building" />
    @else
        <x-dl::action-form method="get" :action="route('ac-plan.index')" class="dl-report-filter">
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="area" label="Area / Lantai" :options="$areas" :value="$area->id" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button type="submit" icon="fa-filter" label="Tampilkan" size="sm" />
            </x-dl::action-bar>
        </x-dl::action-form>
        <x-dl::kpi-row>
            <x-dl::stat-box label="AC di area ini" :value="(string) $dots->count()" tone="accent" :sub="$area->plan_path ? 'Denah gambar unggahan' : 'Denah skematis (asumsi)'" />
            <x-dl::stat-box label="Aman" :value="(string) $counts['aman']" tone="success" />
            <x-dl::stat-box label="Segera" :value="(string) $counts['segera']" :tone="$counts['segera'] ? 'warn' : 'success'" :sub="'Dalam '.AcCare::soonDays().' hari'" />
            <x-dl::stat-box label="Terlambat" :value="(string) $counts['terlambat']" :tone="$counts['terlambat'] ? 'danger' : 'success'" />
        </x-dl::kpi-row>

        <div class="sstock-plan" style="aspect-ratio: {{ $area->plan_w }} / {{ $area->plan_h }}" role="img" aria-label="Denah {{ $area->name }}">
            @if ($area->plan_path)
                <img class="sstock-plan__img" src="{{ route('ac-plan.image', $area) }}" alt="Denah {{ $area->name }}">
            @else
                @foreach ($rooms as $r)
                    <div class="sstock-plan__room" style="left: {{ $r->x }}%; top: {{ $r->y }}%; width: {{ $r->w }}%; height: {{ $r->h }}%"><b>{{ $r->code }}</b> {{ $r->name }}</div>
                @endforeach
            @endif
            @foreach ($dots as $d)
                @php($u = $d['unit'])
                <a class="sstock-plan__dot is-{{ $d['o']['status'] }}{{ $pick && $pick['unit']->id === $u->id ? ' is-on' : '' }}" style="left: {{ $d['x'] }}%; top: {{ $d['y'] }}%"
                   href="{{ route('ac-plan.index', ['area' => $area->id, 'unit' => $u->id]) }}" title="{{ $u->label() }} · {{ $d['o']['label'] }}" aria-label="{{ $u->label() }}, {{ $d['o']['label'] }}">{{ substr($u->asset_code, -2) }}</a>
            @endforeach
        </div>
        <p class="dl-note">Titik hijau = Aman, kuning = Segera (jatuh tempo dalam {{ AcCare::soonDays() }} hari), merah = Terlambat. Klik titik untuk melihat kartu AC.@unless ($area->plan_path) Denah ini skematis, digambar dari persegi ruang, sampai denah asli diunggah di Area dan Gedung AC.@endunless</p>

        @if ($pick)
            @php($u = $pick['unit'])
            @php($o = $pick['o'])
            <x-dl::divider :label="'Kartu AC · '.$u->label()" />
            <x-dl::headline>{{ $u->label() }} <x-dl::badge :tone="$tone[$o['label']]" :label="$o['label']" /></x-dl::headline>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Kode ruang" :value="raw($u->room->code.' · '.$u->room->name)" />
                <x-dl::field.display label="Kode aset" :value="raw($u->asset_code)" />
                <x-dl::field.display label="Tipe" :value="raw($u->typeText())" />
                <x-dl::field.display label="Area" :value="raw($u->room->area->name)" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Cuci rutin terakhir" :value="raw($at($o['last_wash']))" />
                <x-dl::field.display label="Cuci rutin berikutnya" :value="raw($o['next_wash']->format('d-m-Y').' · '.\App\Services\VehicleCare::daysText(\App\Services\VehicleCare::days(today(), $o['next_wash'])))" />
                <x-dl::field.display label="Servis besar terakhir" :value="raw($at($o['last_major']))" />
                <x-dl::field.display label="Servis besar berikutnya" :value="raw($o['next_major']->format('d-m-Y').' · '.\App\Services\VehicleCare::daysText(\App\Services\VehicleCare::days(today(), $o['next_major'])))" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="PIC" :value="raw($u->room->pic_name ?: '-')" />
                <x-dl::field.display label="WhatsApp PIC" :value="raw($u->room->pic_phone ?: '-')" />
                <x-dl::field.display label="Jadwal" :value="raw($o['open'] ? $o['open']['date']->format('d-m-Y').' · '.$o['open']['no'].' ('.AcCare::ORDER_STATUS[$o['open']['status']].')' : 'Belum terjadwal')" />
                <x-dl::field.display label="Pekerjaan berikutnya" :value="raw(AcCare::JOBS[$o['job']])" />
            </x-dl::form.row>
            <x-dl::action-bar align="start">
                @if ($canSchedule && ! $o['open'])
                    <x-dl::button icon="fa-calendar-plus" label="Jadwalkan" :href="route('ac-schedule.index', ['pick' => $u->id, 'show' => 'semua'])" />
                @endif
                @if ($o['open'] && $canVisits)
                    <x-dl::button variant="secondary" icon="fa-truck-fast" label="Buka kunjungan" :href="route('ac-visits.show', $o['open']['order_id'])" />
                @endif
                @if ($canUnit)
                    <x-dl::button variant="secondary" icon="fa-snowflake" label="Master AC" :href="route('ac-units.show', $u)" />
                @endif
            </x-dl::action-bar>

            <x-dl::divider label="Riwayat dan foto" />
            @if ($history->isNotEmpty())
                <div class="dl-table-wrap dl-table--stack">
                    <table class="dl-table is-striped">
                        <thead><tr><th>Selesai</th><th>Pekerjaan</th><th>Pesanan</th><th>Foto sebelum</th><th>Foto sesudah</th></tr></thead>
                        <tbody>
                            @foreach ($history as $h)
                                <tr>
                                    <td data-label="Selesai">{{ $h->done_at->format('d-m-Y') }}</td>
                                    <td data-label="Pekerjaan">{{ AcCare::JOBS[$h->job] }}</td>
                                    <td data-label="Pesanan">@if ($canVisits)<a href="{{ route('ac-visits.show', $h->order_id) }}">{{ $h->order->no }}</a>@else{{ $h->order->no }}@endif</td>
                                    <td data-label="Foto sebelum">@if ($canVisits && $h->photo_before_path)<a href="{{ route('ac-visits.file', [$h, 'before']) }}" target="_blank" rel="noopener">{{ $h->photo_before_name ?: 'Lihat' }}</a>@else — @endif</td>
                                    <td data-label="Foto sesudah">@if ($canVisits && $h->photo_after_path)<a href="{{ route('ac-visits.file', [$h, 'after']) }}" target="_blank" rel="noopener">{{ $h->photo_after_name ?: 'Lihat' }}</a>@else — @endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($history->count() >= $historyLimit)
                    <p class="dl-note">Menampilkan {{ $historyLimit }} pekerjaan terakhir.</p>
                @endif
            @else
                <p class="dl-note">Belum ada pekerjaan selesai tercatat di sistem. Jatuh tempo dihitung dari tanggal awal di Master AC.</p>
            @endif
        @else
            <p class="dl-note">Klik sebuah titik AC pada denah untuk membuka kartunya.</p>
        @endif
    @endif
@endsection
