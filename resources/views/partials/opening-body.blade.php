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
            <div class="sstock-ostat"><b class="{{ $s['alert'] ? 'is-alert' : '' }}">{{ $s['value'] }}</b><span>{{ $s['label'] }}</span></div>
        @endforeach
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
            <span class="sstock-otile is-soon"><span class="sstock-otile__h"><i class="{{ $t['icon'] }}" aria-hidden="true"></i> {{ $t['label'] }}<em>segera</em></span></span>
        @else
            <a class="sstock-otile" href="{{ $href($t) }}" @unless ($phone) data-dl-open-item="{{ $t['code'] }}" data-label="{{ $t['label'] }}" @endunless>
                <span class="sstock-otile__h"><i class="{{ $t['icon'] }}" aria-hidden="true"></i> {{ $t['label'] }}<em class="{{ $t['write'] ? 'is-w' : '' }}">{{ $t['write'] ? 'Tulis' : 'Baca' }}</em></span>
                @if ($t['n'] !== null && $t['n'] !== '')<b>{{ $t['n'] }}</b>@endif
                @if ($t['note'])<span class="sstock-otile__n">{{ $t['note'] }}</span>@endif
            </a>
        @endif
    @endforeach
</div>
