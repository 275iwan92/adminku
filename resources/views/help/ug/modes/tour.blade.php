{{-- REMARK
 Tujuan       : Mode "Interaktif" Panduan Pengguna: misi ala tutorial gim. Pengguna memilih satu tugas, lalu mengklik titik berdenyut di tangkapan layar nyata; tiap klik memunculkan popup penjelas (Driver.js), progres naik, dan misi selesai tercatat (localStorage per pengguna). Setelah tur, semua titik tetap bisa dijelajahi bebas.
 Input        : $catalog (GuideCatalog), $user.
 Dipakai oleh : help/user-guide.blade.php. Perilaku: resources/js/guide.js (modul tour); gaya: resources/css/ug.css.
 Status       : Aktif.
--}}
@php
    $groups = $catalog['groups'];
    $tours = $catalog['tours'];
    $img = fn (string $n) => asset("media/ug/{$n}.png");
@endphp
<section class="ug-tourmode" data-uid="{{ $user->id }}" data-total="{{ count($tours) }}" aria-label="Panduan interaktif">
    <div class="ug-tlist">
        <header class="ug-thead">
            <div>
                <h2>Misi interaktif</h2>
                <p class="ug-sub">Klik titik berdenyut di layar contoh, popup menjelaskan tombol demi tombol. Satu misi sekitar dua menit.</p>
            </div>
            <div class="ug-tscore" aria-live="polite">
                <b data-score>0</b><span>/ {{ count($tours) }} misi selesai</span>
                <div class="ug-tbar"><i data-scorebar></i></div>
                <button type="button" class="ug-link" data-reset hidden>Ulang dari awal</button>
            </div>
        </header>
        @foreach ($groups as $g)
            <section class="ug-tgroup" style="--h: {{ $g['hue'] }}">
                <h3>{{ $g['title'] }} <small>{{ $g['sub'] }}</small></h3>
                <div class="ug-tcards">
                    @foreach ($g['tours'] as $t)
                        <button type="button" class="ug-tcard" data-mission="{{ $t['id'] }}">
                            <span class="ug-tstate" data-state aria-hidden="true">&#9654;</span>
                            <b>{{ $t['title'] }}</b>
                            <small>{{ $t['summary'] }}</small>
                            <em>{{ count($t['steps']) }} langkah</em>
                        </button>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    @foreach ($tours as $t)
        <div class="ug-tstage" data-stage="{{ $t['id'] }}" hidden>
            <div class="ug-tbarrow">
                <button type="button" class="ug-link" data-back>&lsaquo; Semua misi</button>
                <h2>{{ $t['title'] }}</h2>
                <span class="ug-tprog" aria-live="polite"><b data-n>0</b> / {{ count($t['steps']) }}</span>
            </div>
            <div class="ug-tplay">
                <div class="ug-shotbox">
                    <div class="ug-cam">
                        <img src="{{ $img($t['shot']) }}" alt="Tangkapan layar: {{ $t['title'] }}" width="1440" height="900" decoding="async">
                        @foreach ($t['steps'] as $h => [$x, $y, $ht, $tx])
                            <button type="button" class="ug-hot" data-step="{{ $h }}" data-t="{{ $ht }}" data-d="{{ $tx }}" style="left: {{ $x }}%; top: {{ $y }}%" aria-label="{{ $ht }}"></button>
                        @endforeach
                    </div>
                </div>
                <aside class="ug-tside">
                    <p class="ug-sub" data-hint>{{ $t['summary'] }}</p>
                    <ol class="ug-tsteps">
                        @foreach ($t['steps'] as $h => $s)<li data-li="{{ $h }}"><span>{{ $s[2] }}</span></li>@endforeach
                    </ol>
                    <div class="ug-twin" data-win hidden>
                        <b>Misi selesai!</b>
                        <span>Anda sudah mengenal semua titik di layar ini.</span>
                        <div class="ug-chips">
                            @foreach ($t['related'] as $r)<button type="button" class="ug-chip" data-mission="{{ $r }}">Misi berikutnya: {{ $tours[$r]['title'] }}</button>@endforeach
                            <a class="ug-chip" href="#{{ $t['anchor'] }}" data-detail>Panduan lengkap</a>
                        </div>
                    </div>
                    <div class="ug-chips">
                        <button type="button" class="ug-cta" data-start>Mulai misi</button>
                        <button type="button" class="ug-chip" data-open-deck="t-{{ $t['id'] }}">Lihat presentasinya</button>
                    </div>
                    <p class="ug-note">Setelah misi, semua titik tetap bisa diklik untuk dijelajahi.</p>
                </aside>
            </div>
        </div>
    @endforeach
</section>
