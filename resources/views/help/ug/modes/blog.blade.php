{{-- REMARK
 Tujuan       : Mode "Blog" Panduan Pengguna (gaya Pinterest): beranda kartu bersarang (kelompok, lalu tugas, lalu artikel) dengan ilustrasi potongan layar, pencarian dan saringan kelompok; tiap tugas punya artikel berisi alur animasi, diagram, langkah bergambar, tips, dan tautan "baca berikutnya" ke artikel lain, ke Presentasi, ke misi Interaktif, dan ke bagian Detail.
                Artikel diberi id gb-{id}; sub-bagian gb-{id}--{bagian}. Tanpa JS semuanya tampil bertumpuk.
 Input        : $catalog (GuideCatalog).
 Dipakai oleh : help/user-guide.blade.php. Perilaku: resources/js/guide.js; gaya: resources/css/ug.css.
 Status       : Aktif.
--}}
@php
    $groups = $catalog['groups'];
    $tours = $catalog['tours'];
    $flows = $catalog['flows'];
    $actors = ['de' => 'Data Entry', 'sp' => 'Supervisor', 'si' => 'Sistem', 'vn' => 'Vendor'];
    $mins = fn (array $t) => max(1, (int) round(count($t['steps']) * 0.5));
@endphp
<section class="ug-blog" id="gb" aria-label="Panduan bergambar">
    <div class="ug-bhome" data-bhome>
        <header class="ug-bhead">
            <h2>Panduan bergambar</h2>
            <p class="ug-sub">Pilih topik, baca langkah-langkahnya dengan gambar dan alur. Setiap artikel menaut ke artikel terkait, presentasi, dan latihan interaktif.</p>
            <div class="ug-bfilter" role="search">
                <input type="search" data-bsearch placeholder="Cari tugas, misalnya PO, AC, opname" aria-label="Cari artikel panduan">
                <div class="ug-chips" role="group" aria-label="Saring kelompok">
                    <button type="button" class="ug-chip is-on" data-bgroup="">Semua</button>
                    @foreach ($groups as $g)<button type="button" class="ug-chip" data-bgroup="{{ $g['key'] }}">{{ $g['title'] }}</button>@endforeach
                </div>
            </div>
        </header>

        <div class="ug-bhubs">
            @foreach ($groups as $g)
                <article class="ug-bhub" style="--h: {{ $g['hue'] }}" data-bgroup-card="{{ $g['key'] }}">
                    @include('help.ug.modes.crop', ['shot' => $g['shot'], 'x' => 20, 'y' => 15, 'zoom' => 1.3, 'ring' => false])
                    <div class="ug-bhubbody">
                        <h3>{{ $g['title'] }}</h3>
                        <p>{{ $g['tag'] }}</p>
                        <ul>@foreach ($g['tours'] as $t)<li><a href="#gb-{{ $t['id'] }}" data-blog-link>{{ $t['title'] }}</a></li>@endforeach</ul>
                    </div>
                </article>
            @endforeach
        </div>

        <ul class="ug-legend" aria-label="Pelaku di diagram alur">
            @foreach ($actors as $k => $label)<li class="a-{{ $k }}"><i aria-hidden="true"></i>{{ $label }}</li>@endforeach
        </ul>
        <div class="ug-masonry" data-bcards>
            @foreach ($flows as $f)
                <article class="ug-card ug-pin" style="--h: 280" data-bgroup-card="alur" data-btext="{{ mb_strtolower($f['title'].' alur '.implode(' ', array_column($f['nodes'], 1))) }}">
                    <span class="ug-tag2">Alur</span>
                    <h3><a href="#gb-{{ $f['tour'] }}" data-blog-link>{{ $f['title'] }}</a></h3>
                    <ol class="ug-pinflow">
                        @foreach ($f['nodes'] as $i => [$actor, $label])
                            <li class="a-{{ $actor }}" style="--i: {{ $i }}"><em>{{ $actors[$actor] }}</em><span>{{ $label }}</span></li>
                        @endforeach
                    </ol>
                    <div class="ug-cardact"><a href="#gb-{{ $f['tour'] }}" data-blog-link>Baca artikelnya</a><button type="button" data-open-deck="t-{{ $f['tour'] }}">Presentasi</button></div>
                </article>
            @endforeach
            @foreach ($tours as $t)
                @php $g = $groups[$t['group']]; $s0 = $t['steps'][0]; @endphp
                <article class="ug-card" style="--h: {{ $g['hue'] }}" data-bgroup-card="{{ $t['group'] }}" data-btext="{{ mb_strtolower($t['title'].' '.$t['summary'].' '.$g['title'].' '.implode(' ', $t['flow'])) }}">
                    @include('help.ug.modes.crop', ['shot' => $t['shot'], 'x' => $s0[0], 'y' => $s0[1], 'zoom' => 1.9, 'ring' => true])
                    <div class="ug-cardbody">
                        <span class="ug-tag2">{{ $g['title'] }}</span>
                        <h3><a href="#gb-{{ $t['id'] }}" data-blog-link>{{ $t['title'] }}</a></h3>
                        <p>{{ $t['summary'] }}</p>
                        <small>{{ count($t['steps']) }} langkah &middot; sekitar {{ $mins($t) }} menit</small>
                        <div class="ug-cardact">
                            <a href="#gb-{{ $t['id'] }}" data-blog-link>Baca</a>
                            <button type="button" data-open-deck="t-{{ $t['id'] }}">Presentasi</button>
                            <button type="button" data-tour="{{ $t['id'] }}">Coba</button>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        <p class="ug-empty" data-bempty hidden>Tidak ada artikel yang cocok. Ubah kata kunci atau pilih Semua.</p>
    </div>

    @foreach ($tours as $t)
        @php
            $g = $groups[$t['group']];
            $sibs = array_values(array_keys($g['tours']));
            $pos = array_search($t['id'], $sibs, true);
            $prev = $pos > 0 ? $tours[$sibs[$pos - 1]] : null;
            $next = $pos < count($sibs) - 1 ? $tours[$sibs[$pos + 1]] : null;
        @endphp
        <article class="ug-art" id="gb-{{ $t['id'] }}" data-art="{{ $t['id'] }}" style="--h: {{ $g['hue'] }}" aria-labelledby="gb-{{ $t['id'] }}--h">
            <nav class="ug-bcrumb" aria-label="Posisi artikel">
                <a href="#gb" data-blog-home>Panduan</a><span aria-hidden="true">&rsaquo;</span>
                <a href="#gb" data-blog-home data-bgroup-go="{{ $g['key'] }}">{{ $g['title'] }}</a><span aria-hidden="true">&rsaquo;</span>
                <span aria-current="page">{{ $t['title'] }}</span>
            </nav>
            <div class="ug-arthero">
                @include('help.ug.modes.crop', ['shot' => $t['shot'], 'x' => 50, 'y' => 30, 'zoom' => 1.15, 'ring' => false])
                <div>
                    <span class="ug-tag2">{{ $g['title'] }}</span>
                    <h1 id="gb-{{ $t['id'] }}--h">{{ $t['title'] }}</h1>
                    <p class="ug-lead2">{{ $t['summary'] }}</p>
                    <p class="ug-meta">{{ count($t['steps']) }} langkah &middot; sekitar {{ $mins($t) }} menit baca</p>
                    <div class="ug-chips">
                        <button type="button" class="ug-cta" data-tour="{{ $t['id'] }}">Coba interaktif</button>
                        <button type="button" class="ug-chip" data-open-deck="t-{{ $t['id'] }}">Lihat presentasi</button>
                        <a class="ug-chip" href="#{{ $t['anchor'] }}" data-detail>Panduan lengkap</a>
                    </div>
                </div>
            </div>

            <div class="ug-artbody">
                <nav class="ug-toc2" aria-label="Di artikel ini">
                    <b>Di artikel ini</b>
                    <a href="#gb-{{ $t['id'] }}--alur">Alurnya</a>
                    @foreach ($t['steps'] as $i => $s)<a href="#gb-{{ $t['id'] }}--s{{ $i + 1 }}">{{ $i + 1 }}. {{ $s[2] }}</a>@endforeach
                    <a href="#gb-{{ $t['id'] }}--tips">Tips</a>
                    <a href="#gb-{{ $t['id'] }}--lanjut">Baca berikutnya</a>
                </nav>

                <div class="ug-artmain">
                    <section id="gb-{{ $t['id'] }}--alur">
                        <h2>Alurnya</h2>
                        <ol class="ug-flowline">
                            @foreach ($t['flow'] as $i => $f)<li style="--i: {{ $i }}"><b>{{ $i + 1 }}</b><span>{{ $f }}</span></li>@endforeach
                        </ol>
                        @if ($t['diagram'])
                            @include('help.diagram', ['name' => $t['diagram'], 'caption' => 'Diagram: '.$t['title'].'.', 'idp' => 'gb-'.$t['id']])
                        @endif
                    </section>

                    <section>
                        <h2>Langkah demi langkah</h2>
                        @foreach ($t['steps'] as $i => [$x, $y, $ht, $tx])
                            <div class="ug-step {{ $i % 2 ? 'rev' : '' }}" id="gb-{{ $t['id'] }}--s{{ $i + 1 }}">
                                @include('help.ug.modes.crop', ['shot' => $t['shot'], 'x' => $x, 'y' => $y, 'zoom' => 2.4, 'ring' => true])
                                <div>
                                    <span class="ug-stepno">{{ $i + 1 }}</span>
                                    <h3>{{ $ht }}</h3>
                                    <p>{{ $tx }}</p>
                                </div>
                            </div>
                        @endforeach
                    </section>

                    <section id="gb-{{ $t['id'] }}--tips">
                        <h2>Tips</h2>
                        <div class="ug-tips2">
                            @foreach ($t['tips'] as $tip)<p>{{ $tip }}</p>@endforeach
                        </div>
                    </section>

                    <section id="gb-{{ $t['id'] }}--lanjut">
                        <h2>Baca berikutnya</h2>
                        <div class="ug-next">
                            @if ($prev)<a href="#gb-{{ $prev['id'] }}" data-blog-link><small>&lsaquo; Sebelumnya</small><b>{{ $prev['title'] }}</b></a>@endif
                            @if ($next)<a href="#gb-{{ $next['id'] }}" data-blog-link><small>Selanjutnya &rsaquo;</small><b>{{ $next['title'] }}</b></a>@endif
                            @foreach ($t['related'] as $r)
                                @if (($prev['id'] ?? null) !== $r && ($next['id'] ?? null) !== $r)
                                    <a href="#gb-{{ $r }}" data-blog-link><small>Terkait</small><b>{{ $tours[$r]['title'] }}</b></a>
                                @endif
                            @endforeach
                            <a href="#{{ $t['anchor'] }}" data-detail><small>Bagian Detail</small><b>Panduan lengkap {{ $t['title'] }}</b></a>
                        </div>
                    </section>
                </div>
            </div>
        </article>
    @endforeach
</section>
