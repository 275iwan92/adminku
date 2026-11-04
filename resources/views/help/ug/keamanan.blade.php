{{-- REMARK
 Tujuan       : Panduan Pengguna bagian 10 Keamanan (hanya Administrator): Pengguna, Grup (hak akses RO / RW per modul), Daftar Modul, Data Contoh (Segarkan data contoh, hanya bila diaktifkan).
 Sumber hulu  : UserController / UserRequest, GroupController / groups/form, ModuleController, SampleDataController / SampleData / config sstock.sample_refresh, EnsureActive.
 Dipakai oleh : help/user-guide.blade.php (hanya bila pengguna punya salah satu modul Keamanan).
 Status       : Aktif.
--}}
@if ($anySec)
<h2 id="keamanan">10. Keamanan <span class="g-role">hanya Administrator</span></h2>
<p>Menu Keamanan menempel di bawah panel kiri. Dipakai untuk mengatur siapa boleh apa.</p>

@if ($can('USR'))
<h3 id="sec-usr">Pengguna</h3>
<h4>Untuk apa</h4>
<p>Membuat akun untuk orang yang memakai AdminKU, menentukan perannya, dan mencabut aksesnya bila perlu.</p>
<h4>Siapa yang bisa</h4>
<p>Administrator.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>Pengguna</b>, tekan <b>+ Data Baru</b>.</li>
    <li>Isi <b>Nama Lengkap</b>, <b>Username</b>, dan <b>Email</b>. Username memakai huruf kecil, angka, titik, garis bawah, atau strip. Username tidak bisa diubah setelah disimpan.</li>
    <li>Pilih <b>Peran</b> (Data Entry, Admin Data, Supervisor, atau Administrator; Admin Data membuka layar seperti Supervisor tetapi tidak bisa menyetujui) dan <b>Grup</b> (boleh kosong).</li>
    <li>Isi <b>Password</b>, minimal 8 karakter. Saat mengubah pengguna lama, kosongkan password bila tidak diganti.</li>
    <li>Tekan <b>Simpan</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Tekan <b>Nonaktifkan</b> untuk mencabut akses seseorang tanpa menghapus riwayatnya. Pengguna itu langsung keluar pada klik berikutnya dan tidak bisa masuk lagi.</li>
    <li>Demi keamanan, Anda tidak bisa menonaktifkan atau mengubah peran akun Anda sendiri.</li>
    <li>Administrator aktif yang terakhir tidak bisa dinonaktifkan.</li>
    <li>Pengguna tanpa Grup memakai modul bawaan perannya.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Username atau email ditolak.</b> Sudah dipakai akun lain.</li>
    <li><b>Orang lupa password.</b> Ia bisa memakai <b>Lupa password?</b> di halaman masuk. E-mail terkirim hanya bila pengaturan e-mail server sudah benar.</li>
</ul>
@endif

@if ($can('GRP'))
<h3 id="sec-grp">Grup dan hak akses</h3>
<h4>Untuk apa</h4>
<p>Grup menentukan modul mana yang boleh dibuka anggotanya. Ada tiga tingkat hak akses per modul: <b>Tidak ada</b>, <b>Hanya baca (RO)</b>, dan <b>Baca &amp; tulis (RW)</b>. RO boleh membuka layar tetapi tidak boleh menyimpan atau mengubah.</p>
<h4>Siapa yang bisa</h4>
<p>Administrator.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>Grup</b>, tekan <b>+ Data Baru</b>. Isi <b>Kode Grup</b> dan <b>Nama Grup</b>.</li>
    <li>Pada tabel <b>Akses per modul</b>, pilih hak akses tiap modul. Kolom <b>Peran Minimal</b> menunjukkan peran terendah yang masih bisa memakai modul itu. Lalu tekan <b>Simpan</b>.</li>
    <li>Di form <b>Pengguna</b>, pilih Grup untuk orang yang bersangkutan.</li>
    <li>Grup tidak dihapus. Pakai <b>Nonaktifkan</b> atau <b>Aktifkan</b> (tombol di form). Grup nonaktif tidak muncul di pilihan Grup untuk pengguna. Anggota yang sudah ada tidak berubah.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<div class="g-tip">Akses nyata = modul diberikan Grup <b>dan</b> peran pengguna cukup tinggi. Modul khusus Supervisor tetap tertutup bagi Data Entry walau diberi RW. Administrator selalu punya akses penuh.</div>
<ul class="g-list">
    <li>Pengguna tanpa Grup memakai modul bawaan perannya, dengan hak baca dan tulis.</li>
    <li>Modul dengan hak RO tetap tampil di menu. Saat menyimpan, AdminKU menolak dengan pesan hak akses hanya-baca.</li>
    <li>Tombol plus di pilihan dokumen hanya tampil bila pengguna punya hak RW di modul masternya.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Pengguna melapor menu tidak ada.</b> Periksa Grup dan perannya. Modul khusus Supervisor tidak muncul untuk Data Entry.</li>
    <li><b>Pengguna melapor pesan akses hanya-baca (RO).</b> Ubah hak aksesnya menjadi RW di Grup.</li>
</ul>
@endif

@if ($can('MOD'))
<h3 id="sec-mod">Daftar Modul</h3>
<h4>Untuk apa</h4>
<p>Daftar baca-saja semua modul aplikasi, peran minimal, dan grup yang boleh membukanya. Tidak ada yang bisa diubah di sini.</p>
<h4>Siapa yang bisa</h4>
<p>Administrator.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>Daftar Modul</b>.</li>
    <li>Baca kolom <b>Peran Minimal</b> dan <b>Grup yang Punya Akses</b>.</li>
    <li>Ingin mengubah akses? Lakukan di <b>Grup</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Setiap baris menunjukkan nama modul dengan kodenya kecil di bawahnya (mis. ITM untuk Barang), kelompok menu di kolom <b>Menu</b>, <b>Peran Minimal</b>, dan <b>Grup yang Punya Akses</b>.</li>
    <li>Kotak <b>Cari</b> mencari kode atau nama modul.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Grup tidak muncul di kolom Grup yang Punya Akses.</b> Grup itu belum diberi akses ke modul tersebut. Atur di <b>Grup</b>.</li>
</ul>
@endif

@if ($can('SMP'))
<h3 id="sec-smp">Data Contoh</h3>
<h4>Untuk apa</h4>
<p>Menyegarkan seluruh data dengan data contoh, untuk percobaan atau pelatihan. Layar menampilkan isi database saat ini dan apa yang akan terjadi.</p>
<h4>Siapa yang bisa</h4>
<p>Administrator, dan hanya bila fitur ini diaktifkan di pengaturan server (<code>SSTOCK_SAMPLE_REFRESH=true</code>). Fitur ini mati bawaannya di server sungguhan. Bila mati, layar menampilkan pesan peringatan dan tidak ada tombol Segarkan.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>Data Contoh</b> dan baca daftar yang akan dihapus dan dibuat.</li>
    <li>Ketik <b>SEGARKAN</b> (huruf besar) pada kotak konfirmasi.</li>
    <li>Tekan <b>Segarkan Data Contoh</b>.</li>
    <li>Anda keluar otomatis. Masuk lagi dengan akun contoh (<code>admin</code>, <code>spv</code>, <code>adm</code>).</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<div class="g-warn"><b>Seluruh data saat ini hilang</b> (barang, transaksi, pemasok, akun, grup) dan tidak bisa dikembalikan. Data wilayah dan gambar latar login dipertahankan. Jangan pakai di server yang berisi data sungguhan.</div>
<p><b>Reset data contoh HR</b> (di layar yang sama, tidak perlu fitur segarkan aktif): mengembalikan karyawan dan kandidat contoh (David, Detta, Maya Denada, Dedi Sandiaga, dengan foto dan KTP tiruan) ke keadaan snapshot dan membuang karyawan atau kandidat contoh tambahan; data nyata tidak disentuh dan Anda tidak keluar. Ketik <b>RESET</b>, lalu tekan <b>Reset Data Contoh HR</b>. Setara dengan <code>php artisan sample:hr --reset</code>.</p>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Tombol Segarkan tidak ada.</b> Fitur dimatikan di lingkungan ini.</li>
    <li><b>Tombol ditolak.</b> Ketik SEGARKAN dengan huruf besar semua.</li>
</ul>
@endif
@endif
