{{-- REMARK
 Tujuan       : Dashboard Perawatan Gedung dan Aset (GA, modul FAM): angka (aset aktif, perlu perawatan, tiket terbuka, jadwal terlambat, biaya bulan ini), Perlu tindakan berwarna, grafik biaya perawatan 6 bulan (tenaga dan material),
                tiket per status dan tiket terbuka per kategori; tombol ke Tiket, Jadwal, Aset, Setup, Laporan biaya. Memakai kelas sstock-o* (sama dengan layar pembuka GA) sehingga warna mengikuti token tema --dl-*.
 Sumber hulu  : FacCareController@index.
 Input        : stats, todo, trend, trendMax, money, byStatus, byCategory, can, write.
 Output       : kartu dan grafik; tombol hanya bagi yang punya hak modulnya.
 Dipakai oleh : route fac-care.index (menu GA > Perawatan Gedung dan Aset).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Perawatan Gedung dan Aset'])
@section('body')
    @php
        $topStatus = max([1, ...array_column($byStatus, 'value')]);
        $topCat = max([1, ...array_column($byCategory, 'value')]);
        $todoTotal = array_sum(array_column($todo, 1));
        $setup = collect(['FAA', 'FAG', 'FAC', 'GAE'])->contains($can);
    @endphp
    @include('partials.flash')
    <header class="sstock-ohead sstock-ohead--slate">
        <i class="fa-solid fa-building-circle-check" aria-hidden="true"></i>
        <div><h1>Perawatan Gedung dan Aset</h1><p>Aset, jadwal berkala, dan tiket perawatan</p></div>
        <span class="sstock-ohead__hak">Hak Anda: {{ $write('FAM') ? 'Tulis' : 'Baca' }}</span>
    </header>
    <div class="sstock-ostats sstock-ohead--slate">
        @foreach ($stats as $s)
            <div class="sstock-ostat" data-hue="{{ ['teal', 'amber', 'blue', 'coral', 'green'][$loop->index] }}"><b class="{{ $s['alert'] ? 'is-alert' : '' }}">{{ $s['value'] }}</b><span>{{ $s['label'] }}</span></div>
        @endforeach
    </div>
    <x-dl::action-bar align="start">
        @if ($can('FAT'))<x-dl::button class="sstock-btn-coral" variant="secondary" icon="fa-clipboard-list" label="Tiket" :href="route('fac-orders.index')" />@endif
        @if ($can('FAJ'))<x-dl::button class="sstock-btn-teal" variant="secondary" icon="fa-calendar-check" label="Jadwal" :href="route('fac-plans.index')" />@endif
        @if ($can('FAA'))<x-dl::button class="sstock-btn-slate" variant="secondary" icon="fa-boxes-stacked" label="Aset" :href="route('fac-assets.index')" />@endif
        @if ($can('FAR'))<x-dl::button class="sstock-btn-amber" variant="secondary" icon="fa-file-invoice-dollar" label="Laporan Biaya" :href="route('reports.fac-cost')" />@endif
        @if ($setup)<x-dl::button class="sstock-btn-slate" variant="secondary" icon="fa-gear" label="Setup" :href="route('fac-setup.index')" />@endif
    </x-dl::action-bar>
    <section class="sstock-todo">
        <h2><i class="fa-solid fa-bell" aria-hidden="true"></i> Perlu tindakan @if ($todoTotal)<span class="sstock-todo__n">{{ number_format($todoTotal, 0, ',', '.') }}</span>@endif</h2>
        @forelse ($todo as [$tone, $n, $text, $icon, $url])
            <a class="sstock-trow is-{{ $tone }}" href="{{ $url }}">
                <i class="{{ $icon }}" aria-hidden="true"></i><b>{{ number_format($n, 0, ',', '.') }}</b><span>{{ $text }}</span><i class="fa-solid fa-chevron-right sstock-trow__go" aria-hidden="true"></i>
            </a>
        @empty
            <p class="sstock-tok"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Semua aman. Tidak ada yang perlu ditindaklanjuti.</p>
        @endforelse
    </section>
    <div class="sstock-ocharts sstock-ohead--slate">
        <section class="sstock-obox">
            <h2>Biaya perawatan 6 bulan terakhir</h2>
            <div class="sstock-otrend">
                @foreach ($trend as $t)
                    <div><span><u class="in" style="height: {{ round($t['tenaga'] / $trendMax * 100) }}%" title="Tenaga {{ $money($t['tenaga']) }}"></u><u class="out" style="height: {{ round($t['material'] / $trendMax * 100) }}%" title="Material {{ $money($t['material']) }}"></u></span>{{ $t['label'] }}</div>
                @endforeach
            </div>
            <p class="sstock-onote"><i class="sstock-dot in"></i> tenaga <i class="sstock-dot out"></i> material · hanya tiket yang sudah disetujui</p>
        </section>
        <section class="sstock-obox">
            <h2>Tiket per status</h2>
            @foreach ($byStatus as $b)
                <div class="sstock-obar"><span>{{ $b['label'] }}</span><i><u style="width: {{ round($b['value'] / $topStatus * 100) }}%"></u></i><b>{{ $b['value'] }}</b></div>
            @endforeach
            <h2 style="margin-top:14px">Tiket terbuka per kategori</h2>
            @forelse ($byCategory as $b)
                <div class="sstock-obar"><span>{{ $b['label'] }}</span><i><u style="width: {{ round($b['value'] / $topCat * 100) }}%"></u></i><b>{{ $b['value'] }}</b></div>
            @empty
                <p class="sstock-onote">Tidak ada tiket terbuka pada aset.</p>
            @endforelse
        </section>
    </div>
    @if ($write('FAT') || $write('FAJ') || $write('FAA'))
        <div class="sstock-ochips sstock-ohead--slate">
            <span>Pintasan</span>
            @if ($write('FAT'))<a href="{{ route('fac-orders.create') }}"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Lapor Kerusakan</a>@endif
            @if ($write('FAJ'))<a href="{{ route('fac-plans.create') }}"><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i> Jadwal baru</a>@endif
            @if ($write('FAA'))<a href="{{ route('fac-assets.create') }}"><i class="fa-solid fa-plus" aria-hidden="true"></i> Aset baru</a>@endif
        </div>
    @endif
@endsection
