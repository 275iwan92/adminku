{{-- REMARK
 Tujuan       : Panduan Pengguna bagian 11 Istilah: glosarium singkat istilah gudang dan istilah Stoku, ditulis sederhana untuk pengguna awam dan penguji pemula.
 Sumber hulu  : label layar (App\Support\Indonesian), aturan di StockLedger / StockDocuments / StockCounts / ReorderOrders / Product / UnitConverter.
 Dipakai oleh : help/user-guide.blade.php (semua peran).
 Status       : Aktif.
--}}
<h2 id="istilah">11. Istilah</h2>
<p>Istilah yang sering muncul di Stoku, dengan arti yang sederhana. Urut abjad.</p>
<table class="g-table">
    <thead><tr><th>Istilah</th><th>Artinya</th></tr></thead>
    <tbody>
        <tr><td>Aman, Segera, Terlambat</td><td>Status berwarna di GA untuk kendaraan dan AC: hijau masih jauh dari jatuh tempo, kuning dalam 30 hari (atau 1.000 km untuk servis kendaraan), merah sudah lewat.</td></tr>
        <tr><td>Batch</td><td>Satu kelompok barang dengan tanggal kedaluwarsa yang sama. Satu tanggal kedaluwarsa, satu batch. Kodenya <code>KODEBARANG-YYMMDD</code>.</td></tr>
        <tr><td>Berlaku sampai</td><td>Tanggal terakhir sebuah dokumen kendaraan (pajak tahunan, STNK dan plat, KIR, asuransi) masih berlaku. Terisi otomatis dari tanggal bayar, boleh diubah.</td></tr>
        <tr><td>Berita Acara</td><td>Surat hasil Stok Opname yang dicetak dan ditandatangani Penghitung, Supervisor, dan Penanggung Jawab Gudang.</td></tr>
        <tr><td>Biaya rata-rata</td><td>Harga per satuan stok, dirata-rata dari harga beli setiap kali barang masuk. Dipakai untuk menghitung nilai stok. Hanya terlihat oleh Supervisor.</td></tr>
        <tr><td>Cuci rutin</td><td>Pembersihan AC berkala oleh vendor (bawaan tiap 3 bulan). Servis besar sudah mencakup cuci.</td></tr>
        <tr><td>Cut-off (titik acuan stok)</td><td>Saat Stok Opname dimulai. Stoku mencatat stok sistem pada saat itu sebagai pembanding. Selisih dihitung terhadap catatan itu.</td></tr>
        <tr><td>Data Master</td><td>Data acuan yang dipilih di dokumen: barang, pemasok, satuan, kategori, lokasi, departemen, penerima.</td></tr>
        <tr><td>Draf</td><td>Dokumen yang sudah disimpan tetapi belum dikonfirmasi. Stok belum berubah. Draf boleh diubah dan dihapus.</td></tr>
        <tr><td>Faktor</td><td>Berapa satuan stok dalam 1 satuan masuk. Contoh: 1 dus = 12 pcs, faktornya 12.</td></tr>
        <tr><td>FEFO</td><td>First Expired, First Out. Barang yang paling cepat kedaluwarsa dikeluarkan lebih dulu.</td></tr>
        <tr><td>Hitung buta</td><td>Stok Opname yang menyembunyikan stok sistem dari penghitung, supaya hasil hitungnya objektif.</td></tr>
        <tr><td>Konfirmasi, Terkonfirmasi</td><td>Konfirmasi adalah tombol yang memproses dokumen ke stok. Setelah itu dokumen berstatus Terkonfirmasi dan terkunci.</td></tr>
        <tr><td>Kontan</td><td>Pemasok sistem untuk pembelian tunai atau tanpa pemasok tetap. Tidak bisa dinonaktifkan, diganti nama, atau dihapus.</td></tr>
        <tr><td>Kunci transaksi</td><td>Pengaturan Stok Opname. Selama opname berjalan, Konfirmasi dan Setujui transaksi stok ditolak.</td></tr>
        <tr><td>Kop</td><td>Bagian paling atas kertas cetakan yang memuat nama perusahaan, logo, dan alamat. Diatur di menu Perusahaan.</td></tr>
        <tr><td>Lead time</td><td>Lama dari barang dipesan sampai barang datang, dalam hari.</td></tr>
        <tr><td>Lembar Hitung</td><td>Kertas cetak untuk dibawa menghitung barang di gudang saat Stok Opname.</td></tr>
        <tr><td>Penerimaan</td><td>Dokumen yang mencatat barang datang dari pemasok. Stok bertambah.</td></tr>
        <tr><td>Pengeluaran</td><td>Dokumen yang mencatat barang keluar ke penerima. Stok berkurang.</td></tr>
        <tr><td>Penyesuaian</td><td>Dokumen yang mengoreksi stok agar sama dengan hitungan fisik. Harus disetujui Supervisor. Stoku juga membuatnya otomatis dari Stok Opname dan dari pembatalan dokumen.</td></tr>
        <tr><td>Pergerakan stok</td><td>Satu catatan stok masuk, keluar, atau disesuaikan. Riwayatnya ada di Kartu Stok.</td></tr>
        <tr><td>PO</td><td>Surat pesanan resmi ke pemasok. Catat Pemesanan di Stoku bukan PO.</td></tr>
        <tr><td>Kode aset, kode ruang</td><td>Kode aset adalah kode permanen sebuah AC (mis. AC-0004) yang ditempel sebagai stiker dan tidak berubah. Kode ruang milik ruangnya (mis. MTG); AC ditulis MTG · AC-0004. Dipindah ke ruang lain, kode ruangnya berubah, kode asetnya tetap.</td></tr>
        <tr><td>Odometer</td><td>Kilometer yang tertera di panel kendaraan. Hanya naik, tidak boleh lebih kecil dari catatan terakhir.</td></tr>
        <tr><td>PIC</td><td>Penanggung jawab sebuah ruang AC. Menerima salinan pemberitahuan kunjungan dan menandatangani Berita Acara.</td></tr>
        <tr><td>Rencana Perawatan</td><td>Daftar pekerjaan perawatan sebuah Jenis Kendaraan beserta intervalnya (km, bulan, atau keduanya; yang tercapai lebih dulu menentukan).</td></tr>
        <tr><td>RO dan RW</td><td>Hak akses grup. RO (hanya baca) boleh membuka layar. RW (baca dan tulis) boleh juga menyimpan.</td></tr>
        <tr><td>Saldo Awal</td><td>Dokumen yang memasukkan stok yang sudah ada saat mulai memakai Stoku. Hanya Supervisor, hanya untuk barang yang belum punya pergerakan stok. Tidak wajib.</td></tr>
        <tr><td>Pesanan terbuka</td><td>Catatan pesanan yang jumlahnya belum terpenuhi dan belum ditutup. Statusnya Dipesan atau Diterima sebagian.</td></tr>
        <tr><td>Servis besar</td><td>Perawatan AC yang lebih menyeluruh (bawaan tiap 12 bulan). Sudah mencakup cuci rutin, jadi menggeser jatuh tempo cuci juga.</td></tr>
        <tr><td>Satuan masuk</td><td>Kemasan barang saat diterima, misalnya dus berisi 12 pcs. Dikonversi ke satuan stok di Penerimaan Barang.</td></tr>
        <tr><td>Satuan stok</td><td>Satuan yang dipakai untuk menyimpan stok barang, misalnya pcs. Pengeluaran dan Penyesuaian selalu memakainya.</td></tr>
        <tr><td>Selisih</td><td>Hasil hitung fisik dikurangi stok di sistem. Plus berarti lebih. Minus berarti kurang.</td></tr>
        <tr><td>Stok Opname</td><td>Hitung fisik rutin banyak barang sekaligus. Selisihnya menjadi satu Penyesuaian otomatis setelah Supervisor menyetujui.</td></tr>
        <tr><td>Stok sistem</td><td>Stok menurut catatan Stoku, bukan hasil hitungan di rak. Stok Opname membandingkan keduanya.</td></tr>
        <tr><td>Stok tersedia</td><td>Jumlah semua batch yang belum kedaluwarsa. Dipakai untuk memeriksa Titik Pesan Ulang.</td></tr>
        <tr><td>Titik Pesan Ulang</td><td>Batas stok per barang. Stok tersedia sama dengan atau di bawah batas ini membuat Beranda memberi peringatan. Angka 0 berarti tanpa peringatan.</td></tr>
    </tbody>
</table>
