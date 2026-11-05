{{-- REMARK
 Tujuan       : User's Guide (UG) AdminKU, Bahasa Indonesia, untuk SEMUA pengguna yang sudah login: peta "Mulai di sini", peran, cara memakai grid dan form, satu bagian per modul (Untuk apa, Siapa yang bisa, Langkah, Yang perlu diperhatikan, Masalah umum), istilah, dan pertanyaan umum.
                Halaman dokumentasi murni: HTML biasa (kelas sstock-guide di resources/css/app.css, nama kelas lama, belum diganti), tidak lewat kamus Indonesian; istilah dan nama tombol ditulis sama persis dengan label di layar.
                Isi dipecah per bagian di resources/views/help/ug/*.blade.php (mulai, dasar, master, transaksi, laporan, ga, hr, purchase, keamanan, kas, istilah, tanya); berkas ini memuat daftar isi dan menyambungkannya.
                Bagian 9 GA (Perawatan Kendaraan dan Perawatan AC, kode modul GV* dan GA*) memakai nama perusahaan Jays (nama klien, bukan nama produk).
 Sumber hulu  : App\Support\Modules (daftar modul + peran minimal), User::canAccess (bagian modul hanya tampil bila pengguna boleh membukanya), REMARK di view/controller, HANDOVER.md.
 Output       : tab "Panduan Pengguna" di Bantuan & dukungan (menu profil).
 Dipakai oleh : route help.user-guide (grup auth). Technical Guide (English, Administrator) ada di help/technical-guide.blade.php; ubah keduanya bersama bila perilaku berubah.
                Tes HelpTest mencocokkan daftar modul (Modules::all) dengan anchor di sini: modul baru wajib punya bagian bantuan.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Panduan Pengguna', 'bodyClass' => 'sstock-bare'])
@section('body')
@php
    $user = auth()->user();
    $can = fn (string $code) => $user->canAccess($code);
    $sup = $user->isSupervisor();
    $anyMaster = collect(['ITM', 'SUP', 'DEP', 'RCP', 'UOM', 'CAT', 'LOC', 'CMP'])->contains($can);
    $anyTrx = collect(['OPN', 'RCV', 'ISS', 'ADJ', 'SO', 'ORD', 'PGK', 'SJL'])->contains($can);
    $anyRpt = collect(['STB', 'R-CARD', 'R-SUM', 'R-IN', 'R-OUT', 'R-EXP', 'R-ADJ'])->contains($can);
    $anyHr = $can('HRE') || $can('HRR') || $can('HRD');
    $anyPur = $can('PUP') || $can('PUK') || $can('PUO') || $can('PUV');
    $anyKas = $can('KSM') || $can('KSK') || $can('KSR') || $can('R-KFLOW') || $can('R-KIN') || $can('R-KOUT');
    $anyGa = collect(['GVM', 'GVD', 'GVS', 'GVO', 'GVK', 'GVT', 'GAM', 'GAS', 'GAV', 'GAK', 'GAA', 'GAR', 'GAE', 'GAP'])->contains($can);
    $anySec = collect(['USR', 'GRP', 'SMP', 'MOD'])->contains($can);
@endphp
@include('help.tabs', ['active' => 'ug'])
<div class="sstock-guide" id="ug-root">
    <script>document.getElementById('ug-root').dataset.mode = location.hash.length > 1 ? 'detail' : 'deck';</script>
    <div class="ug-mode" role="group" aria-label="Tampilan panduan">
        <button type="button" data-mode="deck">Presentasi</button>
        <button type="button" data-mode="detail">Detail</button>
    </div>
    @include('help.ug.deck')
    <nav class="g-toc" aria-label="Daftar isi">
        <strong>Panduan Pengguna</strong>
        <a href="#mulai">1. Mulai di sini</a>
        <a class="g-toc-sub" href="#hari-pertama">Urutan hari pertama</a>
        <a class="g-toc-sub" href="#saldo-awal-beda">Saldo Awal dan Penerimaan pertama</a>
        <a href="#peran">2. Peran Anda</a>
        <a href="#masuk">3. Masuk, Beranda, dan Menu</a>
        <a class="g-toc-sub" href="#keluar-otomatis">Keluar otomatis</a>
        <a class="g-toc-sub" href="#beranda">Beranda</a>
        <a class="g-toc-sub" href="#catat-pemesanan">Catat Pemesanan</a>
        <a class="g-toc-sub" href="#menu">Menu dan tab</a>
        <a href="#grid">4. Cara memakai Grid</a>
        <a href="#form">5. Cara memakai Form</a>
        <a class="g-toc-sub" href="#tombol-plus">Tombol plus (Tambah baru)</a>
        <a class="g-toc-sub" href="#siklus">Siklus dokumen</a>
        @if ($anyMaster)
            <a href="#master">6. Data Master</a>
            @if ($can('ITM'))<a class="g-toc-sub" href="#m-itm">Barang</a>@endif
            @if ($can('SUP'))<a class="g-toc-sub" href="#m-sup">Pemasok</a>@endif
            @if ($can('DEP'))<a class="g-toc-sub" href="#m-dep">Departemen</a>@endif
            @if ($can('RCP'))<a class="g-toc-sub" href="#m-rcp">Penerima</a>@endif
            @if ($can('UOM'))<a class="g-toc-sub" href="#m-uom">Satuan</a>@endif
            @if ($can('CAT'))<a class="g-toc-sub" href="#m-cat">Kategori</a>@endif
            @if ($can('LOC'))<a class="g-toc-sub" href="#m-loc">Lokasi Penyimpanan</a>@endif
            @if ($can('CMP'))<a class="g-toc-sub" href="#m-cmp">Perusahaan</a>@endif
        @endif
        @if ($anyTrx)
            <a href="#transaksi">7. Transaksi</a>
            @if ($can('OPN'))<a class="g-toc-sub" href="#t-opn">Saldo Awal</a>@endif
            @if ($can('RCV'))<a class="g-toc-sub" href="#t-rcv">Penerimaan Barang</a>@endif
            @if ($can('ISS'))<a class="g-toc-sub" href="#t-iss">Pengeluaran Barang</a>@endif
            @if ($can('ADJ'))<a class="g-toc-sub" href="#t-adj">Penyesuaian Stok</a>@endif
            @if ($can('SO'))<a class="g-toc-sub" href="#t-so">Stok Opname</a>@endif
            @if ($can('ORD'))<a class="g-toc-sub" href="#t-ord">Riwayat Pemesanan</a>@endif
            @if ($can('PGK'))<a class="g-toc-sub" href="#t-pgk">Perencanaan Pengiriman</a>@endif
            @if ($can('SJL'))<a class="g-toc-sub" href="#t-sjl">Surat Jalan</a>@endif
            <a class="g-toc-sub" href="#t-batal">Membatalkan dokumen</a>
        @endif
        @if ($anyRpt)
            <a href="#laporan">8. Laporan</a>
            @if ($can('STB'))<a class="g-toc-sub" href="#r-stb">Beranda Stok</a>@endif
            @if ($can('R-CARD'))<a class="g-toc-sub" href="#r-card">Kartu Stok</a>@endif
            @if ($can('R-SUM'))<a class="g-toc-sub" href="#r-sum">Ringkasan Stok</a>@endif
            @if ($can('R-IN'))<a class="g-toc-sub" href="#r-in">Laporan Penerimaan</a>@endif
            @if ($can('R-OUT'))<a class="g-toc-sub" href="#r-out">Laporan Pengeluaran</a>@endif
            @if ($can('R-EXP'))<a class="g-toc-sub" href="#r-exp">Laporan Kedaluwarsa</a>@endif
            @if ($can('R-ADJ'))<a class="g-toc-sub" href="#r-adj">Laporan Opname &amp; Penyesuaian</a>@endif
        @endif
        @if ($anyGa)
            <a href="#ga">9. Personalia (GA)</a>
            <a class="g-toc-sub" href="#ga-hak">Siapa boleh apa di GA</a>
            <a class="g-toc-sub" href="#ga-status">Arti Aman, Segera, dan Terlambat</a>
            @if ($can('GVM'))<a class="g-toc-sub" href="#ga-gvm">Monitoring Kendaraan</a><a class="g-toc-sub" href="#ga-detail">Detail kendaraan</a>@endif
            @if ($can('GVD'))<a class="g-toc-sub" href="#ga-gvd">Perpanjang Dokumen Kendaraan</a>@endif
            @if ($can('GVS'))<a class="g-toc-sub" href="#ga-gvs">Catat Servis Kendaraan</a>@endif
            @if ($can('GVO'))<a class="g-toc-sub" href="#ga-gvo">Catat Odometer</a>@endif
            @if ($can('GVK'))<a class="g-toc-sub" href="#ga-gvk">Master Kendaraan</a>@endif
            @if ($can('GVT'))<a class="g-toc-sub" href="#ga-gvt">Jenis Kendaraan</a>@endif
            @if ($can('GAM'))<a class="g-toc-sub" href="#ga-gam">Perawatan AC</a>@endif
            @if ($can('GAS'))<a class="g-toc-sub" href="#ga-gas">Penjadwalan AC</a>@endif
            @if ($can('GAV'))<a class="g-toc-sub" href="#ga-gav">Kunjungan AC</a>@endif
            @if ($can('GAK'))<a class="g-toc-sub" href="#ga-gak">Master AC</a>@endif
            @if ($can('GAA'))<a class="g-toc-sub" href="#ga-gaa">Area dan Gedung AC</a>@endif
            @if ($can('GAR'))<a class="g-toc-sub" href="#ga-gar">Ruang AC</a>@endif
            @if ($can('GAE'))<a class="g-toc-sub" href="#ga-gae">Vendor AC</a>@endif
            @if ($can('GAP'))<a class="g-toc-sub" href="#ga-gap">Pengaturan GA</a>@endif
        @endif
        @if ($anyHr)
            <a href="#hr">9a. SDM (HR)</a>
            @if ($can('HRE'))<a class="g-toc-sub" href="#hr-hre">Karyawan</a>@endif
            @if ($can('HRR'))<a class="g-toc-sub" href="#hr-hrr">Rekrutmen</a>@endif
            @if ($can('HRD'))<a class="g-toc-sub" href="#hr-hrd">Kedisiplinan</a>@endif
        @endif
        @if ($anyPur)
            <a href="#purchase">9b. Pembelian</a>
            @if ($can('PUP'))<a class="g-toc-sub" href="#pur-pup">Permintaan Pembelian</a>@endif
            @if ($can('PUK'))<a class="g-toc-sub" href="#pur-puk">Keputusan Pesanan</a>@endif
            @if ($can('PUO'))<a class="g-toc-sub" href="#pur-puo">Pesanan Pembelian (PO)</a>@endif
            @if ($can('PUV'))<a class="g-toc-sub" href="#pur-puv">Penerimaan Pembelian (PV)</a>@endif
        @endif
        @if ($anyKas)
            <a href="#kas">9c. Kas</a>
            @if ($can('KSM'))<a class="g-toc-sub" href="#kas-ksm">Uang Masuk</a>@endif
            @if ($can('KSK'))<a class="g-toc-sub" href="#kas-ksk">Uang Keluar</a>@endif
            @if ($can('KSR'))<a class="g-toc-sub" href="#kas-ksr">Kas / Rekening</a>@endif
            @if ($can('R-KFLOW'))<a class="g-toc-sub" href="#kas-rflow">Laporan Arus Kas</a>@endif
            @if ($can('R-KIN'))<a class="g-toc-sub" href="#kas-rkin">Laporan Penerimaan</a>@endif
            @if ($can('R-KOUT'))<a class="g-toc-sub" href="#kas-rkout">Laporan Pengeluaran</a>@endif
        @endif
        @if ($anySec)
            <a href="#keamanan">10. Keamanan</a>
            @if ($can('USR'))<a class="g-toc-sub" href="#sec-usr">Pengguna</a>@endif
            @if ($can('GRP'))<a class="g-toc-sub" href="#sec-grp">Grup dan hak akses</a>@endif
            @if ($can('MOD'))<a class="g-toc-sub" href="#sec-mod">Daftar Modul</a>@endif
            @if ($can('SMP'))<a class="g-toc-sub" href="#sec-smp">Data Contoh</a>@endif
        @endif
        <a href="#istilah">11. Istilah</a>
        <a href="#tanya">12. Pertanyaan umum</a>
    </nav>

    <main class="g-body">
        <h1>Panduan Pengguna AdminKU</h1>
        <p class="g-lead">Tuntunan memakai AdminKU langkah demi langkah. Bagian yang hanya bisa dipakai peran tertentu ditandai <span class="g-role">hanya Supervisor</span>. Panduan ini hanya menampilkan modul yang boleh Anda buka (peran Anda: <b>{{ $user->roleLabel() }}</b>).</p>

        @include('help.ug.mulai')
        @include('help.ug.dasar')
        @include('help.ug.master')
        @include('help.ug.transaksi')
        @include('help.ug.laporan')
        @include('help.ug.ga')
        @include('help.ug.hr')
        @include('help.ug.purchase')
        @include('help.ug.kas')
        @include('help.ug.keamanan')
        @include('help.ug.istilah')
        @include('help.ug.tanya')
    </main>
</div>
<script>
(function () {
    var root = document.getElementById('ug-root'), deck = root.querySelector('.ug-deck');
    var slides = [].slice.call(deck.querySelectorAll('.ug-s')), dots = [].slice.call(deck.querySelectorAll('.ug-dots button'));
    var bar = deck.querySelector('.ug-bar i'), playBtn = deck.querySelector('[data-play]'), cur = 0, timer = null, DUR = 8000;
    var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
    function mode(m) { root.dataset.mode = m; if (m === 'deck') { if (!reduce) start(); } else stop(); }
    function show(n, dir) {
        n = (n + slides.length) % slides.length;
        slides.forEach(function (s, k) { s.classList.toggle('is-on', k === n); s.dataset.dir = dir || 1; delete s.dataset.manual; });
        dots.forEach(function (d, k) { d.classList.toggle('on', k === n); d.setAttribute('aria-selected', k === n); });
        cur = n;
        if (timer) start();
    }
    // Putar otomatis: slide biasa maju tiap DUR; Misi "dimainkan sendiri" (titik diklik otomatis) kecuali pengguna ikut bermain.
    function start() {
        stop(true); bar.style.animation = 'none'; bar.offsetWidth; bar.style.animation = ''; deck.classList.add('is-playing');
        playBtn.setAttribute('aria-pressed', 'true'); playBtn.textContent = 'Jeda'; tick(slides[cur]);
    }
    function tick(s) {
        if (!s.dataset.misi) { timer = setTimeout(function () { show(cur + 1, 1); }, DUR); return; }
        timer = setTimeout(function () {
            if (slides[cur] !== s) return;
            var h = s.querySelector('.ug-hot.cur');
            if (s.dataset.manual) { if (h) return tick(s); }
            else if (h) { s._auto = 1; h.click(); return tick(s); }
            timer = setTimeout(function () { show(cur + 1, 1); }, 2600);
        }, 2300);
    }
    function stop(keep) { clearTimeout(timer); timer = null; deck.classList.remove('is-playing'); if (!keep) { playBtn.setAttribute('aria-pressed', 'false'); playBtn.textContent = 'Putar otomatis'; } }
    document.addEventListener('visibilitychange', function () { if (document.hidden) stop(); });
    root.querySelectorAll('[data-mode]').forEach(function (b) { b.onclick = function () { mode(b.dataset.mode); }; });
    deck.addEventListener('click', function (e) {
        var t = e.target.closest('[data-go],[data-slide],[data-play],[data-detail]'); if (!t) return;
        if (t.dataset.go) show(cur + (t.dataset.go === 'next' ? 1 : -1), t.dataset.go === 'next' ? 1 : -1);
        else if (t.dataset.slide) show(+t.dataset.slide, +t.dataset.slide >= cur ? 1 : -1);
        else if (t.hasAttribute('data-play')) { timer ? stop() : start(); }
        else { mode('detail'); var id = t.getAttribute('href').slice(1); history.replaceState(null, '', '#' + id); setTimeout(function () { var el = document.getElementById(id); if (el) el.scrollIntoView(); }, 0); e.preventDefault(); }
    });
    document.addEventListener('keydown', function (e) {
        if (root.dataset.mode !== 'deck' || /INPUT|TEXTAREA|SELECT/.test(e.target.tagName)) return;
        if (e.key === 'ArrowRight' || e.key === 'PageDown') show(cur + 1, 1); else if (e.key === 'ArrowLeft' || e.key === 'PageUp') show(cur - 1, -1);
    });
    var x0 = null;
    deck.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
    deck.addEventListener('touchend', function (e) { if (x0 === null) return; var dx = e.changedTouches[0].clientX - x0; x0 = null; if (Math.abs(dx) > 70) show(cur + (dx < 0 ? 1 : -1), dx < 0 ? 1 : -1); });
    if (reduce) playBtn.hidden = true;

    // Misi interaktif: satu titik aktif sekali waktu; klik = zoom kamera ke titik berikutnya.
    deck.querySelectorAll('.ug-misi').forEach(function (m) {
        var hots = [].slice.call(m.querySelectorAll('.ug-hot')), cam = m.querySelector('.ug-cam'), step = 0;
        var prog = m.querySelector('.ug-prog span'), st = m.querySelector('.ug-st'), sd = m.querySelector('.ug-sd');
        function focus(h) { cam.style.transformOrigin = h ? h.dataset.x + '% ' + h.dataset.y + '%' : '50% 50%'; m.classList.toggle('zoomed', !!h); }
        function paint() {
            hots.forEach(function (h, k) { h.classList.toggle('cur', k === step); h.classList.toggle('ok', k < step); });
            prog.textContent = step; m.classList.toggle('done', step >= hots.length); focus(hots[step]);
        }
        hots.forEach(function (h, k) {
            h.onclick = function () {
                if (k !== step) return;
                if (!m._auto) m.dataset.manual = 1; m._auto = 0;
                st.textContent = h.dataset.t; sd.textContent = h.dataset.d; step++;
                m.querySelector('.ug-say').classList.remove('pop'); void m.offsetWidth; m.querySelector('.ug-say').classList.add('pop');
                paint();
            };
        });
        m.querySelector('.ug-redo').onclick = function () { delete m.dataset.manual; step = 0; st.textContent = 'Klik titik yang berdenyut'; sd.textContent = 'Jelajahi tangkapan layar ini. Setiap titik mengungkap satu fitur.'; paint(); };
        paint();
    });
    show(0, 1);
    if (!reduce && root.dataset.mode === 'deck') start();
})();
</script>
@endsection
