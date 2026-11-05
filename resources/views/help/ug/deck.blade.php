{{-- REMARK
 Tujuan       : Mode "Presentasi" Panduan Pengguna: slide promosi bergerak (tangkapan layar nyata dengan zoom masuk/keluar), slide "Tahukah Anda" untuk fitur yang sering terlewat, dan Misi interaktif ala tutorial gim (klik titik berdenyut di tangkapan layar, kamera zoom ke titik itu). Tombol dan chip menaut ke anchor bagian Detail yang sama; hanya modul yang boleh dibuka pengguna ($can) yang tampil.
 Sumber hulu  : $can, $any* dari help/user-guide.blade.php; gambar di public/media/ug (tangkapan layar data demo PT XYZ, diambil ulang bila tampilan berubah).
 Dipakai oleh : help/user-guide.blade.php. Gaya: .ug-* di resources/css/app.css; perilaku: skrip di akhir user-guide.
 Status       : Aktif.
--}}
@php
    $img = fn (string $n) => asset("media/ug/{$n}.png");
    // Slide promosi: [kunci, gambar, judul, tagline, anchor detail, [[label, kode modul, anchor], ...]]
    $promo = [];
    if ($anyTrx || $anyMaster) {
        $promo[] = ['stok', 'products', 'Stok yang selalu cocok', 'Catat barang masuk dan keluar. AdminKU menghitung sisanya, menjaga kedaluwarsa, dan mengingatkan titik pesan ulang.', $anyTrx ? 'transaksi' : 'master', [['Barang', 'ITM', 'm-itm'], ['Penerimaan Barang', 'RCV', 't-rcv'], ['Pengeluaran Barang', 'ISS', 't-iss'], ['Stok Opname', 'SO', 't-so'], ['Surat Jalan', 'SJL', 't-sjl']]];
    }
    if ($anyPur) {
        $promo[] = ['beli', 'pr', 'Pembelian rapi, dari permintaan sampai barang datang', 'Permintaan Pembelian, Keputusan Pesanan, PO, lalu PV dengan pemindaian barang datang.', 'purchase', [['Permintaan Pembelian', 'PUP', 'pur-pup'], ['Keputusan Pesanan', 'PUK', 'pur-puk'], ['Pesanan Pembelian', 'PUO', 'pur-puo'], ['Penerimaan Pembelian', 'PUV', 'pur-puv']]];
    }
    if ($anyKas) {
        $promo[] = ['kas', 'cashout', 'Kas terbaca jelas', 'Uang masuk, uang keluar, dan arus kas per rekening dalam satu buku besar.', 'kas', [['Uang Masuk', 'KSM', 'kas-ksm'], ['Uang Keluar', 'KSK', 'kas-ksk'], ['Kas / Rekening', 'KSR', 'kas-ksr'], ['Arus Kas', 'R-KFLOW', 'kas-rflow']]];
    }
    if ($anyHr) {
        $promo[] = ['sdm', 'employees', 'Karyawan dan rekrutmen dalam satu tempat', 'Data karyawan, kandidat yang sedang diproses, dan catatan kedisiplinan.', 'hr', [['Karyawan', 'HRE', 'hr-hre'], ['Rekrutmen', 'HRR', 'hr-hrr'], ['Kedisiplinan', 'HRD', 'hr-hrd']]];
    }
    if ($anyGa) {
        $promo[] = ['ga', 'fleet', 'Perawatan terpantau sebelum terlambat', 'Dokumen, servis, dan odometer kendaraan serta jadwal perawatan AC, dengan status Aman, Segera, dan Terlambat.', 'ga', [['Monitoring Kendaraan', 'GVM', 'ga-gvm'], ['Catat Servis', 'GVS', 'ga-gvs'], ['Perawatan AC', 'GAM', 'ga-gam'], ['Penjadwalan AC', 'GAS', 'ga-gas']]];
    }
    if ($anyRpt) {
        $promo[] = ['lap', 'summary', 'Laporan siap dibaca', 'Ringkasan, Kartu Stok, penerimaan, pengeluaran, dan barang hampir kedaluwarsa, kapan saja.', 'laporan', [['Ringkasan Stok', 'R-SUM', 'r-sum'], ['Kartu Stok', 'R-CARD', 'r-card'], ['Kedaluwarsa', 'R-EXP', 'r-exp']]];
    }
    if ($anySec) {
        $promo[] = ['aman', 'home', 'Hak akses sesuai peran', 'Atur pengguna dan grup supaya setiap orang hanya melihat menu yang perlu.', 'keamanan', [['Pengguna', 'USR', 'sec-usr'], ['Grup dan hak akses', 'GRP', 'sec-grp']]];
    }

    // Fitur yang sering terlewat: [ikon, judul, keterangan, anchor, kode modul atau null]
    $tips = [
        ['F1', 'F1 di layar mana pun', 'Membuka panduan ini tanpa mencari menu.', 'menu', null],
        ['2x', 'Klik 2x pada baris', 'Membuka data di form, sama dengan ikon pensil.', 'grid', null],
        ['+', 'Tombol plus', 'Pemasok atau Penerima belum ada? Tambah dari form yang sama, langsung terpilih.', 'tombol-plus', null],
        ['CSV', 'Ekspor ke Excel', 'Grid diunduh sesuai pencarian, saringan, dan kolom yang tampil.', 'grid', null],
        ['Tab', 'Banyak tab sekaligus', 'Pindah antar menu tanpa kehilangan isian.', 'menu', null],
        ['Klik', 'Kotak ringkasan Beranda', 'Setiap kotak membuka menu terkait.', 'beranda', null],
        ['Pesan', 'Catat Pemesanan', 'Tandai barang menipis sudah dipesan; perkiraan tiba dihitung otomatis.', 'catat-pemesanan', null],
        ['Scan', 'Scan barang datang', 'Penerimaan Pembelian mencocokkan kode barang ke baris PO.', 'pur-puv', 'PUV'],
    ];
    $tips = array_values(array_filter($tips, fn ($t) => $t[4] === null || $can($t[4])));

    // Misi interaktif: [kunci, gambar, judul, [[x%, y%, judul, teks], ...], syarat modul]
    $misi = [
        ['beranda', 'home', 'Misi 1: Kenali Beranda', [
            [26, 24, 'Kotak ringkasan', 'Setiap kotak bisa diklik dan membuka menu terkait, misalnya barang di titik pesan ulang.'],
            [27, 33, 'Panduan Pengguna', 'Tombol ini membuka panduan. Di layar mana pun, tombol F1 melakukan hal yang sama.'],
            [14, 95, 'Menu profil', 'Klik nama Anda: Notifikasi, Edit profil, Bantuan, Bahasa, Ubah password, dan Keluar.'],
        ], null],
        ['grid', 'products', 'Misi 2: Kuasai Grid', [
            [14, 4, 'Kotak hitung', 'Klik satu kotak (mis. Pesan ulang) untuk menyaring grid. Klik Semua untuk kembali.'],
            [11, 9, 'Cari', 'Ketik sebagian kata, grid langsung menyempit. Tulisan di kotak menyebut kolom yang dicari.'],
            [55, 14, 'Judul kolom', 'Klik judul kolom untuk mengurutkan naik atau turun.'],
            [81, 9, 'Kolom', 'Pilih kolom yang ingin tampil. Hanya mengubah tampilan Anda.'],
            [88, 9, 'Ekspor', 'Unduh isi grid sebagai CSV untuk Excel, sesuai pencarian dan saringan.'],
            [49, 20, 'Klik 2x pada baris', 'Membuka datanya di form, sama dengan ikon pensil.'],
        ], 'ITM'],
        ['form', 'recv', 'Misi 3: Isi Form Tanpa Pindah Layar', [
            [32.4, 22, 'Tombol plus', 'Pemasok belum ada? Tekan + dan isi kotak kecil. Datanya masuk master dan langsung terpilih.'],
            [93, 39, '+ Tambah Barang', 'Menambah baris barang. Ketik sebagian kode atau nama, lalu pilih dari daftar.'],
            [16, 46, 'Cari barang', 'Satu barang hanya muncul sekali per dokumen. Kolom abu-abu dihitung otomatis.'],
            [88, 3.6, 'Simpan', 'Simpan membuat Draf, stok belum berubah. Stok baru berubah saat dokumen dikonfirmasi.'],
        ], 'RCV'],
    ];
    $misi = array_values(array_filter($misi, fn ($m) => $m[4] === null || $can($m[4])));
    $total = 2 + count($promo) + 1 + count($misi) + 1;
    $n = 0;
@endphp
<section class="ug-deck" aria-roledescription="carousel" aria-label="Presentasi AdminKU">
    <div class="ug-stage">
        <article class="ug-s ug-hero is-on" aria-label="Slide {{ ++$n }}">
            <i class="ug-orb o1" aria-hidden="true"></i><i class="ug-orb o2" aria-hidden="true"></i><i class="ug-orb o3" aria-hidden="true"></i>
            <h2 class="ug-logo">AdminKU</h2>
            <p class="ug-tag">KERJA JADI RAPI</p>
            <p class="ug-sub">Stok, pembelian, kas, kendaraan dan AC, serta kepegawaian dalam satu aplikasi. Semua dikerjakan lewat layar yang sama polanya.</p>
            <button type="button" class="ug-cta" data-go="next">Mulai tur</button>
        </article>

        <article class="ug-s" aria-label="Slide {{ ++$n }}">
            <h2>Tiga langkah, semua modul</h2>
            <p class="ug-sub">Pola yang sama di setiap menu, jadi yang Anda pelajari di satu menu langsung berguna di menu lain.</p>
            <ol class="ug-flow">
                <li><b>1</b><span>Siapkan Data Master</span><small>Barang, pemasok, penerima</small></li>
                <li><b>2</b><span>Catat transaksi</span><small>Masuk, keluar, pesan, bayar</small></li>
                <li><b>3</b><span>Baca laporan</span><small>Stok, kas, jadwal</small></li>
            </ol>
            <a class="ug-more" href="#grid" data-detail>Lihat cara memakai Grid dan Form</a>
        </article>

        @foreach ($promo as $i => [$key, $pic, $title, $tag, $anchor, $links])
            <article class="ug-s ug-promo {{ $i % 2 ? 'zout' : 'zin' }}" aria-label="Slide {{ ++$n }}">
                <div class="ug-copy">
                    <span class="ug-badge">{{ $i + 1 }}</span>
                    <h2>{{ $title }}</h2>
                    <p class="ug-sub">{{ $tag }}</p>
                    <div class="ug-chips">
                        @foreach ($links as [$label, $code, $a])
                            @if ($can($code))<a class="ug-chip" href="#{{ $a }}" data-detail>{{ $label }}</a>@endif
                        @endforeach
                    </div>
                    <a class="ug-more" href="#{{ $anchor }}" data-detail>Buka panduan lengkap</a>
                </div>
                <div class="ug-frame" aria-hidden="true"><div class="ug-bt"><i></i><i></i><i></i></div><div class="ug-clip"><img src="{{ $img($pic) }}" alt="" decoding="async"></div></div>
            </article>
        @endforeach

        <article class="ug-s ug-tips" aria-label="Slide {{ ++$n }}">
            <span class="ug-badge">?</span>
            <h2>Tahukah Anda?</h2>
            <p class="ug-sub">Fitur yang sering terlewat, padahal menghemat banyak klik.</p>
            <div class="ug-tipgrid">
                @foreach ($tips as $k => [$ic, $t, $d, $a])
                    <a class="ug-tipcard" href="#{{ $a }}" data-detail style="--i:{{ $k }}"><b>{{ $ic }}</b><span>{{ $t }}</span><small>{{ $d }}</small></a>
                @endforeach
            </div>
        </article>

        @foreach ($misi as [$key, $pic, $title, $hots])
            <article class="ug-s ug-misi" data-misi="{{ count($hots) }}" aria-label="Slide {{ ++$n }}">
                <h2>{{ $title }}</h2>
                <div class="ug-play-area">
                    <div class="ug-shotbox">
                        <div class="ug-cam">
                            <img src="{{ $img($pic) }}" alt="Tangkapan layar untuk {{ $title }}" decoding="async">
                            @foreach ($hots as $h => [$x, $y, $ht, $tx])
                                <button type="button" class="ug-hot" data-step="{{ $h }}" data-x="{{ $x }}" data-y="{{ $y }}" data-t="{{ $ht }}" data-d="{{ $tx }}" style="left:{{ $x }}%;top:{{ $y }}%" aria-label="Klik: {{ $ht }}"></button>
                            @endforeach
                        </div>
                    </div>
                    <div class="ug-say" aria-live="polite">
                        <div class="ug-prog"><span>0</span>/{{ count($hots) }}</div>
                        <b class="ug-st">Klik titik yang berdenyut</b>
                        <p class="ug-sd">Jelajahi tangkapan layar ini. Setiap titik mengungkap satu fitur.</p>
                        <div class="ug-win">Misi selesai! Anda sudah mengenal semua titiknya.</div>
                        <button type="button" class="ug-redo">Ulangi misi</button>
                    </div>
                </div>
            </article>
        @endforeach

        <article class="ug-s" aria-label="Slide {{ ++$n }}">
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

    <div class="ug-ctl">
        <button type="button" data-go="prev" aria-label="Slide sebelumnya">&#8249;</button>
        <div class="ug-dots" role="tablist" aria-label="Pilih slide">
            @for ($k = 0; $k < $n; $k++)<button type="button" role="tab" data-slide="{{ $k }}" aria-label="Slide {{ $k + 1 }}"></button>@endfor
        </div>
        <button type="button" data-go="next" aria-label="Slide berikutnya">&#8250;</button>
        <button type="button" class="ug-play" data-play aria-pressed="false">Putar otomatis</button>
    </div>
    <div class="ug-bar" aria-hidden="true"><i></i></div>
</section>
