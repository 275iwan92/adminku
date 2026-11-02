{{-- REMARK
 Tujuan       : Panduan Pengguna bagian 1 "Mulai di sini" (peta menu satu halaman, urutan hari pertama, tiga aturan dasar) dan bagian 2 "Peran Anda" (apa yang bisa dikerjakan Data Entry, Supervisor, Administrator).
 Sumber hulu  : App\Support\Modules (menu dan peran minimal), User::canAccess. Baris peta hanya tampil bila pengguna boleh membuka modulnya ($can dari help/user-guide).
 Dipakai oleh : help/user-guide.blade.php.
 Status       : Aktif.
--}}
<h2 id="mulai">1. Mulai di sini</h2>
<p>Stoku membantu Anda mencatat barang di gudang. Anda mencatat barang masuk dan barang keluar. Stoku menghitung sisa stoknya sendiri.</p>
<p>Bagian ini untuk pengguna baru. Baca dulu bagian ini. Lalu buka bagian modul yang Anda perlukan.</p>

@include('help.diagram', ['name' => 'ug-alur-kerja', 'caption' => 'Alur kerja Stoku: dari Data Master sampai Laporan.'])
<h3>Peta menu</h3>
<p>Menu ada di panel kiri layar. Setiap menu yang Anda pilih terbuka sebagai tab.</p>
<table class="g-table">
    <thead><tr><th>Menu</th><th>Untuk apa</th><th>Kapan Anda memakainya</th></tr></thead>
    <tbody>
        @if ($can('ITM'))<tr><td><a href="#m-itm">Barang</a></td><td>Daftar semua barang dan stoknya</td><td>Ada barang baru, atau Anda ingin melihat stok</td></tr>@endif
        @if ($can('SUP'))<tr><td><a href="#m-sup">Pemasok</a></td><td>Daftar pemasok (penjual barang)</td><td>Ada pemasok baru</td></tr>@endif
        @if ($can('DEP'))<tr><td><a href="#m-dep">Departemen</a></td><td>Bagian atau unit penerima barang</td><td>Ada bagian baru</td></tr>@endif
        @if ($can('RCP'))<tr><td><a href="#m-rcp">Penerima</a></td><td>Orang yang menerima barang keluar</td><td>Ada penerima baru</td></tr>@endif
        @if ($can('UOM'))<tr><td><a href="#m-uom">Satuan</a></td><td>pcs, box, kg, dan hitungan antar satuan</td><td>Perlu satuan baru</td></tr>@endif
        @if ($can('CAT'))<tr><td><a href="#m-cat">Kategori</a></td><td>Kelompok barang dan awalan kode barang</td><td>Perlu kelompok baru</td></tr>@endif
        @if ($can('CMP'))<tr><td><a href="#m-cmp">Perusahaan</a></td><td>Nama, alamat, dan logo untuk kop semua cetakan</td><td>Sekali di awal, lalu bila datanya berubah</td></tr>@endif
        @if ($can('LOC'))<tr><td><a href="#m-loc">Lokasi Penyimpanan</a></td><td>Rak atau gudang tempat barang disimpan</td><td>Ada rak atau gudang baru</td></tr>@endif
        @if ($can('OPN'))<tr><td><a href="#t-opn">Saldo Awal</a></td><td>Memasukkan stok yang sudah ada saat mulai memakai Stoku</td><td>Sekali di awal, bila Anda memerlukannya</td></tr>@endif
        @if ($can('RCV'))<tr><td><a href="#t-rcv">Penerimaan Barang</a></td><td>Mencatat barang yang datang dari pemasok</td><td>Setiap barang datang</td></tr>@endif
        @if ($can('ISS'))<tr><td><a href="#t-iss">Pengeluaran Barang</a></td><td>Mencatat barang yang keluar ke penerima</td><td>Setiap barang keluar</td></tr>@endif
        @if ($can('ADJ'))<tr><td><a href="#t-adj">Penyesuaian Stok</a></td><td>Mencocokkan stok sistem dengan hitungan fisik, satu per satu</td><td>Ada temuan di luar jadwal, misalnya salah catat</td></tr>@endif
        @if ($can('SO'))<tr><td><a href="#t-so">Stok Opname</a></td><td>Menghitung fisik banyak barang sekaligus</td><td>Rutin, mingguan atau bulanan</td></tr>@endif
        @if ($can('ORD'))<tr><td><a href="#t-ord">Riwayat Pemesanan</a></td><td>Melihat semua catatan pesanan ke pemasok, termasuk yang tertutup</td><td>Memeriksa pesanan yang belum datang, atau menutup pesanan</td></tr>@endif
        @if ($can('R-CARD') || $can('R-SUM') || $can('R-IN') || $can('R-OUT') || $can('R-EXP') || $can('R-ADJ'))<tr><td><a href="#laporan">Laporan</a></td><td>Membaca stok, riwayat, penerimaan, pengeluaran, dan barang hampir kedaluwarsa</td><td>Kapan saja Anda ingin tahu keadaan stok</td></tr>@endif
        <tr><td><a href="#beranda">Beranda</a></td><td>Peringatan stok menipis, barang hampir kedaluwarsa, dan jadwal Stok Opname</td><td>Setiap kali Anda masuk</td></tr>
    </tbody>
</table>

<h3 id="hari-pertama">Urutan hari pertama</h3>
<p>Ikuti urutan ini bila Stoku masih baru di gudang Anda.</p>
@include('help.diagram', ['name' => 'ug-hari-pertama', 'caption' => 'Urutan hari pertama: masuk, Data Master, stok awal, Pengeluaran, Laporan, Stok Opname.'])
<ol class="g-steps">
    <li><b>Masuk.</b> Buka alamat Stoku di browser. Isi username atau e-mail dan password. Data Entry langsung melihat tab Penerimaan Barang. <a href="#masuk">Lihat Masuk</a>.</li>
    <li><b>Siapkan Data Master.</b> Data Master adalah data acuan yang dipilih di dokumen. Isi dengan urutan ini: Kategori, Lokasi Penyimpanan, Satuan, Pemasok, Departemen, Penerima, lalu Barang. Data Entry bisa menambah Pemasok, Departemen, Penerima, dan Barang. Kategori, Lokasi Penyimpanan, dan Satuan diisi Supervisor. Supervisor juga mengisi data Perusahaan agar kop cetakan benar.@if ($anyMaster) Lihat <a href="#master">Data Master</a>.@endif</li>
    <li><b>Isi stok awal.</b> Ada dua cara untuk satu barang. Stok yang sudah ada di rak dan belum pernah dicatat: Supervisor membuat <b>Saldo Awal</b>. Barang yang baru datang dari pemasok: buat <b>Penerimaan Barang</b>. Saldo Awal tidak wajib. Penerimaan pertama <b>tidak</b> otomatis menjadi Saldo Awal. Lihat penjelasannya di bawah.</li>
    <li><b>Catat Pengeluaran Barang</b> setiap barang keluar ke penerima. Stok tidak boleh menjadi minus.</li>
    <li><b>Periksa Laporan.</b> Ringkasan Stok menunjukkan sisa semua barang. Kartu Stok menunjukkan riwayat satu barang. Laporan kosong? Mungkin dokumennya masih Draf.</li>
    <li><b>Jadwalkan Stok Opname.</b> Stok Opname adalah hitung fisik rutin untuk mencocokkan stok di Stoku dengan stok di rak.</li>
</ol>

<h4 id="saldo-awal-beda">Saldo Awal dan Penerimaan pertama</h4>
@include('help.diagram', ['name' => 'ug-saldo-awal-vs-penerimaan', 'caption' => 'Saldo Awal untuk stok yang sudah ada. Penerimaan Barang untuk barang yang datang.'])
<ul class="g-list">
    <li>Saldo Awal hanya untuk barang yang <b>belum punya pergerakan stok</b>. Pergerakan stok adalah masuk, keluar, atau penyesuaian yang sudah tercatat.</li>
    <li>Bila barang sudah bergerak, Saldo Awal ditolak. Pakai Penyesuaian Stok.</li>
    <li>Barang yang stoknya nol dan baru datang cukup dicatat lewat Penerimaan Barang. Tidak perlu Saldo Awal.</li>
</ul>

<div class="g-tip"><b>Tiga aturan dasar.</b>
    <ol class="g-steps">
        <li>Tombol <b>Simpan</b> membuat dokumen berstatus <b>Draf</b>. Draf belum mengubah stok.</li>
        <li>Tekan <b>Konfirmasi</b> supaya stok berubah. Dokumen yang sudah Terkonfirmasi terkunci.</li>
        <li>Laporan stok hanya menghitung dokumen yang sudah Terkonfirmasi. Draf tidak ikut dihitung.</li>
    </ol>
</div>

<h2 id="peran">2. Peran Anda</h2>
<p>Setiap orang punya satu peran. Peran menentukan menu yang tampil dan tombol yang bisa ditekan. Menu yang tidak tampil berarti peran atau grup Anda belum diberi akses.</p>
<table class="g-table">
    <thead><tr><th>Peran</th><th>Bisa mengerjakan</th><th>Tidak bisa</th></tr></thead>
    <tbody>
        <tr>
            <td>Data Entry</td>
            <td>
                <ul class="g-list">
                    <li>Membuat Penerimaan Barang dan Pengeluaran Barang, lalu Konfirmasi.</li>
                    <li>Mengajukan pembatalan dokumen.</li>
                    <li>Mengisi hasil hitung Stok Opname dan mencetak Lembar Hitung.</li>
                    <li>Melihat Barang, menambah barang baru, dan menambah Satuan masuk.</li>
                    <li>Mengelola Pemasok, Departemen, dan Penerima.</li>
                    <li>Membaca Kartu Stok, Ringkasan Stok, Laporan Penerimaan, Laporan Pengeluaran, dan Laporan Kedaluwarsa.</li>
                </ul>
            </td>
            <td>Mengubah barang yang sudah ada, Satuan, Kategori, Lokasi Penyimpanan, Saldo Awal, Penyesuaian Stok. Memulai, meninjau, dan menyetujui Stok Opname. Menyetujui pembatalan. Melihat nilai stok.</td>
        </tr>
        <tr>
            <td>Supervisor</td>
            <td>
                <ul class="g-list">
                    <li>Semua yang bisa dikerjakan Data Entry.</li>
                    <li>Mengubah Barang, Satuan, Kategori, dan Lokasi Penyimpanan.</li>
                    <li>Mengisi data Perusahaan (kop cetakan).</li>
                    <li>Membuat Saldo Awal dan Penyesuaian Stok.</li>
                    <li>Memulai, meninjau, dan menyetujui Stok Opname.</li>
                    <li>Menekan <b>Setujui</b> atau <b>Tolak</b> untuk Penyesuaian Stok dan pengajuan pembatalan.</li>
                    <li>Melihat harga, nilai stok, dan Laporan Opname &amp; Penyesuaian.</li>
                </ul>
            </td>
            <td>Mengurus pengguna, grup, dan data contoh.</td>
        </tr>
        <tr>
            <td>Administrator</td>
            <td>Semua yang bisa dikerjakan Supervisor, ditambah menu <b>Keamanan</b> (Pengguna, Grup, Data Contoh, Daftar Modul) dan Technical Guide.</td>
            <td>Menonaktifkan atau mengubah peran akunnya sendiri.</td>
        </tr>
    </tbody>
</table>
<ul class="g-list">
    <li><b>Harga dan nilai.</b> Harga satuan, biaya rata-rata, dan nilai stok hanya tampil untuk Supervisor dan Administrator. Data Entry tetap mengetik <b>Harga Beli / Satuan</b> saat Penerimaan Barang.</li>
    <li><b>Grup dan hak akses.</b> Administrator bisa mengatur menu per orang lewat <b>Grup</b>. Hak akses ada tiga: Tidak ada, <b>Hanya baca (RO)</b>, dan <b>Baca &amp; tulis (RW)</b>. RO boleh membuka layar tetapi tidak boleh menyimpan.</li>
    <li>Menu yang Anda perlukan tidak tampil? Hubungi Administrator.</li>
</ul>
