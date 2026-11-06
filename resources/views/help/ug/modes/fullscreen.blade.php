{{-- REMARK
 Tujuan       : Bantuan & dukungan layar penuh: halaman beranimasi dari atas ke bawah (satu bagian per layar, scroll-snap, isi muncul bergantian) sebagai pintu masuk Panduan Pengguna: hero, tiga langkah, alur kerja, satu layar per kelompok modul (dengan tangkapan layar zoom), Tahukah Anda, lalu pilihan cara belajar. Tombol Tutup (dan Esc) menutupnya dan menampilkan halaman panduan di belakangnya.
                Di HP posisi tegak, tampilan diputar otomatis ke landscape (CSS; kunci orientasi asli dicoba saat layar penuh) dan bagian digeser mendatar sehingga usapan jari tetap vertikal.
 Input        : $catalog (GuideCatalog).
 Dipakai oleh : help/user-guide.blade.php. Perilaku: resources/js/guide.js (makeFullscreen); gaya: resources/css/ug.css (.ugf-*).
 Status       : Aktif. Tanpa JavaScript tidak tampil (display:none kecuali html.ugf-on yang dipasang skrip di user-guide).
--}}
@php
    $img = fn (string $n) => asset("media/ug/{$n}.png");
    $groups = $catalog['groups'];
    $flows = array_intersect_key($catalog['flows'], array_flip(['f-input', 'f-approval', 'f-laporan']));
    $tips = $catalog['tips'];
    $actors = ['de' => 'Data Entry', 'sp' => 'Supervisor', 'si' => 'Sistem', 'vn' => 'Vendor'];
@endphp
<div class="ugf" id="ugf" role="dialog" aria-modal="true" aria-label="Bantuan dan dukungan layar penuh" data-home="{{ route('home') }}">
    <header class="ugf-top">
        <div class="ugf-brand"><b>AdminKU</b><span>Bantuan &amp; dukungan</span></div>
        <div class="ugf-btns">
            <button type="button" class="ugf-ic" data-ugf-play aria-pressed="false" aria-label="Putar otomatis" title="Putar otomatis"><svg class="i-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg><svg class="i-pause" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14M16 5v14"/></svg></button>
            <button type="button" class="ugf-ic" data-ugf-music aria-pressed="false" aria-label="Musik latar, mati" title="Musik latar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18V6l10-2v12"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="16.5" cy="16" r="2.5"/><path class="i-off" d="M3 3l18 18"/></svg></button>
            <button type="button" data-ugf-full aria-label="Layar penuh"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg></button>
            <button type="button" class="ugf-close" data-ugf-close aria-label="Tutup bantuan dan kembali ke Beranda" title="Tutup dan kembali ke Beranda"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg><span>Tutup</span></button>
        </div>
    </header>
    <nav class="ugf-dots" aria-label="Bagian bantuan"></nav>
    <button type="button" class="ugf-nav prev" data-ugf-prev aria-label="Bagian sebelumnya"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg></button>
    <button type="button" class="ugf-nav next" data-ugf-next aria-label="Bagian berikutnya"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg></button>
    <div class="ugf-prog" aria-hidden="true"><i></i></div>

    <div class="ugf-scroll" tabindex="-1">
        <section class="ugf-s ugf-hero" aria-label="Pembuka">
            <i class="ug-orb o1" aria-hidden="true"></i><i class="ug-orb o2" aria-hidden="true"></i><i class="ug-orb o3" aria-hidden="true"></i>
            <div class="ugf-center">
                <h2 class="ugf-logo rv">AdminKU</h2>
                <p class="ugf-tag rv" style="--d: 150">KERJA JADI RAPI</p>
                <p class="ugf-sub rv" style="--d: 300">Stok, pembelian, kas, kendaraan dan AC, serta kepegawaian dalam satu aplikasi. Gulir untuk berkenalan.</p>
                <button type="button" class="ugf-play rv" style="--d: 450" data-ugf-play aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg><span>Putar otomatis</span></button>
                <button type="button" class="ugf-next rv" style="--d: 600" data-ugf-next aria-label="Mulai: ke bagian berikutnya"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></button>
            </div>
        </section>

        <section class="ugf-s" aria-label="Tiga langkah">
            <div class="ugf-center">
                <h2 class="ugf-h rv">Tiga langkah, semua modul</h2>
                <p class="ugf-sub rv" style="--d: 120">Polanya sama di setiap menu, jadi yang dipelajari di satu menu langsung berguna di menu lain.</p>
                <ol class="ugf-three">
                    <li class="rv" style="--d: 250"><b>1</b><span>Siapkan Data Master</span><small>Barang, pemasok, penerima</small></li>
                    <li class="rv" style="--d: 450"><b>2</b><span>Catat transaksi</span><small>Masuk, keluar, pesan, bayar</small></li>
                    <li class="rv" style="--d: 650"><b>3</b><span>Baca laporan</span><small>Stok, kas, jadwal</small></li>
                </ol>
            </div>
        </section>

        @if ($flows)
            <section class="ugf-s" aria-label="Alur kerja">
                <div class="ugf-center wide">
                    <h2 class="ugf-h rv">Alur kerja, dari input sampai laporan</h2>
                    <div class="ugf-flows">
                        @foreach ($flows as $f)
                            <div class="ugf-flow rv" style="--d: {{ 200 + $loop->index * 250 }}">
                                <h3>{{ $f['title'] }}</h3>
                                <ol>
                                    @foreach ($f['nodes'] as $i => [$actor, $label])
                                        <li class="a-{{ $actor }}" style="--i: {{ $i }}"><b>{{ $i + 1 }}</b><span>{{ $label }}</span><em>{{ $actors[$actor] }}</em></li>
                                    @endforeach
                                </ol>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @foreach ($groups as $g)
            @php $first = array_key_first($g['tours']); @endphp
            <section class="ugf-s ugf-grp {{ $loop->odd ? 'zin' : 'zout' }}" style="--h: {{ $g['hue'] }}" aria-label="{{ $g['title'] }}">
                <div class="ugf-split">
                    <div class="ugf-copy">
                        <span class="ugf-no rv">{{ $loop->iteration }}</span>
                        <h2 class="ugf-h rv" style="--d: 100">{{ $g['title'] }}</h2>
                        <p class="ugf-sub rv" style="--d: 200">{{ $g['tag'] }}</p>
                        <div class="ugf-chips rv" style="--d: 320">
                            @foreach ($g['tours'] as $t)<button type="button" data-ugf-go="#gd-t-{{ $t['id'] }}">{{ $t['title'] }}</button>@endforeach
                        </div>
                        <div class="ugf-acts rv" style="--d: 440">
                            <button type="button" data-ugf-go="#gd-g-{{ $g['key'] }}">Presentasi</button>
                            <button type="button" data-ugf-go="#gb-{{ $first }}">Baca artikel</button>
                            <button type="button" data-ugf-try="{{ $first }}">Coba sendiri</button>
                        </div>
                    </div>
                    <div class="ugf-frame rv" style="--d: 250" aria-hidden="true"><div class="ugf-bt"><i></i><i></i><i></i></div><div class="ugf-clip"><img src="{{ $img($g['shot']) }}" alt="" width="1440" height="900" loading="lazy"></div></div>
                </div>
            </section>
        @endforeach

        <section class="ugf-s" aria-label="Tahukah Anda">
            <div class="ugf-center wide">
                <h2 class="ugf-h rv">Tahukah Anda?</h2>
                <p class="ugf-sub rv" style="--d: 120">Fitur yang sering terlewat, padahal menghemat banyak klik.</p>
                <div class="ugf-tips">
                    @foreach ($tips as $k => [$ic, $t, $d, $a])
                        <a class="rv" style="--d: {{ 200 + $k * 90 }}" href="#{{ $a }}" data-ugf-go="#{{ $a }}"><b>{{ $ic }}</b><span>{{ $t }}</span><small>{{ $d }}</small></a>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="ugf-s" aria-label="Pilih cara belajar">
            <div class="ugf-center wide">
                <h2 class="ugf-h rv">Pilih cara belajar</h2>
                <div class="ugf-modes">
                    <button type="button" class="rv" style="--d: 150" data-ugf-mode="deck"><b>Presentasi</b><small>Ringkasan bergerak seperti video, bertingkat sampai per tugas.</small></button>
                    <button type="button" class="rv" style="--d: 300" data-ugf-mode="tour"><b>Interaktif</b><small>Klik titik di layar contoh, popup menjelaskan tombol demi tombol.</small></button>
                    <button type="button" class="rv" style="--d: 450" data-ugf-mode="blog"><b>Blog</b><small>Artikel bergambar, diagram alur, dan tautan antar artikel.</small></button>
                    <button type="button" class="rv" style="--d: 600" data-ugf-mode="detail"><b>Detail</b><small>Panduan lengkap per menu, untuk dicari dan dibaca runtut.</small></button>
                </div>
                <button type="button" class="ugf-link rv" style="--d: 750" data-ugf-dismiss>Lihat halaman panduan</button>
            </div>
        </section>
    </div>

</div>
