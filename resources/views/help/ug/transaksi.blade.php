{{-- REMARK
 Tujuan       : Panduan Pengguna bagian 7 Transaksi: Saldo Awal, Penerimaan Barang, Pengeluaran Barang, Penyesuaian Stok, Stok Opname (modul besar), dan Membatalkan dokumen yang sudah dikonfirmasi.
                Pola tetap tiap modul: Untuk apa, Siapa yang bisa, Langkah, Yang perlu diperhatikan, Masalah umum.
 Sumber hulu  : StockDocumentController / StockDocuments / StockLedger / StockDocumentRequest (aturan dokumen), docs/form dan docs/pack-units (kolom dan jendela), StockCountController / StockCounts / StockCount / StockCountLine / CountSchedule (Stok Opname), ReorderOrders.
 Dipakai oleh : help/user-guide.blade.php (hanya bila pengguna punya salah satu modul transaksi; tiap bagian bergantung $can).
 Status       : Aktif.
--}}
@if ($anyTrx)
<h2 id="transaksi">7. Transaksi</h2>
<p>Semua perubahan stok dicatat sebagai dokumen. Nomor dokumen dibuat otomatis: awalan jenisnya (OPN, RCV, ISS, ADJ, dan SO untuk Stok Opname), lalu tahun-bulan, lalu nomor urut. Setiap dokumen mengikuti siklus <b>Draf &gt; Konfirmasi</b> (lihat bagian 5). Stok tidak bisa menjadi minus.</p>
<table class="g-table">
    <thead><tr><th>Modul</th><th>Dipakai untuk</th><th>Siapa</th></tr></thead>
    <tbody>
        @if ($can('OPN'))<tr><td><a href="#t-opn">Saldo Awal</a></td><td>Memasukkan stok yang sudah ada saat mulai memakai AdminKU</td><td><span class="g-role">hanya Supervisor</span></td></tr>@endif
        @if ($can('RCV'))<tr><td><a href="#t-rcv">Penerimaan Barang</a></td><td>Barang masuk dari pemasok</td><td>Data Entry, Supervisor</td></tr>@endif
        @if ($can('ISS'))<tr><td><a href="#t-iss">Pengeluaran Barang</a></td><td>Barang keluar ke penerima</td><td>Data Entry, Supervisor</td></tr>@endif
        @if ($can('ADJ'))<tr><td><a href="#t-adj">Penyesuaian Stok</a></td><td>Mengoreksi stok barang per barang, untuk temuan di luar jadwal</td><td><span class="g-role">hanya Supervisor</span></td></tr>@endif
        @if ($can('SO'))<tr><td><a href="#t-so">Stok Opname</a></td><td>Hitung fisik rutin untuk banyak barang sekaligus</td><td>Data Entry (mengisi hasil), Supervisor (memulai, menyetujui)</td></tr>@endif
        @if ($can('ORD'))<tr><td><a href="#t-ord">Riwayat Pemesanan</a></td><td>Daftar semua catatan pesanan ke pemasok, termasuk yang sudah tertutup</td><td>Data Entry, Supervisor</td></tr>@endif
    </tbody>
</table>
<p class="g-note">Sebuah Stok Opname yang berjalan dengan kunci transaksi menolak Konfirmasi dan Setujui.@if ($can('SO')) Lihat <a href="#t-so">Stok Opname</a>.@endif</p>

@if ($can('OPN'))
<h3 id="t-opn">Saldo Awal <span class="g-role">hanya Supervisor</span></h3>
<h4>Untuk apa</h4>
<p>Memasukkan stok yang <b>sudah ada di rak</b> saat Anda mulai memakai AdminKU. Saldo Awal <b>tidak wajib</b>. Barang yang baru datang cukup dicatat lewat Penerimaan Barang. Penerimaan pertama tidak otomatis menjadi Saldo Awal.</p>
<h4>Siapa yang bisa</h4>
<p>Hanya Supervisor.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>Saldo Awal</b>, tekan <b>+ Data Baru</b>.</li>
    <li>Isi <b>Saldo per Tanggal (awal periode)</b>. Tanggal ini tidak boleh lebih dari hari ini.</li>
    <li>Tekan <b>+ Tambah Barang</b> dan pilih barang. Barang berkedaluwarsa? Isi juga <b>Tanggal Kedaluwarsa</b>. Isi <b>Jml Awal</b> (lebih dari 0) dan <b>Nilai Satuan</b>. <b>Valuasi</b> dihitung otomatis.</li>
    <li>Tekan <b>Simpan</b>, periksa, lalu tekan <b>Konfirmasi</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Saldo Awal hanya untuk barang yang <b>belum punya pergerakan stok</b>. Pergerakan stok adalah masuk, keluar, atau penyesuaian yang sudah tercatat. Aturan ini diperiksa saat Konfirmasi.</li>
    <li>Barang yang sudah bergerak ditolak dengan pesan <i>... sudah punya pergerakan stok; pakai Penyesuaian Stok, bukan Saldo Awal.</i></li>
    <li><b>Nilai Satuan</b> adalah harga per satuan stok. Angka ini menjadi dasar biaya rata-rata dan nilai stok.</li>
    <li>Tanggal yang Anda pilih menjadi tanggal Saldo Awal di Kartu Stok.@if ($can('R-CARD')) Cara membacanya ada di <a href="#r-card">Kartu Stok</a>.@endif</li>
    <li>Salah isi Saldo Awal yang sudah Terkonfirmasi? Anda bisa membatalkannya. Tetapi barang itu kini tercatat sudah bergerak, jadi Saldo Awal baru ditolak. Koreksi lewat Penyesuaian Stok.</li>
</ul>
<p class="g-note">Perbandingan Saldo Awal dan Penerimaan ada di bagian <a href="#saldo-awal-beda">Mulai di sini</a>.</p>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Saldo Awal ditolak untuk satu barang.</b> Barang itu sudah punya pergerakan stok. Pakai Penyesuaian Stok.</li>
    <li><b>Tanggal ditolak.</b> Tanggal tidak boleh lebih dari hari ini.</li>
    <li><b>Konfirmasi ditolak: transaksi dikunci.</b> Ada Stok Opname yang berjalan. Lihat <a href="#tanya">Pertanyaan umum</a>.</li>
</ul>
@endif

@if ($can('RCV'))
<h3 id="t-rcv">Penerimaan Barang</h3>
<h4>Untuk apa</h4>
<p>Mencatat barang yang datang dari pemasok. Stok bertambah setelah Konfirmasi.</p>
<h4>Siapa yang bisa</h4>
<p>Data Entry dan Supervisor.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>Penerimaan Barang</b>, tekan <b>+ Data Baru</b>.</li>
    <li>Pilih <b>Pemasok</b>. Pemasok wajib. Pembelian tunai? Pilih <b>Kontan</b>. Pemasok belum ada? Tekan tombol <b>+</b>. Isi <b>No. Referensi (surat jalan / PO / faktur)</b> bila ada. <b>Penerima</b> dan <b>Departemen</b> boleh kosong.</li>
    <li>Tekan <b>+ Tambah Barang</b> dan pilih barang.</li>
    <li>Barangnya berkedaluwarsa? Isi <b>Tanggal Kedaluwarsa</b>. Isian ini wajib untuk barang berkedaluwarsa. Barang lain mengabaikannya. Satu kedatangan biasanya satu tanggal, jadi cukup satu baris.</li>
    <li>Isi <b>Jml</b>, <b>Satuan</b>, dan <b>Harga Beli / Satuan</b>. Kolom <b>Satuan</b> menawarkan satuan stok barang itu dan <b>Satuan masuk</b>-nya (mis. dus). Satuan lain ada di grup <b>Satuan lain</b>.</li>
    <li>Lihat kolom <b>= Dalam satuan stok</b>. Kolom ini langsung menghitung hasilnya (mis. 5 dus = 60 pcs) sebelum Konfirmasi. <b>Subtotal</b> juga terisi otomatis.</li>
    <li>Isi <b>Catatan</b> bila perlu. Tekan <b>Simpan</b> (menjadi Draf), periksa, lalu tekan <b>Konfirmasi</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    @if ($can('ITM'))<li>Cara kerja satuan masuk ada di bagian <a href="#m-itm">Barang</a>.</li>@endif
    <li>Yang Anda ketik (jumlah, satuan, harga per satuan) tercatat di dokumen. Stok dan biaya rata-rata memakai satuan stok.</li>
    <li>Satuan belum punya faktor (tidak ada di Satuan masuk barang itu dan tidak ada di konversi umum)? Saat <b>Simpan</b> atau <b>Konfirmasi</b> muncul jendela <b>Faktor satuan belum ada</b>. Isi angka pada &quot;1 dus = ... pcs&quot; untuk tiap barang, tekan <b>Simpan Faktor</b>, lalu tekan Simpan atau Konfirmasi lagi. Faktor itu tersimpan sebagai Satuan masuk milik <b>barang tersebut</b> saja.</li>
    <li>Setelah Konfirmasi, stok batch bertambah dan <b>biaya rata-rata</b> batch dihitung ulang dari harga beli. Tanggal kedaluwarsa yang belum pernah dipakai membuat <b>batch baru</b> otomatis. Tanggal yang sama menambah batch yang ada.</li>
    <li>Penerimaan yang dikonfirmasi ditambahkan ke <b>jumlah diterima</b> pesanan barang itu. Pesanan <b>tertutup otomatis</b> bila jumlah dipesan terpenuhi. Lihat <a href="#catat-pemesanan">Catat Pemesanan</a>.</li>
    <li>Tanggal dan jam dokumen adalah saat Konfirmasi.</li>
    <li>Data Entry tidak melihat harga acuan barang, tetapi tetap mengetik <b>Harga Beli / Satuan</b>.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Pemasok tidak ada di pilihan.</b> Pemasok nonaktif atau belum ada. Tambahkan dengan tombol <b>+</b>, atau pilih <b>Kontan</b>.</li>
    <li><b>Pesan &quot;Isi tanggal kedaluwarsa&quot;.</b> Barang itu berkedaluwarsa. Isi Tanggal Kedaluwarsa di barisnya.</li>
    <li><b>Jendela &quot;Faktor satuan belum ada&quot;.</b> Isi faktornya, lalu Simpan lagi.</li>
    <li><b>Salah pemasok atau jumlah.</b> Dokumen Terkonfirmasi terkunci. Batalkan lalu buat dokumen baru.</li>
    <li><b>Pembatalan ditolak.</b> Sebagian barangnya sudah keluar. Lihat <a href="#t-batal">Membatalkan dokumen</a>.</li>
</ul>
@endif

@if ($can('ISS'))
<h3 id="t-iss">Pengeluaran Barang</h3>
<h4>Untuk apa</h4>
<p>Mencatat barang yang keluar ke penerima. Stok berkurang setelah Konfirmasi.</p>
<h4>Siapa yang bisa</h4>
<p>Data Entry dan Supervisor.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>Pengeluaran Barang</b>, tekan <b>+ Data Baru</b>.</li>
    <li>Pilih <b>Penerima</b> (wajib). Pilih <b>Departemen</b> bila perlu. Departemen kosong berarti departemen bawaan penerima. Isi <b>Keperluan (maintenance / ATK ...)</b>.</li>
    <li>Tekan <b>+ Tambah Barang</b> dan pilih barang. Barang berkedaluwarsa muncul per batch. Batch yang kedaluwarsanya paling cepat ada di atas. Kolom <b>Tersedia</b> menunjukkan stok batch itu.</li>
    <li>Isi <b>Jml</b>. Satuannya selalu satuan stok barang. Satuan ditampilkan di kolom <b>Satuan</b> dan tidak bisa dipilih.</li>
    <li>Tekan <b>Simpan</b>, periksa, lalu tekan <b>Konfirmasi</b>. Stok berkurang.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li><b>FEFO.</b> Yang paling cepat kedaluwarsa keluar lebih dulu.</li>
    <li>Jumlah melebihi stok batch yang Anda pilih? Sisanya otomatis diambil dari batch berikutnya, yang kedaluwarsanya paling cepat dulu. Batch yang sudah kedaluwarsa tidak ditawarkan.</li>
    <li><b>Peringatan batch.</b> Anda memilih batch yang kedaluwarsanya lebih jauh, padahal ada batch yang lebih cepat kedaluwarsa dan masih berstok? Setelah Simpan, form menampilkan peringatan kuning di atas. Ini hanya peringatan. Dokumen tetap bisa dikonfirmasi.</li>
    <li><b>Stok tidak boleh minus.</b> Jumlah melebihi stok yang ada? Konfirmasi ditolak dengan pesan <i>Stok ... tidak cukup: tersedia ..., dibutuhkan ...</i></li>
    <li>Stok barang sampai Titik Pesan Ulang setelah Konfirmasi? Muncul peringatan kuning <i>Titik pesan ulang tercapai: ...</i> di atas form.</li>
    <li>Stok berkurang pada nilai biaya rata-rata. Nilainya hanya terlihat oleh Supervisor.</li>
    <li>Pengeluaran Barang selalu dalam satuan stok barang. Tidak ada pilihan satuan.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Barang tidak muncul di pilihan.</b> Barang nonaktif, stok batchnya 0, atau batchnya sudah kedaluwarsa.</li>
    <li><b>Konfirmasi ditolak: stok tidak cukup.</b> Periksa kolom <b>Tersedia</b>. Stok fisik memang ada? Catat dulu Penerimaan Barang, atau minta Supervisor membuat Penyesuaian Stok.</li>
    <li><b>Konfirmasi ditolak: transaksi dikunci.</b> Ada Stok Opname yang berjalan. Lihat <a href="#tanya">Pertanyaan umum</a>.</li>
    <li><b>Penerima tidak ada.</b> Tambahkan dengan tombol <b>+</b>.</li>
</ul>
@endif

@if ($can('ADJ'))
<h3 id="t-adj">Penyesuaian Stok <span class="g-role">hanya Supervisor</span></h3>
<h4>Untuk apa</h4>
<p>Mengoreksi stok barang per barang, untuk temuan di luar jadwal. Contohnya barang rusak, salah catat, atau stok kedaluwarsa yang dibuang. Untuk hitung fisik rutin banyak barang, pakai @if ($can('SO'))<a href="#t-so">Stok Opname</a>@else Stok Opname @endif. Penyesuaian <b>tidak langsung mengubah stok</b>. Ia harus disetujui lebih dulu.</p>
<h4>Siapa yang bisa</h4>
<p>Hanya Supervisor.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>Penyesuaian Stok</b>, tekan <b>+ Data Baru</b>.</li>
    <li>Isi <b>Alasan penyesuaian / opname</b> (wajib).</li>
    <li>Tekan <b>+ Tambah Barang</b> dan pilih barang. <b>Jml Sistem</b> terisi otomatis. Isi <b>Jml Hitung (fisik)</b>. <b>Selisih</b> dihitung otomatis: plus berarti stok bertambah, minus berarti stok berkurang.</li>
    <li>Tekan <b>Simpan</b>, lalu <b>Konfirmasi</b>. Dokumen menjadi <b>Menunggu Persetujuan</b>. Stok belum berubah.</li>
    <li>Supervisor menekan <b>Setujui</b>: stok diperbarui dan status menjadi <b>Disetujui</b>. Atau <b>Tolak</b>: stok tidak berubah dan status menjadi <b>Ditolak</b>.</li>
</ol>
@include('help.diagram', ['name' => 'ug-penyesuaian', 'caption' => 'Alur Penyesuaian Stok.'])
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li><b>Setujui ditolak bila stok barang berubah</b> sejak dihitung, misalnya ada penerimaan atau pengeluaran baru. Tolak dokumennya, lalu hitung ulang di dokumen baru.</li>
    <li>Satuannya selalu satuan stok barang.</li>
    <li>Di grid tersedia kotak <b>Menunggu persetujuan</b> dan <b>Draf</b> untuk menyaring cepat.</li>
    <li>AdminKU juga membuat Penyesuaian otomatis dalam dua keadaan: saat Stok Opname disetujui (alasan &quot;Stok Opname SO-...&quot;) dan saat dokumen dibatalkan (alasan &quot;Pembalik otomatis pembatalan ...&quot;).</li>
    <li>AdminKU belum melarang pembuat dokumen menyetujui dokumennya sendiri.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Setujui ditolak: &quot;Stok ... berubah sejak penghitungan&quot;.</b> Tolak dokumen itu, lalu buat Penyesuaian baru dengan hitungan terbaru.</li>
    <li><b>Salah isi setelah Konfirmasi.</b> Selama masih Menunggu Persetujuan, tekan <b>Batalkan</b> (alasan wajib) untuk menariknya, lalu buat yang baru.</li>
</ul>
@endif

@if ($can('SO'))
<h3 id="t-so">Stok Opname</h3>
<h4>Untuk apa</h4>
<p>Stok Opname adalah <b>penghitungan fisik rutin</b>, mingguan atau bulanan. Pada hari itu beberapa orang menghitung barang di gudang dengan <b>kertas dan alat tulis</b>. Hasilnya <b>diinput sekaligus</b> untuk banyak barang. AdminKU menghitung <b>selisih</b>: hasil hitung fisik dikurangi stok di sistem. Setelah Supervisor menyetujui, AdminKU membuat <b>satu Penyesuaian Stok otomatis</b> sehingga stok di sistem sama dengan stok di rak. Penghitungan dilakukan <b>per batch</b>: satu tanggal kedaluwarsa, satu baris.</p>
<table class="g-table">
    <thead><tr><th></th><th>Stok Opname</th><th>Penyesuaian Stok</th></tr></thead>
    <tbody>
        <tr><td>Dipakai untuk</td><td>Hitung fisik rutin, banyak barang sekaligus</td><td>Temuan tidak rutin, barang per barang</td></tr>
        <tr><td>Siapa yang mengisi</td><td>Data Entry atau Supervisor menginput hasil hitung</td><td>Supervisor mengisi dokumennya</td></tr>
        <tr><td>Hasilnya</td><td>Satu Penyesuaian otomatis setelah Setujui Opname</td><td>Dokumen yang menunggu <b>Setujui</b></td></tr>
    </tbody>
</table>
<h4>Siapa yang bisa</h4>
<ul class="g-list">
    <li><b>Data Entry:</b> membuka daftar, mengisi hasil hitung, menambah barang yang ditemukan, menekan Selesai Hitung, dan mencetak Lembar Hitung.</li>
    <li><span class="g-role">hanya Supervisor</span> Memulai, meninjau, menyetujui, mengembalikan, membatalkan, membuka dan mengunci transaksi, mengatur jadwal, mencetak Berita Acara, dan melihat nilai.</li>
</ul>
<h4>Langkah</h4>
@include('help.diagram', ['name' => 'ug-opname-langkah', 'caption' => 'Langkah kerja Stok Opname dari Mulai Opname sampai Penyesuaian otomatis.'])
<ol class="g-steps">
    <li><b>Mulai</b> <span class="g-role">hanya Supervisor</span>. Di <b>Stok Opname</b>, tekan <b>Mulai Opname</b>. Isi <b>Tanggal Opname</b>. Pilih <b>Cakupan</b>: Semua barang, Per Kategori, atau Per Lokasi. Pilih Kategori atau Lokasinya bila perlu. Isi <b>Ambang Selisih (%)</b> (bawaan 5). Pilih <b>Hitung buta</b> dan <b>Kunci transaksi selama opname</b> (keduanya bawaan Ya). Tekan <b>Simpan</b>. Simpan berarti mulai.
        <ul class="g-list">
            <li>Saat mulai, AdminKU mencatat stok sistem dan biaya rata-rata tiap batch yang berstok dan aktif. Inilah <b>titik acuan stok</b> (disebut juga <b>cut-off</b>). Selisih dihitung terhadap titik acuan ini.</li>
            <li>Nomornya berawalan <b>SO</b>, mis. SO-2611-0001.</li>
            <li><b>Hitung buta Ya:</b> stok sistem disembunyikan di Lembar Hitung dan layar input sampai Tinjau. Lebih objektif. <b>Tidak:</b> stok sistem dan selisih langsung tampil. Lebih cepat.</li>
            <li><b>Kunci transaksi Ya:</b> Konfirmasi dan Setujui transaksi stok ditolak sampai opname selesai atau dibatalkan.</li>
            <li><b>Ambang Selisih:</b> selisih di atas persen ini wajib diberi alasan saat Tinjau.</li>
            <li>Tidak ada batch berstok dalam cakupan itu? AdminKU menolak dan meminta cakupan lain.</li>
        </ul>
    </li>
    <li><b>Cetak Lembar Hitung.</b> Tekan <b>Cetak Lembar Hitung</b>. Lembar A4 lanskap ini dikelompokkan per Lokasi, lalu kode. Kolom <b>Hasil Hitung</b>, <b>Penghitung</b>, dan <b>Catatan</b> dikosongkan untuk ditulis tangan. Kolom <b>Stok Sistem</b> hanya tercetak bila opname tidak buta. Di bawahnya ada kotak tanda tangan Penghitung dan Mengetahui (Supervisor). Data Entry dan Supervisor boleh mencetaknya.</li>
    <li><b>Hitung di gudang</b> memakai lembar itu. Tulis <b>0</b> bila barang memang kosong. <b>Kosongkan</b> bila belum dihitung.</li>
    <li><b>Input hasil hitung</b> (Data Entry atau Supervisor). Buka opname dari daftar. Setiap baris adalah satu batch. Isi <b>Hasil Hitung</b> (angka 0 atau lebih, sampai 3 desimal, dalam satuan stok) dan <b>Penghitung</b>. Nama penghitung terakhir diingat untuk baris berikutnya.
        <ul class="g-list">
            <li>Setiap perubahan <b>tersimpan otomatis</b> begitu Anda pindah sel.</li>
            <li>Ingin memastikan semuanya sudah masuk? Tekan <b>Simpan Cadangan</b>. AdminKU membandingkan jumlah baris terisi di layar dengan yang ada di server, lalu menampilkan &quot;Semua isian tersimpan di server: ...&quot;. Bila jumlahnya berbeda, muncul peringatan merah dan isian Anda mungkin belum tersimpan: muat ulang halaman dan isi lagi baris yang kurang.</li>
            <li>Kotak <b>Terisi</b> dan <b>Belum diisi</b> di atas lembar menghitung kemajuan langsung. Klik <b>Belum diisi</b> untuk melihat baris yang tersisa. Kotak <b>Cari</b> mencari kode atau nama.</li>
            <li>Beberapa orang boleh mengisi baris yang berbeda sekaligus. AdminKU mencatat <b>siapa yang menginput</b> tiap baris. Namanya tampil di kolom <b>Diinput oleh</b> di layar Tinjau dan di Berita Acara.</li>
            <li>Baris yang sama sudah diubah orang lain sejak layar Anda dimuat? Muncul peringatan &quot;Baris ... sudah diubah oleh ... pada ...&quot;. Isi alasan lalu Simpan untuk <b>menimpa</b>. Angka yang Anda ketik menggantikan angka lama, bukan ditambahkan. Atau pilih Batal untuk membuang isian Anda.</li>
            <li>Opname <b>tidak buta</b>? Kolom <b>Stok Sistem</b> dan <b>Selisih</b> tampil langsung di layar input, dan baris yang selisihnya <b>melewati ambang</b> diberi warna merah muda supaya mudah dilihat.</li>
        </ul>
    </li>
    <li><b>Barang tidak ketemu atau tidak bisa dihitung?</b> Isi <b>Catatan</b> dulu, lalu centang <b>Tidak dihitung</b>. Catatan wajib. Baris itu tidak menghasilkan selisih.</li>
    <li><b>Barang ditemukan tetapi tidak ada di lembar?</b> Tekan <b>Tambah Barang</b> dan pilih barang atau batchnya. Stok sistemnya diambil saat itu. Barang itu ikut dihitung seperti baris lain.</li>
    <li><b>Selesai Hitung.</b> Tombol ini hanya berhasil bila semua baris terisi, atau Tidak dihitung dengan catatan. Bila belum, pesannya menyebut kode barang yang tersisa. Sesudahnya isian terkunci dan opname masuk <b>Tinjau</b>.</li>
    <li><b>Tinjau</b> <span class="g-role">hanya Supervisor</span>. Layar menampilkan stok sistem, hasil fisik, dan <b>selisih</b> (jumlah, persen, dan nilai). Nilai adalah selisih dikali biaya rata-rata saat titik acuan. Ada juga ringkasan nilai lebih dan nilai kurang. Saring dengan kotak <b>Hanya selisih</b> atau <b>Selisih besar</b>.
        <ul class="g-list">
            <li>Baris yang selisihnya melewati ambang (kolom <b>Besar</b>) diberi <b>warna merah muda</b> di seluruh barisnya.</li>
            <li>Isi <b>Hitung Ulang</b> bila perlu. Bila ada, angka itulah yang dipakai.</li>
            <li>Stok sebuah barang bergeser sejak titik acuan? Muncul kotak merah berisi tombol <b>Perbarui Baris Bergeser</b> (semua baris yang bergeser sekaligus) dan satu tombol <b>Perbarui &lt;kode barang&gt;</b> per baris. Tombol per baris hanya memperbarui barang itu: stok sistemnya diganti stok sekarang dan barisnya dikosongkan untuk dihitung ulang. Baris lain yang sudah terisi tidak disentuh. Opname kembali ke Sedang dihitung; isi baris itu lalu tekan Selesai Hitung lagi.</li>
            <li>Isi <b>Alasan</b> tiap selisih. Alasan <b>wajib</b> bila selisihnya melewati ambang (kolom <b>Besar</b>). Stok sistem 0 dan ada selisih? Alasan juga wajib.</li>
            <li>Tekan <b>Pratinjau Berita Acara</b> untuk melihat berita acaranya.</li>
        </ul>
    </li>
    <li><b>Setujui Opname</b> <span class="g-role">hanya Supervisor</span>. AdminKU memeriksa tiga hal: semua baris sudah final, alasan terisi, dan stok barang yang berselisih belum bergeser sejak titik acuan. Bila semuanya benar, AdminKU membuat <b>satu dokumen Penyesuaian Stok</b> (status Disetujui, alasan &quot;Stok Opname SO-...&quot;) berisi baris yang selisihnya tidak nol. Dokumen itu diposting ke stok dan kunci transaksi dilepas. Tidak ada selisih sama sekali? Opname selesai tanpa Penyesuaian.</li>
    <li><b>Kembalikan ke Penghitungan</b> (dengan catatan) mengembalikan opname agar diperbaiki. Stok tidak berubah. Setelah diperbaiki, tekan Selesai Hitung lagi.</li>
    <li><b>Berita Acara Stok Opname.</b> Tombol <b>Cetak Berita Acara</b> ada sesudah opname Selesai. Sebelum itu ada <b>Pratinjau Berita Acara</b>. Isinya: nomor, tanggal, cakupan, cut-off, ambang selisih, daftar penghitung, ringkasan, baris selisih beserta alasannya, nomor Penyesuaian yang dibuat, dan tiga kotak tanda tangan (Penghitung, Supervisor, Penanggung Jawab Gudang).</li>
</ol>
@include('help.diagram', ['name' => 'ug-stok-opname', 'caption' => 'Alur Stok Opname: status dari Mulai sampai Selesai atau Dibatalkan.'])
<h4>Yang perlu diperhatikan</h4>
<table class="g-table">
    <thead><tr><th>Isian di kolom Hasil Hitung</th><th>Artinya</th></tr></thead>
    <tbody>
        <tr><td>Kosong</td><td>Belum dihitung. Opname belum bisa Selesai Hitung.</td></tr>
        <tr><td>0</td><td>Sudah dihitung dan barangnya memang kosong. Selisihnya sama dengan minus stok sistem.</td></tr>
        <tr><td>Tidak dihitung (centang, dengan Catatan)</td><td>Barang tidak ketemu atau tidak bisa dihitung. Baris itu tidak menghasilkan selisih dan stoknya tidak diubah.</td></tr>
    </tbody>
</table>
<div class="g-warn"><b>Kunci transaksi.</b> Opname berstatus Sedang dihitung atau Tinjau, dan kuncinya aktif? AdminKU menolak tiga hal ini:
    <ul class="g-list">
        <li><b>Konfirmasi</b> Penerimaan Barang, Pengeluaran Barang, dan Saldo Awal.</li>
        <li><b>Setujui</b> Penyesuaian Stok.</li>
        <li>Pembatalan dokumen yang sudah diproses.</li>
    </ul>
    Pesannya: &quot;Stok Opname SO-... sedang berjalan, transaksi dikunci. Hubungi Supervisor.&quot; Menyimpan <b>Draf</b> tetap boleh. Kunci lepas saat opname Selesai atau Dibatalkan.</div>
<ul class="g-list">
    <li><b>Buka Kunci dan Kunci.</b> Gudang harus jalan lagi di tengah opname? Supervisor menekan <b>Buka Kunci</b>. Tombol <b>Kunci</b> menguncinya kembali. Keduanya mencatat siapa dan kapan.</li>
    <li><b>Stok bergeser.</b> Transaksi yang menggeser stok sesudah titik acuan membuat persetujuan ditolak. Tekan <b>Perbarui Baris Bergeser</b>. Stok sistem baris itu diganti stok sekarang dan barisnya dikosongkan untuk dihitung ulang. Opname kembali ke Sedang dihitung.</li>
    <li><b>Penyetuju yang juga penginput.</b> Anda sendiri yang menginput sebagian besar baris? Muncul peringatan. Disarankan penyetuju bukan penginput. Ini hanya peringatan, bukan larangan.</li>
    <li>Barang yang tidak ada di Lembar Hitung dan tidak Anda tambahkan tidak disentuh. Baris Tidak dihitung tidak menghasilkan selisih.</li>
    <li>Hasil opname yang sudah <b>Selesai</b> permanen. Ia tidak bisa diubah atau dihapus. Koreksinya lewat Penyesuaian Stok biasa.</li>
    <li>Hanya <b>satu</b> opname boleh berjalan pada satu waktu (Sedang dihitung atau Tinjau). Selesaikan atau tekan <b>Batalkan Opname</b> dulu sebelum memulai yang baru. Membatalkan melepas kunci dan tidak mengubah stok.</li>
    <li>Hasil opname terlihat di <b>Kartu Stok</b> sebagai Penyesuaian dengan keterangan &quot;Stok Opname SO-...&quot;.</li>
</ul>
<h4>Jadwal dan pengingat</h4>
<ul class="g-list">
    <li><span class="g-role">hanya Supervisor</span> Di daftar Stok Opname, atur <b>Frekuensi Opname</b>: Bulanan atau Mingguan. Bulanan memakai <b>Tanggal (bulanan)</b> tertentu atau Akhir bulan (bawaan). Mingguan memakai <b>Hari (mingguan)</b>. Tekan <b>Simpan Jadwal</b>.</li>
    <li>Semua yang punya modul Stok Opname melihat jadwalnya di daftar dan pengingatnya di <b>Beranda</b>.</li>
    <li>Tanggal berikutnya dihitung dari opname <b>Selesai</b> terakhir. Opname yang dibatalkan tidak dihitung.</li>
    <li>Menu Stok Opname diberi tanda kecil (titik atau tanda seru) bila ada opname yang berjalan atau jadwalnya terlambat.</li>
    <li>Tidak ada notifikasi e-mail atau WhatsApp. Pengingat hanya tampil di layar.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Mulai Opname ditolak: sudah ada opname berjalan.</b> Selesaikan atau batalkan opname itu dulu.</li>
    <li><b>Selesai Hitung ditolak.</b> Masih ada baris kosong, atau Tidak dihitung tanpa catatan. Pesan menyebut kode barangnya.</li>
    <li><b>Setujui Opname ditolak: stok bergeser.</b> Tekan Perbarui Baris Bergeser, hitung ulang barisnya, lalu Selesai Hitung lagi.</li>
    <li><b>Setujui Opname ditolak: alasan wajib.</b> Isi kolom Alasan pada baris yang bertanda Besar.</li>
    <li><b>Penerimaan atau Pengeluaran ditolak: transaksi dikunci.</b> Tunggu opname selesai, atau minta Supervisor menekan <b>Buka Kunci</b>.</li>
    <li><b>Tombol Mulai Opname tidak ada.</b> Tombol itu hanya untuk Supervisor.</li>
</ul>
@endif

@if ($can('ORD'))
<h4 id="so-hp">Menghitung di HP</h4>
<p>Ketuk <b>&#8801;</b> (di layar yang punya aksi: ketuk <b>Menu</b> di bilah), lalu <b>Opname</b> (Tampilan HP). Opname yang sedang dihitung punya tombol <b>Hitung sekarang</b>. Aturannya sama dengan di komputer; hanya tampilannya satu barang per kartu.</p>
<ol class="g-steps">
    <li>Kartu menampilkan nama, kode, tanggal kedaluwarsa, dan rak. Ketik hasil hitung di kolom besar. Satuan stok tertulis di sampingnya. Desimal pakai koma, tanpa pemisah ribuan.</li>
    <li>Tekan <b>Enter</b> di keyboard HP atau <b>Barang berikutnya</b>. Isian tersimpan per barang dan layar pindah ke barang berikutnya. <b>Sebelumnya</b> kembali satu barang. Penanda <b>Barang 5 / 120</b> dan jumlah yang sudah terisi tampil di atas.</li>
    <li><b>Kosong = belum dihitung. 0 = barang memang kosong.</b> Barang tidak ketemu: centang <b>Tidak dihitung</b> dan isi catatan.</li>
    <li>Kolom <b>Cari</b> di atas melompat ke barang: ketik sebagian kode atau nama lalu pilih. Pemindai barcode Bluetooth atau USB (bertingkah seperti keyboard) cukup memindai ke kolom Cari: kode yang pas langsung membuka barangnya. Tulisan tangan dengan pen (mis. S Pen) bisa dipakai di kolom isian mana pun.</li>
    <li>Tulisan di atas menunjukkan <b>Tersimpan</b>. Bila sinyal putus, tertulis <b>Gagal menyimpan</b> dan isian tertahan di HP, lalu dikirim ulang sendiri atau lewat <b>Coba lagi</b>. Jangan menutup halaman sebelum tertulis Tersimpan.</li>
    <li>Bila barang itu sudah diisi orang lain, kartu memberi peringatan kuning (siapa dan jam berapa). Bila orang lain mengubahnya sejak layar Anda dibuka, AdminKU meminta alasan untuk menimpa, atau pilih <b>Buang isian saya</b>.</li>
    <li>Sesudah barang terakhir muncul <b>Ringkasan</b> (juga lewat tombol Ringkasan di atas). Semua terisi: tekan <b>Selesai Hitung</b>. Yang belum terisi tampil sebagai daftar; sentuh untuk membukanya.</li>
</ol>
<p>Pada opname <b>hitung buta</b> stok sistem tidak ditampilkan sama sekali, seperti di komputer.</p>

<h3 id="t-ord">Riwayat Pemesanan</h3>
<h4>Untuk apa</h4>
<p>Melihat semua catatan <a href="#catat-pemesanan">Catat Pemesanan</a>, termasuk yang sudah tertutup. Di Beranda hanya pesanan yang masih terbuka tampil. Di sini Anda juga melihat pesanan <b>Selesai</b> dan <b>Ditutup manual</b>, dan penerimaan mana yang memenuhinya. Layar ini tidak memuat harga atau nilai uang.</p>
<h4>Siapa yang bisa</h4>
<p>Data Entry dan Supervisor. Tombol <b>Tutup Pesanan</b> hanya tampil bagi yang boleh menulis modul ini. Grup dengan hak <b>Hanya baca (RO)</b> hanya melihat.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>Transaksi</b>, lalu <b>Riwayat Pemesanan</b>.</li>
    <li>Pilih kotak status di atas grid: <b>Semua</b>, <b>Terbuka</b> (Dipesan dan Diterima sebagian), <b>Selesai</b>, atau <b>Ditutup manual</b>.</li>
    <li>Atur <b>Dari tanggal</b> dan <b>Sampai tanggal</b> (tanggal pesan), lalu tekan <b>Terapkan</b>. Kosong berarti semua tanggal. Pakai <b>Saring</b> untuk memilih satu <b>Pemasok</b>, dan kotak <b>Cari</b> untuk barang, pemasok, atau referensi.</li>
    <li>Klik 2x pada baris, atau tekan ikon <b>Buka</b>, untuk melihat detail pesanan.</li>
    <li>Di detail, lihat tabel <b>Penerimaan yang dialokasikan</b>: nomor dokumen Penerimaan, tanggal, dan jumlahnya.</li>
    <li>Pesanan masih terbuka dan pemasok tidak akan mengirim sisanya? Tekan <b>Tutup Pesanan</b>, isi alasan bila perlu, lalu tekan <b>Tutup Pesanan</b> di jendela itu.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Kolom grid: <b>Tanggal Pesan</b>, <b>No. Referensi Pemesanan</b>, <b>Barang</b>, <b>Pemasok</b>, <b>Jumlah Dipesan</b> (satuan stok), <b>Diterima</b>, <b>Sisa</b>, <b>Status</b>, <b>Pertama Diterima</b>, <b>Lead Time (hari)</b>, <b>Dicatat oleh</b>, dan <b>Ditutup</b>.</li>
    <li><b>Status</b> berwarna sama dengan Beranda: hijau <b>Dipesan</b>, kuning <b>Diterima sebagian</b>. Ditambah biru <b>Selesai</b> (jumlah dipesan sudah terpenuhi) dan abu-abu <b>Ditutup manual</b>.</li>
    <li><b>Lead Time (hari)</b> adalah lama dari tanggal pesan sampai penerimaan pertama. Kolomnya kosong bila belum ada penerimaan.</li>
    <li>Kolom <b>Ditutup</b> memuat tanggal penutupan. Pesanan yang ditutup manual juga memuat alasannya.</li>
    <li>Catatan lama yang dibuat sebelum ada jumlah tampil dengan tanda &quot;-&quot; pada <b>Jumlah Dipesan</b>, <b>Diterima</b>, dan <b>Sisa</b>. Ini normal.</li>
    <li>Pesanan yang ditutup manual tidak dirinci penerimaannya. Detailnya hanya menunjukkan jumlah yang tercatat sampai pesanan ditutup.</li>
    <li>Pesanan yang ditutup tidak bisa dibuka lagi. Anda bisa mencatat pesanan baru dari Beranda.</li>
    <li>Layar ini hanya membaca. Belum ada tombol untuk mengubah atau menghapus catatan pesanan.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Pesanan tidak ada di daftar.</b> Periksa tanggal pesan dan saringan. Tekan <b>Semua</b> pada kotak status.</li>
    <li><b>Tombol Tutup Pesanan tidak ada.</b> Pesanannya sudah tertutup, atau grup Anda hanya boleh membaca.</li>
    <li><b>Penerimaan tidak muncul di tabel detail.</b> Penerimaan itu Draf, dibatalkan, atau tanggalnya sebelum tanggal pesan.</li>
</ul>
@endif

<h3 id="t-batal">Membatalkan dokumen yang sudah dikonfirmasi</h3>
<h4>Untuk apa</h4>
<p>Membatalkan dokumen yang sudah terlanjur dikonfirmasi. Stok dikembalikan. Riwayat stok tidak dihapus.</p>
<h4>Siapa yang bisa</h4>
<ul class="g-list">
    <li><b>Supervisor</b> dan Administrator: dokumen langsung <b>Dibatalkan</b>.</li>
    <li><b>Data Entry:</b> membuat <b>pengajuan pembatalan</b> yang menunggu Supervisor.</li>
</ul>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka dokumennya (klik 2x di grid), tekan <b>Batalkan</b>.</li>
    <li>Kotak <b>Konfirmasi</b> meminta <b>Alasan</b>. Alasan wajib, minimal 3 huruf dan maksimal 255. Isi, lalu tekan <b>Ya, lanjutkan</b>. Tombol batal di kotak itu hanya menutup kotak.</li>
    <li>Supervisor: dokumen langsung <b>Dibatalkan</b> dan stok dikembalikan.</li>
    <li>Data Entry: status menjadi <b>Pembatalan Menunggu Persetujuan</b> dan stok belum berubah. Supervisor menekan <b>Setujui</b> (dokumen Dibatalkan, stok dikembalikan) atau <b>Tolak</b> (dokumen kembali berlaku).</li>
    <li>Pengaju boleh <b>menarik</b> pengajuannya selama masih menunggu. Tekan <b>Batalkan</b> lagi. Hanya pengaju atau Supervisor yang boleh.</li>
</ol>
@include('help.diagram', ['name' => 'ug-pembatalan', 'caption' => 'Alur membatalkan dokumen yang sudah dikonfirmasi.'])
<h4>Yang perlu diperhatikan</h4>
<div class="g-warn"><b>Pembatalan bisa ditolak otomatis bila stok tidak cukup.</b> Contoh: Penerimaan sudah sebagian dikeluarkan, jadi menarik kembali penerimaan itu membuat stok minus. Peringatannya tampil di form sebelum Anda menekan tombol. Solusinya: buat <b>Penyesuaian Stok</b> untuk mengoreksi.</div>
<ul class="g-list">
    <li>Setiap pembatalan dokumen yang sudah diproses otomatis membuat dokumen <b>Penyesuaian Stok pembalik</b>. Statusnya langsung Disetujui, alasannya &quot;Pembalik otomatis pembatalan ...&quot;. Nomornya tampil di Riwayat dokumen asal. Dokumen pembalik tidak bisa dibatalkan lagi.</li>
    <li>Riwayat stok <b>tidak dihapus</b>. Pembatalan menambah baris pembalik. Di <b>Kartu Stok</b> baris itu ditandai <b>(BATAL)</b>.</li>
    <li>Pembatalan Penerimaan menghitung ulang pesanan. Pesanan yang jumlah diterimanya jadi kurang dari jumlah dipesan terbuka kembali.</li>
    <li>Dokumen <b>Draf</b> tidak dibatalkan. Ia di-<b>Hapus</b>.</li>
    <li>Saat Stok Opname berjalan dengan kunci transaksi, pembatalan dokumen yang sudah diproses juga ditolak.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Pembatalan ditolak: stok tidak cukup.</b> Koreksi lewat Penyesuaian Stok.</li>
    <li><b>Tombol Batalkan tidak ada.</b> Dokumen itu masih Draf (pakai Hapus) atau dokumen pembalik otomatis (tidak bisa dibatalkan).</li>
</ul>
@endif
