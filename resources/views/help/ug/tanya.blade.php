{{-- REMARK
 Tujuan       : Panduan Pengguna bagian 12 Pertanyaan umum dan masalah umum: masalah yang sering ditanyakan beserta jawabannya (laporan kosong, Saldo Awal ditolak, keluar otomatis, stok tidak cukup, barang tidak muncul, transaksi terkunci, tombol plus, dan lain-lain).
 Sumber hulu  : pesan galat dan aturan di StockLedger, StockDocuments, StockCounts, EnsureModuleAccess, EnsureActive, ProductUnits, ItemLookups, QuickAdd, Decimal.
 Dipakai oleh : help/user-guide.blade.php (semua peran).
 Status       : Aktif.
--}}
<h2 id="tanya">12. Pertanyaan umum</h2>
<table class="g-table">
    <thead><tr><th>Masalah</th><th>Jawaban</th></tr></thead>
    <tbody>
        <tr><td>Laporan kosong padahal saya sudah mencatat</td><td>Dokumennya mungkin masih <b>Draf</b>. Laporan stok hanya menghitung dokumen yang sudah <b>Terkonfirmasi</b>. Buka dokumennya dan tekan <b>Konfirmasi</b>. Periksa juga <b>Dari tanggal</b> dan <b>Sampai tanggal</b>. Bawaannya awal bulan ini sampai hari ini.</td></tr>
        <tr><td>Stok tidak berkurang atau bertambah setelah Simpan</td><td>Simpan hanya membuat Draf. Tekan <b>Konfirmasi</b> agar diproses ke stok. Penyesuaian Stok masih perlu <b>Setujui</b> dari Supervisor.</td></tr>
        <tr><td>Saldo Awal ditolak: barang sudah punya pergerakan stok</td><td>Saldo Awal hanya untuk barang yang belum pernah masuk, keluar, atau disesuaikan. Untuk barang itu pakai <b>Penyesuaian Stok</b>. Saldo Awal juga tidak wajib. Barang baru cukup dicatat lewat Penerimaan Barang.</td></tr>
        <tr><td>Kartu Stok tidak punya baris AWAL, tetapi Saldo Awal di atas tidak 0</td><td>Dari tanggal Anda lebih lambat dari tanggal Saldo Awal. Jumlahnya sudah masuk ke kotak Saldo Awal di atas kartu. Atur Dari tanggal lebih awal untuk melihat baris AWAL.</td></tr>
        <tr><td>Tiba-tiba keluar ke halaman masuk</td><td>Tidak ada aktivitas 30 menit, jadi AdminKU keluar otomatis. Atau akun Anda dinonaktifkan. Masuk lagi. Isian yang belum disimpan tidak tersimpan otomatis. Dua menit sebelum keluar ada peringatan dengan tombol <b>Tetap masuk</b>.</td></tr>
        <tr><td>Pengeluaran ditolak: stok tidak cukup</td><td>Jumlah melebihi stok. Periksa kolom <b>Tersedia</b>. Stok fisik memang ada? Catat dulu Penerimaan Barang, atau minta Supervisor membuat Penyesuaian Stok.</td></tr>
        <tr><td>Pembatalan ditolak: stok tidak cukup</td><td>Dokumen itu tidak bisa ditarik kembali karena akan membuat stok minus. Koreksi lewat Penyesuaian Stok.</td></tr>
        <tr><td>Barang tidak muncul di pilihan dokumen</td><td>Barangnya nonaktif atau belum ada. Aktifkan lewat grid Barang (Supervisor). Di Pengeluaran Barang, barang juga tidak muncul bila stok batchnya 0 atau batchnya sudah kedaluwarsa.</td></tr>
        <tr><td>Pemasok, Penerima, atau Departemen tidak ada di pilihan</td><td>Datanya nonaktif atau belum ada. Tekan tombol <b>+</b> di samping pilihan untuk menambah dan langsung memilihnya. Tombol itu hanya tampil bila Anda boleh mengelola datanya.</td></tr>
        <tr><td>Bagaimana memakai tombol plus?</td><td>Tekan <b>+</b> di samping pilihan, isi kotak kecil, lalu tekan <b>Simpan</b>. Datanya masuk ke master dan langsung terpilih. Lihat <a href="#tombol-plus">Tombol plus</a>.</td></tr>
        <tr><td>Penerimaan atau Pengeluaran ditolak: &quot;Stok Opname ... sedang berjalan, transaksi dikunci&quot;</td><td>Ada Stok Opname yang berjalan dengan kunci transaksi. Draf boleh tetap disimpan. Tunggu opname selesai, atau minta Supervisor menekan <b>Buka Kunci</b>.</td></tr>
        <tr><td>Selesai Hitung ditolak</td><td>Masih ada baris yang kosong, atau Tidak dihitung tanpa catatan. Pesan menyebut kode barangnya.</td></tr>
        <tr><td>Setujui Opname ditolak: stok bergeser sejak cut-off</td><td>Ada transaksi stok sesudah titik acuan. Supervisor menekan <b>Perbarui Baris Bergeser</b> (semua baris yang bergeser) atau <b>Perbarui &lt;kode barang&gt;</b> (satu baris saja), lalu baris itu dihitung ulang.</td></tr>
        <tr><td>Muncul pesan &quot;Akses Anda ke modul ini hanya-baca (RO)&quot;</td><td>Grup Anda hanya boleh membuka modul itu tanpa mengubah. Minta Administrator memberi RW.</td></tr>
        <tr><td>Menu atau tombol tidak ada</td><td>Peran atau Grup Anda belum diberi akses. Contoh: mengubah Barang hanya untuk Supervisor. Hubungi Administrator.</td></tr>
        <tr><td>Kode barang tidak bisa diubah</td><td>Memang terkunci supaya riwayat tidak rusak. Bila salah, nonaktifkan barangnya lalu buat barang baru.</td></tr>
        <tr><td>Jendela &quot;Faktor satuan belum ada&quot; muncul</td><td>Satuan yang Anda pilih di Penerimaan belum punya faktor untuk barang itu. Isi faktornya di jendela, tekan <b>Simpan Faktor</b>, lalu Simpan atau Konfirmasi lagi.</td></tr>
        <tr><td>Muncul pesan &quot;Faktor ini sudah dipakai di dokumen&quot;</td><td>Faktor Satuan masuk yang sudah dipakai di dokumen Terkonfirmasi hanya bisa diubah Supervisor. Hubungi Supervisor.</td></tr>
        <tr><td>Barang berstatus PESAN ULANG padahal stoknya ada</td><td>Stok yang sudah kedaluwarsa tidak dihitung sebagai stok tersedia. Periksa kolom <b>Stok Tersedia</b> di grid Barang.</td></tr>
        <tr><td>Catat Pemesanan tidak muncul</td><td>Tidak ada barang yang menipis, atau semua barang yang menipis sudah punya pesanan terbuka. Lihat <a href="#catat-pemesanan">Catat Pemesanan</a>.</td></tr>
        <tr><td>Nama atau logo di kop cetakan salah atau masih AdminKU</td><td>Supervisor mengisi dan menyimpan data di <b>Data Master</b> &gt; <b>Perusahaan</b>. Logo harus PNG, JPG, atau WebP, paling besar 1 MB. Belum diisi? Kop memakai nama bawaan AdminKU.</td></tr>
        <tr><td>Pesanan yang sudah selesai hilang dari Beranda, di mana melihatnya?</td><td>Di menu <b>Transaksi</b> &gt; <b>Riwayat Pemesanan</b>. Daftar itu memuat pesanan Dipesan, Diterima sebagian, Selesai, dan Ditutup manual. @if ($can('ORD'))Lihat <a href="#t-ord">Riwayat Pemesanan</a>.@endif</td></tr>
        <tr><td>Nilai persediaan dan harga rata-rata</td><td>Dihitung presisi (bukan angka pecahan komputer) dan dibulatkan sekali di akhir, sehingga jumlah di laporan konsisten dengan Kartu Stok.</td></tr>
        <tr><td>Takut menyimpan dua kali</td><td>Tombol hanya bereaksi sekali. Data tidak akan ganda.</td></tr>
        <tr><td>Segarkan Data Contoh tidak ada</td><td>Fitur itu mati bawaannya dan hanya untuk Administrator di mesin uji. Lihat bagian Data Contoh di Keamanan.</td></tr>
    </tbody>
</table>
