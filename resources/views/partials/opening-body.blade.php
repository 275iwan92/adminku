{{-- REMARK
 Tujuan       : Isi layar pembuka kelompok menu / modul (desktop dan HP): header (judul, keterangan, hak), angka utama, perlu tindakan, pintasan, dan ubin layar anak (lencana Tulis / Baca, jumlah data). Modul yang belum dibuat (HR, Purchase) hanya menampilkan rencana layar, tanpa angka.
 Sumber hulu  : App\Support\Opening::build.
 Input        : page (section, title, icon, tone, tag, soon, write, stats, todo, shortcuts, tiles), phone (true = tautan HP).
 Output       : kelas sstock-o* (CSS di resources/css/app.css). Desktop berjalan di tab (iframe): tautan ber-data-dl-open-item membuka layar sebagai tab kerja Layer Shell.
 Dipakai oleh : opening, hp.opening.
 Status       : Aktif.
--}}
@php
    $href = fn (array $x) => $phone && isset(\App\Support\Modules::all()[$x['code']]) ? \App\Support\HpShell::url($x['code']) : $x['url'];
@endphp
<header class="sstock-ohead sstock-ohead--{{ $page['tone'] }}">
    <i class="{{ $page['icon'] }}" aria-hidden="true"></i>
    <div><h1>{{ $page['title'] }}</h1><p>{{ $page['tag'] }}</p></div>
    <span class="sstock-ohead__hak">{{ $page['soon'] ? 'Segera hadir' : 'Hak Anda: '.($page['write'] ? 'Tulis' : 'Baca') }}</span>
</header>
@if ($page['soon'])
    <p class="sstock-onote">Modul belum dibuat. Layar pembuka hanya memuat rencana layar, tanpa angka; angka, tindakan, dan pintasan menyusul bersama modulnya.</p>
@endif
@if ($page['stats'])
    <div class="sstock-ostats sstock-ohead--{{ $page['tone'] }}">
        @foreach ($page['stats'] as $s)
            <div class="sstock-ostat" data-hue="{{ ['teal', 'blue', 'amber', 'purple', 'coral', 'green'][$loop->index % 6] }}"><b class="{{ $s['alert'] ? 'is-alert' : '' }}">{{ $s['value'] }}</b><span>{{ $s['label'] }}</span></div>
        @endforeach
    </div>
@endif
@if (! empty($page['charts']))
    @php
        $top = max([1.0, ...array_column($page['charts']['bars'], 'value')]);
        $kasReport = route('reports.cash-disbursements', ['from' => now()->startOfMonth()->subMonths(2)->toDateString(), 'to' => now()->toDateString()]);
    @endphp
    <div class="sstock-ocharts sstock-ohead--{{ $page['tone'] }}">
        <section class="sstock-obox">
            <h2>Pengeluaran per kebutuhan, 3 bulan terakhir ({{ $page['charts']['month'] }})</h2>
            @forelse ($page['charts']['bars'] as $b)
                <a class="sstock-obar" href="{{ $kasReport }}" @unless ($phone) data-dl-open-item="R-KOUT" data-label="Laporan Pengeluaran" @endunless>
                    <span>{{ $b['label'] }}</span><i><u style="width: {{ round($b['value'] / $top * 100) }}%"></u></i><b>{{ $b['text'] }}</b>
                </a>
            @empty
                <p class="sstock-onote">Belum ada pengeluaran yang disetujui dalam 3 bulan terakhir.</p>
            @endforelse
        </section>
        <section class="sstock-obox">
            <h2>Tren 6 bulan: masuk dan keluar</h2>
            <div class="sstock-otrend">
                @foreach ($page['charts']['trend'] as $t)
                    <div><span><u class="in" style="height: {{ round($t['in'] / $page['charts']['max'] * 100) }}%" title="Masuk Rp {{ \Devlib\Locale::number($t['in'], 0) }}"></u><u class="out" style="height: {{ round($t['out'] / $page['charts']['max'] * 100) }}%" title="Keluar Rp {{ \Devlib\Locale::number($t['out'], 0) }}"></u></span>{{ $t['label'] }}</div>
                @endforeach
            </div>
            <p class="sstock-onote"><i class="sstock-dot in"></i> masuk <i class="sstock-dot out"></i> keluar</p>
        </section>
    </div>
@endif
@if ($page['section'] === 'ga')
    <section class="sstock-obox sstock-ohead--{{ $page['tone'] }}">
        <h2>Perlu tindakan</h2>
        @forelse ($page['todo'] as [$tone, $n, $text])
            <div class="sstock-orow"><span class="sstock-opill is-{{ $tone }}">{{ \Devlib\Locale::number($n, 0) }}</span><span>{{ $text }}</span></div>
        @empty
            <p class="sstock-onote">Tidak ada yang perlu ditindaklanjuti.</p>
        @endforelse
    </section>
@endif
@if ($page['shortcuts'])
    <div class="sstock-ochips sstock-ohead--{{ $page['tone'] }}">
        <span>Pintasan</span>
        @foreach ($page['shortcuts'] as $s)
            <a href="{{ $href($s) }}" @unless ($phone) data-dl-open-item="{{ $s['code'] }}" data-label="{{ $s['label'] }}" @endunless><i class="{{ $s['icon'] }}" aria-hidden="true"></i> {{ $s['label'] }}</a>
        @endforeach
    </div>
@endif
<div class="sstock-otiles sstock-ohead--{{ $page['tone'] }}">
    @foreach ($page['tiles'] as $t)
        @if ($t['soon'])
            <span class="sstock-otile is-soon" data-hue="{{ $t['hue'] }}"><span class="sstock-otile__h"><i class="{{ $t['icon'] }}" aria-hidden="true"></i> {{ $t['label'] }}<em>segera</em></span></span>
        @else
            <a class="sstock-otile" data-hue="{{ $t['hue'] }}" href="{{ $href($t) }}" @unless ($phone) data-dl-open-item="{{ $t['code'] }}" data-label="{{ $t['label'] }}" @endunless>
                <span class="sstock-otile__h"><i class="{{ $t['icon'] }}" aria-hidden="true"></i> {{ $t['label'] }}<em class="{{ $t['write'] ? 'is-w' : '' }}">{{ $t['write'] ? 'Tulis' : 'Baca' }}</em></span>
                @if ($t['n'] !== null && $t['n'] !== '')<b>{{ $t['n'] }}</b>@endif
                @if ($t['note'])<span class="sstock-otile__n">{{ $t['note'] }}</span>@endif
            </a>
        @endif
    @endforeach
</div>
