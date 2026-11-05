{{-- REMARK
 Tujuan       : Panduan Pengguna bagian 8 Laporan: cara pakai bersama, lalu satu bagian per laporan (Kartu Stok dengan cara membaca Saldo Awal, Ringkasan Stok, Laporan Penerimaan, Laporan Pengeluaran, Laporan Kedaluwarsa, Laporan Opname & Penyesuaian).
 Sumber hulu  : ReportController (periode bawaan awal bulan s.d. hari ini; dokumen yang dihitung), reports/*.blade.php (saringan dan kolom), x-report-filter.
 Dipakai oleh : help/user-guide.blade.php (hanya bila pengguna punya salah satu modul laporan).
 Status       : Aktif.
--}}
@if ($anyRpt)
<h2 id="laporan">8. Laporan</h2>
<p>Laporan hanya membaca data. Tidak ada yang berubah saat Anda membukanya. Cara pakainya sama untuk semua laporan:</p>
<ol class="g-steps">
    <li>Buka laporan dari menu <b>Laporan</b>.</li>
    <li>Atur <b>Dari tanggal</b> dan <b>Sampai tanggal</b>. Bawaannya awal bulan ini sampai hari ini. Atur saringan lain bila ada.</li>
    <li>Tekan <b>Tampilkan</b>.</li>
    <li>Tekan <b>Cetak</b> untuk mencetak, atau simpan sebagai PDF dari dialog cetak browser. <b>Hemat Tinta</b> mencetak tanpa warna latar.</li>
</ol>
<ul class="g-list">
    <li><b>Dokumen yang dihitung.</b> Laporan stok hanya memakai dokumen yang sudah <b>Terkonfirmasi</b> (Penyesuaian: Disetujui). Dokumen Draf tidak ikut. Dokumen Dibatalkan tidak dihitung di Laporan Penerimaan dan Laporan Pengeluaran. Di Kartu Stok pembatalan tampil sebagai baris pembalik.</li>
    <li><b>Laporan kosong?</b> Periksa periodenya. Periksa juga apakah dokumennya masih Draf.</li>
    <li>Harga, biaya, dan nilai hanya tampil untuk Supervisor.</li>
</ul>
<p class="g-note">Kop laporan (nama, logo, alamat) mengikuti data di menu <b>Perusahaan</b>. Semua cetakan memakai kop yang sama.</p>
<div class="g-tip"><b>Cetak dokumen:</b> tombol <b>Cetak</b> juga ada di form semua dokumen stok (Draf, Terkonfirmasi, Dibatalkan, dan seterusnya). Hasil cetak selalu memuat <b>mode</b> dokumen (DRAFT, CONFIRMED, CANCELLED). Penyesuaian juga memuat WAITING APPROVAL, APPROVED, atau REJECTED. Dokumen yang belum atau tidak sah diberi tanda air.</div>

@if ($can('STB'))
<h3 id="r-stb">Beranda Stok</h3>
<h4>Untuk apa</h4>
<p>Layar pembuka menu <b>Stok</b>: pengingat Stok Opname, angka ringkasan stok (barang aktif, di titik pesan ulang, hampir kedaluwarsa, penerimaan dan pengeluaran hari ini, nilai stok, dokumen menunggu persetujuan), tabel barang di titik pesan ulang, dan tabel barang hampir kedaluwarsa. Beranda aplikasi sendiri hanya berisi ringkasan lintas modul.</p>
<h4>Siapa yang bisa</h4>
<p>Data Entry dan Supervisor. Nilai stok dan dokumen menunggu persetujuan hanya untuk Supervisor.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>Stok</b> &gt; <b>Beranda Stok</b>, atau tekan kartu <b>Stok</b> di Beranda.</li>
    <li>Tekan kotak angka atau tombol di tabel untuk membuka layar terkait, misalnya <b>Buka Penerimaan</b> atau <b>Catat Pemesanan</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Semua isinya sama dengan yang dulu tampil di Beranda. Cara <b>Catat Pemesanan</b> tidak berubah.</li>
    <li>Di HP, kartu <b>Stok</b> di Beranda membuka ubin ringkasan stok dan pengingat opname.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Menu Beranda Stok tidak ada.</b> Grup Anda belum diberi modul ini. Minta Administrator mencentangnya di menu <b>Grup</b>.</li>
</ul>
@endif

@if ($can('R-CARD'))
<h3 id="r-card">Kartu Stok</h3>
<h4>Untuk apa</h4>
<p>Riwayat pergerakan stok <b>satu barang</b>: dari mana stok masuk, ke mana keluar, dan saldonya. Barang berkedaluwarsa punya satu kartu per batch. Pilihan <b>Barang</b> menampilkan kode batch, nama, dan tanggal kedaluwarsanya.</p>
<h4>Siapa yang bisa</h4>
<p>Data Entry dan Supervisor. Kolom biaya dan nilai saldo hanya untuk Supervisor.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>Kartu Stok</b>.</li>
    <li>Pilih <b>Barang</b>. Isian ini wajib.</li>
    <li>Atur <b>Dari tanggal</b> dan <b>Sampai tanggal</b>, lalu tekan <b>Tampilkan</b>.</li>
    <li>Baca kotak ringkasan di atas: <b>Saldo Awal</b>, <b>Total Masuk</b>, <b>Total Keluar</b>, <b>Penyesuaian</b>, dan <b>Saldo Akhir</b>. Lalu baca baris pergerakan di bawahnya.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Kolom <b>Tipe</b>: <b>AWAL</b> (Saldo Awal), <b>MASUK</b> (Penerimaan), <b>KELUAR</b> (Pengeluaran), dan <b>PENY</b> (Penyesuaian, termasuk hasil Stok Opname).</li>
    <li>Pembatalan tampil sebagai baris pembalik dengan tanda <b>(BATAL)</b> di tipenya. Baris asalnya tidak dihapus.</li>
    <li>Baris dikelompokkan per bulan. Kolom <b>Saldo</b> adalah saldo berjalan setelah tiap baris.</li>
</ul>
<h4>Membaca Saldo Awal di Kartu Stok</h4>
<p>Kotak <b>Saldo Awal</b> di atas kartu adalah saldo sebelum <b>Dari tanggal</b>. Karena itu tampilan Saldo Awal bergantung pada Dari tanggal.</p>
@include('help.diagram', ['name' => 'ug-kartu-stok-saldo-awal', 'caption' => 'Saldo Awal di Kartu Stok: baris AWAL atau kotak Saldo Awal, tergantung Dari tanggal.'])
<ul class="g-list">
    <li>Dari tanggal <b>sama dengan atau lebih awal</b> dari tanggal dokumen Saldo Awal: baris <b>AWAL</b> tampil di daftar, dan kotak Saldo Awal di atas bernilai 0.</li>
    <li>Dari tanggal <b>lebih lambat</b> dari tanggal dokumen Saldo Awal: baris AWAL tidak tampil. Jumlahnya sudah masuk ke kotak Saldo Awal di atas.</li>
    <li>Ingin melihat seluruh riwayat dari awal? Atur Dari tanggal ke tanggal sebelum AdminKU dipakai.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Kartu kosong.</b> Barang belum punya pergerakan dalam periode itu. Perluas periodenya.</li>
    <li><b>Saldo Awal di atas 0 padahal tidak ada baris AWAL.</b> Dari tanggal Anda lebih lambat dari tanggal Saldo Awal. Itu normal.</li>
    <li><b>Barang tidak ketemu di pilihan.</b> Cari dengan kode batch. Barang berkedaluwarsa muncul per batch.</li>
</ul>
@endif

@if ($can('R-SUM'))
<h3 id="r-sum">Ringkasan Stok</h3>
<h4>Untuk apa</h4>
<p>Satu tabel stok semua barang untuk satu periode: saldo awal, masuk, keluar, penyesuaian, dan saldo akhir. Satu baris adalah satu batch.</p>
<h4>Siapa yang bisa</h4>
<p>Data Entry dan Supervisor. Valuasi dan total nilai stok hanya untuk Supervisor.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>Ringkasan Stok</b>.</li>
    <li>Atur periode. Pilih saringan: <b>Kategori</b>, <b>Hanya barang di / bawah titik pesan ulang</b>, atau <b>Termasuk barang nonaktif</b>.</li>
    <li>Tekan <b>Tampilkan</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Kolom <b>Masuk</b> menjumlahkan Penerimaan dan Saldo Awal pada periode itu.</li>
    <li>Kolom <b>Status</b> (<b>AMAN</b> atau <b>PESAN ULANG</b>) dihitung per barang dari saldo akhir semua batch yang belum kedaluwarsa pada akhir periode.</li>
    <li>Kolom <b>Pemasok Pesanan</b>, <b>Jumlah Dipesan</b>, dan <b>Info Pemesanan</b> terisi untuk barang berstatus PESAN ULANG yang punya pesanan terbuka. Kolom <b>Lead Time Otomatis (d)</b> ada di samping Lead Time manual.</li>
    <li>Supervisor juga melihat <b>Biaya Rata-rata</b>, <b>Nilai Stok</b>, dan total nilai stok.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Barang tidak tampil.</b> Barangnya nonaktif. Centang <b>Termasuk barang nonaktif</b>.</li>
    <li><b>Angka tidak cocok dengan hitungan di rak.</b> Lakukan @if ($can('SO'))<a href="#t-so">Stok Opname</a>@else Stok Opname @endif atau Penyesuaian Stok.</li>
</ul>
@endif

@if ($can('R-IN'))
<h3 id="r-in">Laporan Penerimaan</h3>
<h4>Untuk apa</h4>
<p>Barang yang masuk dalam satu periode: tanggal, jam, pemasok, nomor referensi, barang, jumlah, dan siapa yang menginput.</p>
<h4>Siapa yang bisa</h4>
<p>Data Entry dan Supervisor. Harga beli dan jumlah nilai hanya untuk Supervisor.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>Laporan Penerimaan</b>.</li>
    <li>Atur periode. Pilih <b>Pemasok</b> bila ingin satu pemasok saja.</li>
    <li>Tekan <b>Tampilkan</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Hanya dokumen yang berlaku. Dokumen Draf dan Dibatalkan tidak dihitung. Dokumen yang sedang berstatus Pembatalan Menunggu Persetujuan masih dihitung.</li>
    <li>Kolom <b>Jml (satuan stok)</b> selalu dalam satuan stok. Kolom <b>Diterima sebagai</b> menunjukkan yang diketik, mis. 5 dus.</li>
    <li>Tanggal dan jam adalah saat Konfirmasi.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Penerimaan saya tidak muncul.</b> Dokumennya masih Draf, atau periodenya tidak mencakup saat Konfirmasi.</li>
</ul>
@endif

@if ($can('R-OUT'))
<h3 id="r-out">Laporan Pengeluaran</h3>
<h4>Untuk apa</h4>
<p>Barang yang keluar dalam satu periode: tanggal, jam, penerima, departemen, keperluan, barang, jumlah, dan siapa yang menginput.</p>
<h4>Siapa yang bisa</h4>
<p>Data Entry dan Supervisor. Biaya hanya untuk Supervisor.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>Laporan Pengeluaran</b>.</li>
    <li>Atur periode. Isi <b>Penerima / Departemen</b> bila ingin menyaring. Cukup ketik sebagian nama.</li>
    <li>Tekan <b>Tampilkan</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Hanya dokumen yang berlaku. Draf dan yang dibatalkan tidak dihitung.</li>
    <li>Pengeluaran yang melebihi stok satu batch tampil sebagai beberapa baris, satu per batch yang diambil.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Pengeluaran saya tidak muncul.</b> Dokumennya masih Draf. Tekan Konfirmasi.</li>
</ul>
@endif

@if ($can('R-EXP'))
<h3 id="r-exp">Laporan Kedaluwarsa</h3>
<h4>Untuk apa</h4>
<p>Batch yang masih ada stoknya dan sudah atau hampir kedaluwarsa, mulai yang paling dulu.</p>
<h4>Siapa yang bisa</h4>
<p>Data Entry dan Supervisor. Nilai stok hanya untuk Supervisor.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>Laporan Kedaluwarsa</b>.</li>
    <li>Isi <b>Kedaluwarsa dalam (hari)</b> (bawaan 30) dan pilih <b>Kategori</b> bila perlu.</li>
    <li>Tekan <b>Tampilkan</b>. Kolom <b>Sisa Hari</b> menunjukkan sisa waktunya. Angka minus berarti sudah lewat.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Laporan ini tidak memakai periode, tetapi keadaan hari ini.</li>
    <li>Barang tanpa stok tidak ditampilkan, karena tidak ada yang perlu dibuang.</li>
    <li>Barang kedaluwarsa yang masih ada stoknya dipisahkan dari rak. Supervisor mengeluarkannya dari stok lewat Penyesuaian Stok.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Barang yang saya cari tidak ada.</b> Stoknya 0, atau tanggal kedaluwarsanya masih lebih jauh dari batas hari.</li>
</ul>
@endif

@if ($can('R-ADJ'))
<h3 id="r-adj">Laporan Opname &amp; Penyesuaian <span class="g-role">hanya Supervisor</span></h3>
<h4>Untuk apa</h4>
<p>Daftar baris Penyesuaian Stok dalam satu periode: stok sistem dibanding hitung fisik, selisih, status persetujuan, dan dampak nilainya. Penyesuaian otomatis dari Stok Opname dan dari pembatalan ikut tampil.</p>
<h4>Siapa yang bisa</h4>
<p>Supervisor.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>Laporan Opname &amp; Penyesuaian</b>.</li>
    <li>Atur periode. Pilih <b>Status</b>: Semua, Draf, Menunggu Persetujuan, Disetujui, atau Ditolak.</li>
    <li>Tekan <b>Tampilkan</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Laporan ini berbeda dari yang lain. Ia menampilkan Penyesuaian dengan <b>semua status</b>, termasuk Draf. Lihat kolom <b>Status</b>. Hanya yang <b>Disetujui</b> yang sudah mengubah stok.</li>
    <li>Kolom <b>Dampak Nilai</b> adalah selisih dikali biaya. Ringkasannya menjumlahkan dampak nilai bersih.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Stok belum berubah padahal Penyesuaian ada di laporan.</b> Statusnya masih Draf atau Menunggu Persetujuan.</li>
</ul>
@endif
@endif
