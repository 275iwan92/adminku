{{-- REMARK
 Tujuan       : User's Guide (UG) AdminKU, Bahasa Indonesia, untuk SEMUA pengguna yang sudah login: peta "Mulai di sini", peran, cara memakai grid dan form, satu bagian per modul (Untuk apa, Siapa yang bisa, Langkah, Yang perlu diperhatikan, Masalah umum), istilah, dan pertanyaan umum.
                Halaman dokumentasi murni: HTML biasa (kelas sstock-guide di resources/css/app.css, nama kelas lama, belum diganti), tidak lewat kamus Indonesian; istilah dan nama tombol ditulis sama persis dengan label di layar.
                Isi dipecah per bagian di resources/views/help/ug/*.blade.php (mulai, dasar, master, transaksi, laporan, ga, hr, purchase, keamanan, kas, istilah, tanya); berkas ini memuat daftar isi dan menyambungkannya.
                Bagian 9 GA (Perawatan Kendaraan dan Perawatan AC, kode modul GV* dan GA*) memakai nama perusahaan Jays (nama klien, bukan nama produk).
 Sumber hulu  : App\Support\Modules (daftar modul + peran minimal), User::canAccess (bagian modul hanya tampil bila pengguna boleh membukanya), REMARK di view/controller, HANDOVER.md.
 Output       : tab "Panduan Pengguna" di Bantuan & dukungan (menu profil), dalam empat mode: Presentasi (slide bertingkat), Interaktif (misi klik titik, Driver.js), Blog (kartu + artikel bergambar), dan Detail (dokumen lengkap di bawah). Tiga mode pertama bersumber dari resources/help/guide.php lewat App\Support\GuideCatalog (views help/ug/modes/*, skrip resources/js/guide.js, gaya resources/css/ug.css); Detail tetap sumber isi lengkap dan tujuan semua tautan "Panduan lengkap".
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
<div class="sstock-guide" id="ug-root" data-uid="{{ $user->id }}">
    <script>(function () { if (window.top === window && location.protocol !== 'file:' && !/[?&]plain\b/.test(location.search)) { location.replace('{{ route('home') }}?bantuan=' + encodeURIComponent(location.hash.slice(1) || '1')); return; } var h = location.hash, r = document.getElementById('ug-root'); var m = /^#gb/.test(h) ? 'blog' : /^#gt/.test(h) ? 'tour' : /^#gd/.test(h) ? 'deck' : h.length > 1 ? 'detail' : (localStorage.getItem('adminku.ug.mode') || 'deck'); r.dataset.mode = ['deck', 'tour', 'blog', 'detail'].indexOf(m) < 0 ? 'deck' : m; if (!h && !/[?&]plain\b/.test(location.search)) document.documentElement.classList.add('ugf-on'); })();</script>
    <div class="ug-mode" role="group" aria-label="Tampilan panduan">
        <button type="button" data-mode="deck"><span class="ug-mi" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M10 8.5v5l4-2.5z"/><path d="M8 21h8M12 17v4"/></svg></span><b>Presentasi</b><small>Ringkasan bergerak seperti video</small></button>
        <button type="button" data-mode="tour"><span class="ug-mi" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 3l1.2 3M4.2 7.2l2.4 1.8M3 12.5l3-.8"/><path d="M12 11l7.5 3.3-3.3 1.4-1.4 3.3z"/></svg></span><b>Interaktif</b><small>Klik titik, belajar sambil coba</small></button>
        <button type="button" data-mode="blog"><span class="ug-mi" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="8" height="10" rx="2"/><rect x="13" y="3" width="8" height="6" rx="2"/><rect x="13" y="11" width="8" height="10" rx="2"/><rect x="3" y="15" width="8" height="6" rx="2"/></svg></span><b>Blog</b><small>Artikel bergambar dan alur</small></button>
        <button type="button" data-mode="detail"><span class="ug-mi" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 4h10a3 3 0 013 3v13H8a3 3 0 01-3-3z"/><path d="M8 8h6M8 12h6M8 16h3"/></svg></span><b>Detail</b><small>Panduan lengkap per menu</small></button>
    </div>
    @php $catalog = \App\Support\GuideCatalog::forUser($user); @endphp
    <button type="button" class="ug-fsopen" data-ugf-open>Buka bantuan layar penuh</button>
    @include('help.ug.modes.fullscreen')
    @include('help.ug.modes.deck')
    @include('help.ug.modes.tour')
    @include('help.ug.modes.blog')
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
@vite('resources/js/guide.js')
@endsection
