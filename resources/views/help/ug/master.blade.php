{{-- REMARK
 Tujuan       : Panduan Pengguna bagian 6 Data Master: Barang (produk, batch, FEFO, kode otomatis, titik pesan ulang, lead time, Satuan masuk), Pemasok (termasuk Kontan), Departemen, Penerima, Satuan, Kategori, Lokasi Penyimpanan.
                Pola tetap tiap modul: Untuk apa, Siapa yang bisa, Langkah, Yang perlu diperhatikan, Masalah umum.
 Sumber hulu  : ProductController / products/form, ProductUnits, Product / Item, LeadTimes, SupplierController / Supplier, DepartmentController, RecipientController, UnitController, CategoryController, LocationController, Modules (peran minimal).
 Dipakai oleh : help/user-guide.blade.php (hanya bila pengguna punya salah satu modul master; tiap bagian bergantung $can).
 Status       : Aktif.
--}}
@if ($anyMaster)
<h2 id="master">6. Data Master</h2>
<p>Data Master adalah data acuan yang Anda pilih di dokumen. Isilah sekali, lalu pakai berulang. Semuanya berupa grid dan form master (lihat bagian 4 dan 5). Data tidak dihapus. Data yang tidak dipakai lagi dinonaktifkan.</p>
<table class="g-table">
    <thead><tr><th>Modul</th><th>Isi singkat</th><th>Siapa yang boleh mengubah</th></tr></thead>
    <tbody>
        @if ($can('ITM'))<tr><td><a href="#m-itm">Barang</a></td><td>Data barang, stok, titik pesan ulang, harga</td><td>Menambah: Data Entry dan Supervisor. Mengubah: Supervisor.</td></tr>@endif
        @if ($can('SUP'))<tr><td><a href="#m-sup">Pemasok</a></td><td>Pemasok barang</td><td>Data Entry dan Supervisor</td></tr>@endif
        @if ($can('DEP'))<tr><td><a href="#m-dep">Departemen</a></td><td>Bagian atau unit penerima barang</td><td>Data Entry dan Supervisor</td></tr>@endif
        @if ($can('RCP'))<tr><td><a href="#m-rcp">Penerima</a></td><td>Orang yang menerima barang keluar</td><td>Data Entry dan Supervisor</td></tr>@endif
        @if ($can('UOM'))<tr><td><a href="#m-uom">Satuan</a></td><td>pcs, box, kg, dan hitungan antar satuan</td><td><span class="g-role">hanya Supervisor</span></td></tr>@endif
        @if ($can('CAT'))<tr><td><a href="#m-cat">Kategori</a></td><td>Pengelompokan barang dan awalan kode</td><td><span class="g-role">hanya Supervisor</span></td></tr>@endif
        @if ($can('LOC'))<tr><td><a href="#m-loc">Lokasi Penyimpanan</a></td><td>Rak atau gudang</td><td><span class="g-role">hanya Supervisor</span></td></tr>@endif
        @if ($can('CMP'))<tr><td><a href="#m-cmp">Perusahaan</a></td><td>Nama, alamat, dan logo untuk kop semua cetakan</td><td><span class="g-role">hanya Administrator</span></td></tr>@endif
    </tbody>
</table>
<div class="g-tip"><b>Urutan menyiapkan data baru:</b> Kategori, Lokasi Penyimpanan, Satuan, Pemasok, Departemen, Penerima, lalu Barang. Barang butuh kategori, satuan, dan (bila ada) lokasi yang sudah ada. Datanya belum ada? Pakai <a href="#tombol-plus">tombol plus</a> di samping pilihan.</div>

@if ($can('ITM'))
<h3 id="m-itm">Barang</h3>
<h4>Untuk apa</h4>
<p>Daftar semua barang beserta stok sekarang. Satu baris adalah satu barang, bukan satu batch. <b>Batch</b> adalah satu kelompok barang dengan tanggal kedaluwarsa yang sama. Barang yang punya tanggal kedaluwarsa dipecah menjadi batch: satu tanggal, satu batch.</p>
@include('help.diagram', ['name' => 'ug-barang-batch', 'caption' => 'Barang, batch per tanggal kedaluwarsa, urutan keluar, dan titik pesan ulang.'])
<h4>Siapa yang bisa</h4>
<ul class="g-list">
    <li>Menambah barang baru: Data Entry dan Supervisor.</li>
    <li>Mengubah barang yang sudah ada, mengaktifkan, dan menonaktifkan: <span class="g-role">hanya Supervisor</span>. Data Entry melihat form terkunci.</li>
    <li>Menambah Satuan masuk: Data Entry dan Supervisor (aturan mengubahnya ada di bagian Satuan masuk).</li>
</ul>
<h4>Langkah: menambah barang</h4>
<ol class="g-steps">
    <li>Buka <b>Barang</b>, tekan <b>+ Data Baru</b>.</li>
    <li>Isi <b>Nama Barang</b>. Pilih <b>Kategori</b> dan <b>Satuan</b>. Pilih <b>Lokasi Rak / Penyimpanan</b> bila ada. Kode Barang terisi sendiri setelah Anda memilih Kategori.</li>
    <li>Barangnya punya tanggal kedaluwarsa (lem, oli, gemuk, dan sejenisnya)? Centang <b>Barang berkedaluwarsa</b>. Tanggalnya <b>tidak diisi di sini</b>. Tanggal diisi di setiap baris Penerimaan Barang.</li>
    <li><span class="g-role">hanya Supervisor</span> Isi bagian <b>Pesan Ulang dan Harga</b>: <b>Titik Pesan Ulang</b>, <b>Jml Pesan Ulang</b>, <b>Lead Time (hari)</b>, dan <b>Harga Satuan (Rp)</b>. Penjelasannya ada di bawah. Bila Data Entry yang menambah barang, bagian ini kosong (0) dan Supervisor melengkapinya kemudian.</li>
    <li>Tekan <b>Simpan</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li><b>Kode Barang dibuat otomatis:</b> <code>AWALAN-URUTAN</code>, mis. <code>ATK-0001</code>, tanpa tanggal. Awalan diambil dari Kategori. Kode, Kategori, dan tanda <b>Barang berkedaluwarsa</b> <b>terkunci</b> setelah disimpan.</li>
    <li><b>Kode batch:</b> <code>KODEBARANG-YYMMDD</code> (tahun, bulan, tanggal). Contoh <code>ATK-0008-261101</code> berarti kedaluwarsa 1 November 2026. Barang tanpa kedaluwarsa punya satu batch yang kodenya sama dengan kode barang.</li>
    <li><b>Batch dibuat otomatis.</b> Penerimaan Barang atau Saldo Awal yang memakai tanggal baru membuat batch baru. Tanggal yang sudah ada menambah stok batch itu. Daftar batch (kode, tanggal, stok, status) ada di bagian <b>Batch</b> pada form barang. Statusnya <b>Baik</b>, <b>Hampir kedaluwarsa</b>, atau <b>Kedaluwarsa</b>.</li>
    <li><b>Urutan keluar (FEFO).</b> FEFO artinya yang paling cepat kedaluwarsa keluar lebih dulu. Pengeluaran Barang menawarkan batch dengan tanggal terdekat di atas.</li>
    <li>Kotak di atas form menunjukkan <b>Stok saat ini</b> (semua batch) dan <b>Tersedia</b> (hanya yang belum kedaluwarsa). <span class="g-role">hanya Supervisor</span> Ada juga <b>Nilai stok</b>. Stok <b>tidak bisa diketik di sini</b>. Stok hanya berubah lewat dokumen transaksi.</li>
    <li><b>Titik Pesan Ulang</b> adalah batas stok. Bila stok tersedia sama dengan atau di bawah angka ini, Beranda memberi peringatan. Isi 0 bila tidak ingin peringatan. Angkanya dihitung per barang dari semua batch yang belum kedaluwarsa. Barang bisa berstatus <b>PESAN ULANG</b> walau di rak masih ada stok yang sudah kedaluwarsa.</li>
    <li><b>Jml Pesan Ulang</b> adalah jumlah yang disarankan untuk dipesan. <b>Lead Time (hari)</b> adalah lama dari pesan sampai barang datang. Keduanya isian manual.</li>
    <li><b>Lead time otomatis dan saran.</b> Isian <b>Lead Time (hari)</b> tidak berubah sendiri. Di bawahnya, bila barang sudah punya minimal 3 pesanan yang sudah menerima barang, tampil <i>Lead time otomatis: N hari (rata-rata dari k pemesanan)</i>. Bila ada lead time dan pemakaian, tampil kotak <b>Saran pesan ulang</b> dengan <i>Saran Titik Pesan Ulang</i> dan <i>Jml Pesan Ulang</i> beserta rumusnya. Pemakaian rata-rata adalah total Pengeluaran Terkonfirmasi 90 hari terakhir dibagi 90 (yang dibatalkan tidak dihitung). <b>Titik Pesan Ulang = pemakaian/hari × (lead time + hari pengaman)</b>, bawaan 7 hari pengaman; <b>Jml Pesan Ulang = pemakaian/hari × hari cakupan</b>, bawaan 30 hari; keduanya dibulatkan ke atas. Contoh: pemakaian 0,6/hari, lead time 5 hari: Titik Pesan Ulang 0,6 × (5 + 7) = 7,2 → 8; Jml Pesan Ulang 0,6 × 30 = 18. Hari pengaman dan hari cakupan boleh dicoba di kotak itu (angka di layar berubah, tidak disimpan). Tombol <b>Pakai saran</b> (Supervisor) hanya mengisi kolom Titik Pesan Ulang dan Jml Pesan Ulang; angkanya baru tersimpan setelah <b>Simpan</b>. Semuanya hanya saran. Supervisor mengubah Titik Pesan Ulang dan Lead Time sendiri bila setuju.</li>
    <li><b>Harga Satuan</b> hanya acuan. Nilai stok memakai rata-rata bergerak dari harga beli di Penerimaan Barang. Kolom <b>Jml Pesan Ulang</b> dan <b>Harga Satuan</b> di grid tersembunyi. Tampilkan lewat tombol <span class="g-btn">Kolom</span>.</li>
    <li><b>Satuan stok</b> tidak bisa diganti selama barang itu masih punya Satuan masuk.</li>
</ul>

<h4>Satuan masuk (kemasan saat barang diterima)</h4>
<p>Barang sering datang dalam kemasan, misalnya dus berisi 12 pcs. Satuan masuk mencatat kemasan itu beserta faktornya ke satuan stok barang. Contoh: 1 dus = 12 pcs. Stok tetap disimpan dalam satuan stok (pcs).</p>
@include('help.diagram', ['name' => 'ug-satuan-masuk', 'caption' => 'Kemasan saat barang diterima dikonversi ke satuan stok.'])
<p>Bagian <b>Satuan masuk</b> ada di form barang yang sudah disimpan. Tabelnya menunjukkan <b>Satuan masuk</b>, <b>Faktor ke {satuan stok}</b>, <b>Dipakai</b> (berapa dokumen Penerimaan Terkonfirmasi yang memakainya), dan <b>Diubah oleh</b> (nama dan waktu).</p>
<ol class="g-steps">
    <li>Tekan <b>Tambah Satuan Masuk</b>.</li>
    <li>Pilih <b>Satuan</b>. Isi <b>Faktor</b>: berapa satuan stok dalam 1 satuan ini. Lalu tekan <b>Simpan</b>. Data Entry dan Supervisor boleh menambah.</li>
    <li>Salah ketik? Tekan <b>Ubah</b> pada barisnya. Faktor yang <b>belum pernah dipakai</b> di dokumen Terkonfirmasi boleh diperbaiki siapa pun.</li>
    <li>Faktor yang <b>sudah dipakai</b> hanya bisa diubah Supervisor. Data Entry melihat tulisan <b>Hubungi Supervisor</b> di tempat tombol Ubah. Bila tetap ditolak, muncul pesan <i>Faktor ini sudah dipakai di dokumen; hubungi Supervisor untuk mengubahnya.</i></li>
</ol>
<ul class="g-list">
    <li>Perubahan faktor hanya berlaku untuk penerimaan berikutnya. Dokumen lama tidak berubah. Dokumen menyimpan jumlah yang diketik dan hasil konversinya.</li>
    <li>Satuan masuk hanya dipakai di <b>Penerimaan Barang</b>. Pengeluaran Barang dan Penyesuaian Stok <b>selalu dalam satuan stok barang</b>.</li>
    <li>Satuan stok tidak perlu faktor, karena selalu 1.</li>
</ul>

<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Form barang terkunci.</b> Data Entry tidak bisa mengubah barang yang sudah ada. Minta Supervisor.</li>
    <li><b>Kode atau Kategori salah.</b> Keduanya terkunci supaya riwayat tidak rusak. Nonaktifkan barangnya, lalu buat barang baru.</li>
    <li><b>Barang tidak muncul di pilihan dokumen.</b> Barangnya nonaktif. Di Pengeluaran Barang, barang juga tidak muncul bila stok batchnya 0 atau sudah kedaluwarsa.</li>
    <li><b>Status PESAN ULANG padahal stok ada.</b> Stok yang sudah kedaluwarsa tidak dihitung sebagai stok tersedia.</li>
    <li><b>Tidak ada peringatan sama sekali.</b> Titik Pesan Ulang masih 0. Minta Supervisor mengisinya.</li>
</ul>
@endif

@if ($can('SUP'))
<h3 id="m-sup">Pemasok</h3>
<h4>Untuk apa</h4>
<p>Daftar pemasok yang dipilih di Penerimaan Barang dan Catat Pemesanan.</p>
<h4>Siapa yang bisa</h4>
<p>Data Entry dan Supervisor.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Tekan <b>+ Data Baru</b>.</li>
    <li>Isi <b>Kode Pemasok</b> (unik, tidak bisa diubah setelah disimpan) dan <b>Nama Pemasok</b>.</li>
    <li>Isi kontak bila ada: <b>Telepon Kantor (PSTN)</b>, <b>Nama Kontak</b>, <b>HP Kontak (smartphone)</b> (juga dipakai untuk WhatsApp), dan <b>Alamat Jalan</b>.</li>
    <li>Untuk Pembelian, isi bila ada (boleh kosong): <b>Email</b> (tampil di cetakan PO), <b>Syarat Bayar (hari)</b> (0 = tunai; jadi nilai awal PO), dan <b>Catatan</b>. Tab <b>Riwayat beli</b> (hanya baca, muncul setelah pemasok disimpan) menampilkan barang yang pernah diterima dari pemasok ini: beli terakhir, harga terakhir, dan berapa kali.</li>
    <li>Pada <b>Kecamatan, Kota / Kabupaten, Provinsi, Negara</b>, ketik kecamatan, kota, atau kode pos, lalu pilih dari daftar. Satu pilihan mengisi kecamatan, kota, provinsi, negara, dan kode pos.</li>
    <li>Tekan <b>Simpan</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li><b>Kontan</b> adalah pemasok sistem untuk pembelian tunai atau tanpa pemasok tetap. Kontan selalu ada di urutan pertama pilihan Pemasok. Kontan tidak bisa dinonaktifkan, diganti nama, atau dihapus.</li>
    <li>Wilayah wajib dipilih dari daftar (minimal sampai kota) bila data wilayah sudah ada. Bila daftar wilayah masih kosong, isian ini boleh dikosongkan.</li>
    <li>Dari pilihan Pemasok di Penerimaan Barang atau Catat Pemesanan, tombol <b>+</b> menambah pemasok baru hanya dengan nama. Kodenya dibuat otomatis (SUP-001, SUP-002, ...). Alamat dan wilayah bisa dilengkapi nanti di menu ini.</li>
    <li>Pemasok yang dinonaktifkan tidak muncul di pilihan dokumen baru.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Kode pemasok ditolak.</b> Kode itu sudah dipakai. Pilih kode lain.</li>
    <li><b>Wilayah wajib diisi.</b> Cari dan pilih minimal sampai kota.</li>
    <li><b>Kontan tidak bisa dinonaktifkan.</b> Memang begitu aturannya.</li>
</ul>
@endif

@if ($can('DEP'))
<h3 id="m-dep">Departemen</h3>
<h4>Untuk apa</h4>
<p>Mencatat bagian atau unit penerima barang. Dipakai di Penerimaan Barang, Pengeluaran Barang, Penyesuaian Stok, dan laporan Pengeluaran.</p>
<h4>Siapa yang bisa</h4>
<p>Data Entry dan Supervisor.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Tekan <b>+ Data Baru</b>.</li>
    <li>Isi <b>Kode Departemen</b> (unik) dan <b>Nama Departemen</b>.</li>
    <li>Tekan <b>Simpan</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Departemen yang dinonaktifkan tidak muncul di pilihan dokumen baru. Dokumen lama tetap menampilkan namanya.</li>
    <li>Dari dokumen, tombol <b>+</b> menambah departemen baru hanya dengan nama. Kodenya dibuat otomatis.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Kode ditolak.</b> Sudah ada departemen dengan kode itu. Lewat tombol <b>+</b>, nama yang sudah ada juga ditolak.</li>
</ul>
@endif

@if ($can('RCP'))
<h3 id="m-rcp">Penerima</h3>
<h4>Untuk apa</h4>
<p>Mencatat orang yang menerima barang keluar. Dipakai di Pengeluaran Barang, dan opsional di Penerimaan Barang dan Penyesuaian Stok.</p>
<h4>Siapa yang bisa</h4>
<p>Data Entry dan Supervisor.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Tekan <b>+ Data Baru</b>.</li>
    <li>Isi <b>Nama Penerima</b>.</li>
    <li>Pilih <b>Departemen Bawaan</b> bila ada. Departemen bawaan dipakai di Pengeluaran Barang bila Anda tidak memilih departemen.</li>
    <li>Tekan <b>Simpan</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Dua penerima boleh bernama sama. Karena itu tombol <b>+</b> tidak menolak nama kembar untuk Penerima.</li>
    <li>Di form yang sudah disimpan tampil jumlah dokumen Pengeluaran penerima itu.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Penerima tidak muncul di Pengeluaran Barang.</b> Penerimanya nonaktif atau belum ada. Aktifkan atau tambahkan dengan tombol <b>+</b>.</li>
</ul>
@endif

@if ($can('UOM'))
<h3 id="m-uom">Satuan <span class="g-role">hanya Supervisor</span></h3>
<h4>Untuk apa</h4>
<p>Daftar satuan (pcs, box, kg, ...) dan konversi umumnya. Grid menampilkan jenis, konversi, dan jumlah barang pemakai.</p>
<h4>Siapa yang bisa</h4>
<p>Supervisor.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Tekan <b>+ Data Baru</b>. Isi <b>Kode Satuan</b> (singkat, huruf kecil, mis. <code>box</code>; terkunci setelah disimpan) dan <b>Nama Satuan</b>.</li>
    <li>Pilih <b>Jenis</b>. Jenis hanya menentukan warna satuan di daftar.</li>
    <li>Untuk konversi umum, tekan <b>+ Tambah Konversi</b>. Pilih satuan tujuan dan isi <b>Faktor</b>. Contoh: 1 box = 12 pcs, faktornya 12.</li>
    <li>Tekan <b>Simpan</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Isi tiap pasangan satu kali saja. Arah sebaliknya dihitung otomatis.</li>
    <li>Konversi umum berlaku untuk semua barang. Ia hanya <b>cadangan</b> di Penerimaan Barang. Bila barang punya faktor sendiri (bagian Satuan masuk di form Barang), faktor milik barang selalu didahulukan.</li>
    <li>Konversi tidak berantai. Box ke pcs dan pcs ke gram tidak dihitung otomatis menjadi box ke gram.</li>
    <li>Pengguna tanpa akses RW ke Satuan tidak mengubah konversi umum. Faktor per barang dibuat dari form Barang atau dari jendela <b>Faktor satuan belum ada</b> di Penerimaan Barang.</li>
    <li>Satuan yang dinonaktifkan tidak muncul di pilihan barang baru.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Jendela &quot;Faktor satuan belum ada&quot; muncul di Penerimaan.</b> Barang itu belum punya faktor untuk satuan yang Anda pilih. Isi faktornya di jendela itu, atau minta Supervisor mengisi konversi di sini.</li>
</ul>
@endif

@if ($can('CAT'))
<h3 id="m-cat">Kategori <span class="g-role">hanya Supervisor</span></h3>
<h4>Untuk apa</h4>
<p>Mengelompokkan barang. Awalan kategori menjadi awal kode barang.</p>
<h4>Siapa yang bisa</h4>
<p>Supervisor.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Tekan <b>+ Data Baru</b>.</li>
    <li>Isi <b>Awalan Kode</b>: tepat 3 huruf atau angka, unik, mis. <code>ATK</code>.</li>
    <li>Isi <b>Nama Kategori</b>, lalu tekan <b>Simpan</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Awalan <b>tidak bisa diubah</b> setelah disimpan.</li>
    <li>Nomor urut kode barang berjalan per kategori. Contoh: <code>ATK-0001</code>, <code>ATK-0002</code>.</li>
    <li>Kategori dipakai juga untuk saringan laporan dan untuk cakupan Stok Opname.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Awalan ditolak.</b> Awalan harus tepat 3 huruf atau angka, dan belum dipakai kategori lain.</li>
</ul>
@endif

@if ($can('LOC'))
<h3 id="m-loc">Lokasi Penyimpanan <span class="g-role">hanya Supervisor</span></h3>
<h4>Untuk apa</h4>
<p>Rak atau gudang yang dipilih di form Barang (<b>Lokasi Rak / Penyimpanan</b>). Lokasi juga dipakai untuk mengurutkan Lembar Hitung Stok Opname dan untuk cakupan opname per lokasi.</p>
<h4>Siapa yang bisa</h4>
<p>Supervisor.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Tekan <b>+ Data Baru</b>.</li>
    <li>Isi <b>Kode Lokasi</b> (unik, mis. <code>A1-01</code>; terkunci setelah disimpan) dan <b>Nama Lokasi</b>.</li>
    <li>Tekan <b>Simpan</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Lokasi yang dinonaktifkan tidak muncul di pilihan barang baru. Barang lama tetap menampilkan lokasinya.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Kode ditolak.</b> Kode itu sudah dipakai lokasi lain.</li>
</ul>
@endif

@if ($can('CMP'))
<h3 id="m-cmp">Perusahaan <span class="g-role">hanya Administrator</span></h3>
<h4>Untuk apa</h4>
<p>Menyimpan data perusahaan Anda. Data ini dicetak di <b>kop</b>, yaitu bagian paling atas kertas. Kop ada di semua cetakan: dokumen stok, Lembar Hitung, Berita Acara Stok Opname, dan keenam laporan. Anda mengisinya sekali di sini.</p>
<h4>Siapa yang bisa</h4>
<p>Supervisor dan Administrator. Data Entry tidak melihat menu ini. Grup dengan hak <b>Hanya baca (RO)</b> boleh membuka layarnya tetapi tidak boleh menyimpan.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>Data Master</b>, lalu <b>Perusahaan</b>. Layarnya satu formulir, tanpa daftar.</li>
    <li>Isi <b>Nama Perusahaan</b> (wajib). Nama ini dicetak tebal di kop.</li>
    <li>Isi <b>Nama PT / CV</b> bila ada. Ini nama badan hukum untuk urusan pajak. Ia dicetak kecil di bawah nama perusahaan.</li>
    <li>Isi <b>NPWP</b>, <b>Telepon</b>, dan <b>Email</b> bila perlu.</li>
    <li>Isi <b>Alamat</b>. Tulis satu baris alamat per baris.</li>
    <li>Di bagian <b>Cuaca di Beranda</b> isi <b>Kota untuk cuaca</b>, <b>Lintang</b>, dan <b>Bujur</b> (misalnya Malang, -7.98, 112.63; koma desimal boleh). Ini kota <b>cadangan</b> untuk kartu cuaca Beranda, dipakai bila lokasi perangkat pengguna tidak bisa dipakai (izin ditolak atau AdminKU dibuka tanpa HTTPS). Kosong semua = tanpa cadangan: kartu cuaca hanya tampil bila lokasi perangkat bisa dipakai. Lintang dan Bujur harus diisi berpasangan; lintang -90 sampai 90, bujur -180 sampai 180.</li>
    <li>Pada <b>Logo</b>, pilih berkas gambar dari komputer Anda. Tekan <b>Hapus logo</b> bila ingin membuang logo yang sudah ada.</li>
    <li>Pilih <b>Tampilan kop</b>: <b>Logo + Nama + Alamat</b>, <b>Logo + Nama</b>, atau <b>Nama saja</b>.</li>
    <li>Lihat <b>Pratinjau kop</b> di bawah isian. Pratinjau berubah langsung saat Anda mengetik atau memilih.</li>
    <li>Tekan <b>Simpan</b>. Semua cetakan berikutnya memakai kop baru.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li><b>Logo.</b> Pakai PNG, JPG, atau WebP. SVG tidak bisa dipakai. Ukuran berkas paling besar 1 MB, dan tiap sisi gambar antara 32 dan 4000 piksel. AdminKU mengecilkan logo otomatis sampai selebar 600 piksel.</li>
    <li><b>Memilih gaya dengan logo, tetapi logo belum ada?</b> Pilihan Anda tetap disimpan. Namun kop dicetak dengan <b>Nama saja</b> sampai logo diunggah. Pratinjau memberi keterangan kecil tentang hal ini.</li>
    <li><b>Nama PT / CV sama dengan nama perusahaan?</b> Namanya dicetak sekali saja. Perbedaan huruf besar dan kecil atau spasi tidak dihitung.</li>
    <li><b>Alamat</b> hanya dicetak pada gaya <b>Logo + Nama + Alamat</b>. Kop dokumen stok memuat paling banyak tiga baris alamat dan kontak. Teks yang terlalu panjang dipotong. Tulis singkat.</li>
    <li><b>NPWP</b> dicetak di kop dokumen stok. Ia tidak dicetak di Lembar Hitung, Berita Acara, dan laporan.</li>
    <li>Belum pernah diisi? Kop memakai nama bawaan <b>AdminKU</b>, <b>Nama saja</b> bila belum ada logo.</li>
    <li>Aplikasi tetap bernama <b>AdminKU</b> di halaman masuk, menu, dan judul. Hanya kop cetakan yang memakai nama perusahaan Anda.</li>
    <li>Perubahan berlaku untuk semua cetakan berikutnya, termasuk mencetak ulang dokumen lama. Dokumen yang sudah dicetak di kertas tidak berubah.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Logo ditolak.</b> Periksa jenis berkasnya (PNG, JPG, atau WebP), ukurannya (paling besar 1 MB), dan sisi gambarnya (32 sampai 4000 piksel). Berkas yang namanya berakhiran .png tetapi bukan gambar juga ditolak.</li>
    <li><b>Kop cetakan tidak berubah.</b> Muat ulang halaman cetak. Pastikan Anda sudah menekan <b>Simpan</b> dan pesan hijau muncul.</li>
    <li><b>Lintang atau Bujur ditolak.</b> Isi angka saja (boleh minus dan titik atau koma desimal), keduanya berpasangan, dalam batasnya (-90 sampai 90 dan -180 sampai 180). Koordinat kota bisa dilihat di peta (klik kanan pada kota, salin angkanya).</li>
    <li><b>Menu Perusahaan tidak ada.</b> Menu ini hanya untuk Administrator.</li>
</ul>
@endif
@endif
