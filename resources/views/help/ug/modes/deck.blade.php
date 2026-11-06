{{-- REMARK
 Tujuan       : Mode "Presentasi" bertingkat Panduan Pengguna: garis besar (slide promosi, Tahukah Anda, pemilih topik), presentasi per kelompok modul, dan presentasi per tugas (sub-modul, mis. Perawatan AC atau Surat Jalan) dengan slide "kamera" yang menyusuri langkah di tangkapan layar. Semua bergerak sendiri (putar otomatis, bisa dijeda) dan bisa dikendalikan panah, geser, dan titik.
 Input        : $catalog (GuideCatalog), $can.
 Dipakai oleh : help/user-guide.blade.php. Perilaku: resources/js/guide.js; gaya: resources/css/ug.css.
 Status       : Aktif.
--}}
@php
    $img = fn (string $n) => asset("media/ug/{$n}.png");
    $tips = $catalog['tips'];
    $groups = $catalog['groups'];
    $tours = $catalog['tours'];
    $mins = fn (array $t) => max(1, (int) round(count($t['steps']) * 0.5));
@endphp
<section class="ug-deck" aria-roledescription="carousel" aria-label="Presentasi AdminKU">
    <nav class="ug-crumb" aria-label="Posisi presentasi"></nav>
    <div class="ug-stage">

        {{-- Tingkat 1: garis besar --}}
        <div class="ug-set is-on" data-set="main" data-title="Garis besar">
            <article class="ug-s ug-hero is-on">
                <i class="ug-orb o1" aria-hidden="true"></i><i class="ug-orb o2" aria-hidden="true"></i><i class="ug-orb o3" aria-hidden="true"></i>
                <h2 class="ug-logo">AdminKU</h2>
                <p class="ug-tag">KERJA JADI RAPI</p>
                <p class="ug-sub">Stok, pembelian, kas, kendaraan dan AC, serta kepegawaian dalam satu aplikasi. Semua dikerjakan lewat layar yang sama polanya.</p>
                <button type="button" class="ug-cta" data-go="next">Mulai tur</button>
            </article>

            <article class="ug-s">
                <h2>Tiga langkah, semua modul</h2>
                <p class="ug-sub">Pola yang sama di setiap menu, jadi yang Anda pelajari di satu menu langsung berguna di menu lain.</p>
                <ol class="ug-flow">
                    <li><b>1</b><span>Siapkan Data Master</span><small>Barang, pemasok, penerima</small></li>
                    <li><b>2</b><span>Catat transaksi</span><small>Masuk, keluar, pesan, bayar</small></li>
                    <li><b>3</b><span>Baca laporan</span><small>Stok, kas, jadwal</small></li>
                </ol>
                <a class="ug-more" href="#grid" data-detail>Lihat cara memakai Grid dan Form</a>
            </article>

            @foreach ($groups as $g)
                <article class="ug-s ug-promo {{ $loop->index % 2 ? 'zout' : 'zin' }}">
                    <div class="ug-copy">
                        <span class="ug-badge" style="--h: {{ $g['hue'] }}">{{ $loop->iteration }}</span>
                        <h2>{{ $g['title'] }}</h2>
                        <p class="ug-sub">{{ $g['tag'] }}</p>
                        <div class="ug-chips">
                            @foreach ($g['tours'] as $t)<button type="button" class="ug-chip" data-open="t-{{ $t['id'] }}">{{ $t['title'] }}</button>@endforeach
                        </div>
                        <button type="button" class="ug-more" data-open="g-{{ $g['key'] }}">Presentasi {{ $g['title'] }} &rsaquo;</button>
                    </div>
                    <div class="ug-frame" aria-hidden="true"><div class="ug-bt"><i></i><i></i><i></i></div><div class="ug-clip"><img src="{{ $img($g['shot']) }}" alt="" width="1440" height="900" decoding="async"></div></div>
                </article>
            @endforeach

            <article class="ug-s ug-tips">
                <span class="ug-badge">?</span>
                <h2>Tahukah Anda?</h2>
                <p class="ug-sub">Fitur yang sering terlewat, padahal menghemat banyak klik.</p>
                <div class="ug-tipgrid">
                    @foreach ($tips as $k => [$ic, $t, $d, $a])
                        <a class="ug-tipcard" href="#{{ $a }}" data-detail style="--i: {{ $k }}"><b>{{ $ic }}</b><span>{{ $t }}</span><small>{{ $d }}</small></a>
                    @endforeach
                </div>
            </article>

            <article class="ug-s ug-picker" data-picker>
                <h2>Pilih topik presentasi</h2>
                <p class="ug-sub">Setiap topik punya presentasi sendiri. Mulai dari kelompok, atau langsung ke satu tugas.</p>
                <div class="ug-pickgrid">
                    @foreach ($groups as $g)
                        <div class="ug-pickcard" style="--h: {{ $g['hue'] }}">
                            <button type="button" class="ug-pickhead" data-open="g-{{ $g['key'] }}"><b>{{ $g['title'] }}</b><small>{{ $g['sub'] }}</small></button>
                            <div class="ug-chips">@foreach ($g['tours'] as $t)<button type="button" class="ug-chip" data-open="t-{{ $t['id'] }}">{{ $t['title'] }}</button>@endforeach</div>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="ug-s">
                <h2>Mulai dalam lima langkah</h2>
                <ol class="ug-flow five">
                    <li><b>1</b><span>Masuk</span></li>
                    <li><b>2</b><span>Isi Data Master</span></li>
                    <li><b>3</b><span>Isi stok awal</span></li>
                    <li><b>4</b><span>Catat transaksi</span></li>
                    <li><b>5</b><span>Periksa Laporan</span></li>
                </ol>
                <a class="ug-cta" href="#mulai" data-detail>Buka panduan lengkap</a>
            </article>
        </div>

        {{-- Tingkat 2: per kelompok modul --}}
        @foreach ($groups as $g)
            <div class="ug-set" data-set="g-{{ $g['key'] }}" data-parent="main" data-title="{{ $g['title'] }}">
                <article class="ug-s ug-hero ug-ghero" style="--h: {{ $g['hue'] }}">
                    <i class="ug-orb o1" aria-hidden="true"></i><i class="ug-orb o2" aria-hidden="true"></i>
                    <p class="ug-tag">{{ strtoupper($g['sub']) }}</p>
                    <h2 class="ug-logo">{{ $g['title'] }}</h2>
                    <p class="ug-sub">{{ $g['tag'] }}</p>
                    <div class="ug-chips ug-chips-light">@foreach ($g['tours'] as $t)<button type="button" class="ug-chip" data-open="t-{{ $t['id'] }}">{{ $t['title'] }}</button>@endforeach</div>
                </article>
                @foreach ($g['tours'] as $t)
                    <article class="ug-s ug-promo {{ $loop->index % 2 ? 'zout' : 'zin' }}">
                        <div class="ug-copy">
                            <span class="ug-badge" style="--h: {{ $g['hue'] }}">{{ $loop->iteration }}</span>
                            <h2>{{ $t['title'] }}</h2>
                            <p class="ug-sub">{{ $t['summary'] }}</p>
                            <ol class="ug-mini">@foreach ($t['flow'] as $f)<li>{{ $f }}</li>@endforeach</ol>
                            <div class="ug-chips">
                                <button type="button" class="ug-chip" data-open="t-{{ $t['id'] }}">Presentasi tugas ini</button>
                                <button type="button" class="ug-chip" data-tour="{{ $t['id'] }}">Coba sendiri</button>
                                <a class="ug-chip" href="#{{ $t['anchor'] }}" data-detail>Detail</a>
                            </div>
                        </div>
                        <div class="ug-frame" aria-hidden="true"><div class="ug-bt"><i></i><i></i><i></i></div><div class="ug-clip"><img src="{{ $img($t['shot']) }}" alt="" width="1440" height="900" decoding="async"></div></div>
                    </article>
                @endforeach
            </div>
        @endforeach

        {{-- Tingkat 3: per tugas (sub-modul) --}}
        @foreach ($tours as $t)
            @php $g = $groups[$t['group']]; @endphp
            <div class="ug-set" data-set="t-{{ $t['id'] }}" data-parent="g-{{ $t['group'] }}" data-title="{{ $t['title'] }}">
                <article class="ug-s ug-promo zin">
                    <div class="ug-copy">
                        <span class="ug-badge" style="--h: {{ $g['hue'] }}">{{ count($t['steps']) }}</span>
                        <h2>{{ $t['title'] }}</h2>
                        <p class="ug-sub">{{ $t['summary'] }}</p>
                        <p class="ug-meta">{{ count($t['steps']) }} langkah &middot; sekitar {{ $mins($t) }} menit</p>
                    </div>
                    <div class="ug-frame" aria-hidden="true"><div class="ug-bt"><i></i><i></i><i></i></div><div class="ug-clip"><img src="{{ $img($t['shot']) }}" alt="" width="1440" height="900" decoding="async"></div></div>
                </article>
                <article class="ug-s">
                    <h2>Alurnya</h2>
                    <ol class="ug-flow five">
                        @foreach ($t['flow'] as $fi => $f)<li><b>{{ $fi + 1 }}</b><span>{{ $f }}</span></li>@endforeach
                    </ol>
                </article>
                <article class="ug-s ug-walk" data-dur="{{ count($t['steps']) * 3400 + 1500 }}" data-steps="{{ json_encode(array_map(fn ($s) => ['x' => $s[0], 'y' => $s[1], 't' => $s[2], 'd' => $s[3]], $t['steps']), JSON_UNESCAPED_UNICODE) }}">
                    <h2>Lihat langsung</h2>
                    <div class="ug-walkbox">
                        <div class="ug-cam"><img src="{{ $img($t['shot']) }}" alt="" width="1440" height="900" decoding="async"><i class="ug-ring"></i></div>
                        <div class="ug-cap" aria-live="polite"><b></b><span></span></div>
                    </div>
                </article>
                <article class="ug-s">
                    <span class="ug-badge" style="--h: {{ $g['hue'] }}">&#10003;</span>
                    <h2>Sekarang giliran Anda</h2>
                    <p class="ug-sub">Coba sendiri di tangkapan layar, atau baca panduannya selengkap-lengkapnya.</p>
                    <div class="ug-chips">
                        <button type="button" class="ug-cta" data-tour="{{ $t['id'] }}">Coba sendiri</button>
                        <button type="button" class="ug-chip" data-blog="{{ $t['id'] }}">Baca artikelnya</button>
                        <a class="ug-chip" href="#{{ $t['anchor'] }}" data-detail>Panduan lengkap</a>
                        @foreach ($t['related'] as $r)<button type="button" class="ug-chip" data-open="t-{{ $r }}">Berikutnya: {{ $tours[$r]['title'] }}</button>@endforeach
                    </div>
                </article>
            </div>
        @endforeach
    </div>

    <div class="ug-ctl">
        <button type="button" data-go="prev" aria-label="Slide sebelumnya">&#8249;</button>
        <div class="ug-dots" role="tablist" aria-label="Pilih slide"></div>
        <button type="button" data-go="next" aria-label="Slide berikutnya">&#8250;</button>
        <button type="button" class="ug-play" data-play aria-pressed="false">Putar otomatis</button>
        <button type="button" class="ug-play" data-open="picker">Daftar topik</button>
    </div>
    <div class="ug-bar" aria-hidden="true"><i></i></div>
</section>
