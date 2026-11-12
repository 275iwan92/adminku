{{-- REMARK
 Tujuan       : Isi layar pembuka kelompok menu / modul (desktop dan HP): header (judul, keterangan, hak), angka utama, perlu tindakan, pintasan, dan ubin layar anak (lencana Tulis / Baca, jumlah data). Modul yang belum dibuat (HR, Purchase) hanya menampilkan rencana layar, tanpa angka.
 Sumber hulu  : App\Support\Opening::build.
 Input        : page (section, title, icon, tone, tag, soon, write, stats, todo, shortcuts, tiles), phone (true = tautan HP).
 Output       : kelas sstock-o* (CSS di resources/css/app.css). Desktop berjalan di tab (iframe): tautan ber-data-dl2-open-item membuka layar sebagai tab kerja Layer Shell.
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
                <a class="sstock-obar" href="{{ $kasReport }}" @unless ($phone) data-dl2-open-item="R-KOUT" data-label="Laporan Pengeluaran" @endunless>
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
                    <div><span><u class="in" style="height: {{ round($t['in'] / $page['charts']['max'] * 100) }}%" title="Masuk Rp {{ \Devlib2\Locale::number($t['in'], 0) }}"></u><u class="out" style="height: {{ round($t['out'] / $page['charts']['max'] * 100) }}%" title="Keluar Rp {{ \Devlib2\Locale::number($t['out'], 0) }}"></u></span>{{ $t['label'] }}</div>
                @endforeach
            </div>
            <p class="sstock-onote"><i class="sstock-dot in"></i> masuk <i class="sstock-dot out"></i> keluar</p>
        </section>
    </div>
@endif
@if ($page['section'] === 'ga')
    @php $todoTotal = array_sum(array_column($page['todo'], 1)); @endphp
    <section class="sstock-todo">
        <h2><i class="fa-solid fa-bell" aria-hidden="true"></i> Perlu tindakan @if ($todoTotal)<span class="sstock-todo__n">{{ \Devlib2\Locale::number($todoTotal, 0) }}</span>@endif</h2>
        @forelse ($page['todo'] as [$tone, $n, $text, $icon, $code])
            <a class="sstock-trow is-{{ $tone }}" href="{{ $href(['code' => $code, 'url' => route(\App\Support\Modules::all()[$code][4])]) }}" @unless ($phone) data-dl2-open-item="{{ $code }}" data-label="{{ \App\Support\Modules::all()[$code][1] }}" @endunless>
                <i class="{{ $icon }}" aria-hidden="true"></i>
                <b>{{ \Devlib2\Locale::number($n, 0) }}</b>
                <span>{{ $text }}</span>
                <i class="fa-solid fa-chevron-right sstock-trow__go" aria-hidden="true"></i>
            </a>
        @empty
            <p class="sstock-tok"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Semua aman. Tidak ada yang perlu ditindaklanjuti.</p>
        @endforelse
    </section>
@endif
@if ($page['shortcuts'])
    <div class="sstock-ochips sstock-ohead--{{ $page['tone'] }}">
        <span>Pintasan</span>
        @foreach ($page['shortcuts'] as $s)
            <a href="{{ $href($s) }}" @unless ($phone) data-dl2-open-item="{{ $s['code'] }}" data-label="{{ $s['label'] }}" @endunless><i class="{{ $s['icon'] }}" aria-hidden="true"></i> {{ $s['label'] }}</a>
        @endforeach
    </div>
@endif
@php
    // Batang tipis per ubin: angka ubin relatif terhadap angka terbesar di halaman ini (hanya bila ada >= 2 ubin berangka).
    $nums = collect($page['tiles'])->mapWithKeys(fn ($t) => [$t['code'] => ctype_digit($d = preg_replace('/\D/', '', (string) $t['n'])) && $d !== '' && preg_match('/^[\d.]+$/', (string) $t['n']) ? (int) $d : null])->filter(fn ($v) => $v !== null);
    $maxNum = max([1, ...$nums->values()->all()]);
@endphp
<div class="sstock-otiles sstock-ohead--{{ $page['tone'] }}">
    @foreach ($page['tiles'] as $t)
        @if ($t['soon'])
            <span class="sstock-otile is-soon" data-hue="{{ $t['hue'] }}"><span class="sstock-otile__h"><i class="{{ $t['icon'] }}" aria-hidden="true"></i> {{ $t['label'] }}<em>segera</em></span></span>
        @else
            <a class="sstock-otile" data-hue="{{ $t['hue'] }}" href="{{ $href($t) }}" @unless ($phone) data-dl2-open-item="{{ $t['code'] }}" data-label="{{ $t['label'] }}" @endunless>
                <span class="sstock-otile__h"><i class="{{ $t['icon'] }}" aria-hidden="true"></i> {{ $t['label'] }}<em class="{{ $t['write'] ? 'is-w' : '' }}">{{ $t['write'] ? 'Tulis' : 'Baca' }}</em></span>
                @if ($t['n'] !== null && $t['n'] !== '')<b>{{ $t['n'] }}</b>@endif
                @if ($t['note'])<span class="sstock-otile__n">{{ $t['note'] }}</span>@endif
                @if ($nums->count() >= 2 && isset($nums[$t['code']]) && $nums[$t['code']] > 0)<span class="sstock-otile__bar" aria-hidden="true"><u style="flex: {{ $nums[$t['code']] }} 0 0"></u><u style="flex: {{ $maxNum - $nums[$t['code']] }} 0 0; background: transparent; min-width: 0"></u></span>@endif
            </a>
        @endif
    @endforeach
</div>
