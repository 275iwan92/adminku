{{-- REMARK
 Tujuan       : User's Guide (UG) Stoku, Bahasa Indonesia, untuk SEMUA pengguna yang sudah login: peta "Mulai di sini", peran, cara memakai grid dan form, satu bagian per modul (Untuk apa, Siapa yang bisa, Langkah, Yang perlu diperhatikan, Masalah umum), istilah, dan pertanyaan umum.
                Halaman dokumentasi murni: HTML biasa (kelas sstock-guide di resources/css/app.css), tidak lewat kamus Indonesian; istilah dan nama tombol ditulis sama persis dengan label di layar.
                Isi dipecah per bagian di resources/views/help/ug/*.blade.php (mulai, dasar, master, transaksi, laporan, ga, keamanan, istilah, tanya); berkas ini memuat daftar isi dan menyambungkannya.
                Bagian 9 GA (Perawatan Kendaraan dan Perawatan AC, kode modul GV* dan GA*) memakai nama aplikasi AdminKU dan perusahaan Jays; bagian lama belum diganti mereknya.
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
    $anyTrx = collect(['OPN', 'RCV', 'ISS', 'ADJ', 'SO', 'ORD'])->contains($can);
    $anyRpt = collect(['STB', 'R-CARD', 'R-SUM', 'R-IN', 'R-OUT', 'R-EXP', 'R-ADJ'])->contains($can);
    $anyGa = collect(['GVM', 'GVD', 'GVS', 'GVO', 'GVK', 'GVT', 'GAS', 'GAV', 'GAD', 'GAK', 'GAA', 'GAR', 'GAE', 'GAP'])->contains($can);
    $anySec = collect(['USR', 'GRP', 'SMP', 'MOD'])->contains($can);
@endphp
@include('help.tabs', ['active' => 'ug'])
<div class="sstock-guide">
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
            @if ($can('SUP'))<a class="g-toc-sub" href="#m-sup">Suppliers</a>@endif
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
            <a href="#ga">9. GA</a>
            <a class="g-toc-sub" href="#ga-hak">Siapa boleh apa di GA</a>
            <a class="g-toc-sub" href="#ga-status">Arti Aman, Segera, dan Terlambat</a>
            @if ($can('GVM'))<a class="g-toc-sub" href="#ga-gvm">Monitoring Kendaraan</a><a class="g-toc-sub" href="#ga-detail">Detail kendaraan</a>@endif
            @if ($can('GVD'))<a class="g-toc-sub" href="#ga-gvd">Perpanjang Dokumen Kendaraan</a>@endif
            @if ($can('GVS'))<a class="g-toc-sub" href="#ga-gvs">Catat Servis Kendaraan</a>@endif
            @if ($can('GVO'))<a class="g-toc-sub" href="#ga-gvo">Catat Odometer</a>@endif
            @if ($can('GVK'))<a class="g-toc-sub" href="#ga-gvk">Master Kendaraan</a>@endif
            @if ($can('GVT'))<a class="g-toc-sub" href="#ga-gvt">Jenis Kendaraan</a>@endif
            @if ($can('GAS'))<a class="g-toc-sub" href="#ga-gas">Penjadwalan AC</a>@endif
            @if ($can('GAV'))<a class="g-toc-sub" href="#ga-gav">Kunjungan AC</a>@endif
            @if ($can('GAD'))<a class="g-toc-sub" href="#ga-gad">Denah AC</a>@endif
            @if ($can('GAK'))<a class="g-toc-sub" href="#ga-gak">Master AC</a>@endif
            @if ($can('GAA'))<a class="g-toc-sub" href="#ga-gaa">Area dan Gedung AC</a>@endif
            @if ($can('GAR'))<a class="g-toc-sub" href="#ga-gar">Ruang AC</a>@endif
            @if ($can('GAE'))<a class="g-toc-sub" href="#ga-gae">Vendor AC</a>@endif
            @if ($can('GAP'))<a class="g-toc-sub" href="#ga-gap">Pengaturan GA</a>@endif
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
        <h1>Panduan Pengguna Stoku</h1>
        <p class="g-lead">Tuntunan memakai Stoku langkah demi langkah. Bagian yang hanya bisa dipakai peran tertentu ditandai <span class="g-role">hanya Supervisor</span>. Panduan ini hanya menampilkan modul yang boleh Anda buka (peran Anda: <b>{{ $user->roleLabel() }}</b>).</p>

        @include('help.ug.mulai')
        @include('help.ug.dasar')
        @include('help.ug.master')
        @include('help.ug.transaksi')
        @include('help.ug.laporan')
        @include('help.ug.ga')
        @include('help.ug.keamanan')
        @include('help.ug.istilah')
        @include('help.ug.tanya')
    </main>
</div>
@endsection
