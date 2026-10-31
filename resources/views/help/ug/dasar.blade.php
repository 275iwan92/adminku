{{-- REMARK
 Tujuan       : Panduan Pengguna bagian 3 (Masuk, keluar otomatis, Beranda, Catat Pemesanan, Menu), 4 (Cara memakai Grid) dan 5 (Cara memakai Form: tombol umum, tombol plus, baris detail, siklus dokumen).
 Sumber hulu  : AuthController, EnsureActive, x-dl::idle-logout (layout), HomeController dan partials/reorder-alert, count-reminder, expiry-alert, ReorderOrderController, ReorderOrders, LeadTimes, QuickAdd, StockDocuments.
 Dipakai oleh : help/user-guide.blade.php.
 Status       : Aktif.
--}}
<h2 id="masuk">3. Masuk, Beranda, dan Menu</h2>
<h3>Masuk</h3>
<ol class="g-steps">
    <li>Buka alamat Stoku di browser.</li>
    <li>Isi <b>username atau e-mail</b> dan <b>password</b>, lalu tekan tombol masuk.</li>
    <li>Salah password 5 kali berturut-turut? Percobaan berikutnya ditahan beberapa detik. Tunggu sampai hitungan selesai, lalu coba lagi.</li>
    <li>Lupa password? Tekan <b>Lupa password?</b>, isi username atau e-mail, lalu tekan <b>Kirim tautan reset</b>. Tautan dikirim ke e-mail Anda. Tautan berlaku 60 menit dan hanya bisa dipakai sekali. Pilih password baru (minimal 8 karakter), lalu tekan <b>Simpan password baru</b>.</li>
</ol>
@include('help.diagram', ['name' => 'ug-masuk', 'caption' => 'Masuk, lupa password, dan membuka menu.'])

<h3 id="keluar-otomatis">Keluar otomatis</h3>
<p class="g-note">Tidak ada pilihan &quot;ingat saya&quot;. Stoku tidak mengingat Anda di komputer ini. Bila sesi habis, Anda masuk lagi.</p>
<ul class="g-list">
    <li>Tidak ada aktivitas selama <b>30 menit</b>? Stoku keluar otomatis. Angka 30 menit adalah pengaturan bawaan. Administrator bisa mengubahnya.</li>
    <li>Dua menit sebelumnya muncul peringatan <b>Sesi hampir berakhir</b> di pojok kanan bawah, dengan hitung mundur.</li>
    <li>Tekan <b>Tetap masuk</b> untuk melanjutkan. Tekan <b>Keluar sekarang</b> bila ingin keluar.</li>
    <li>Mengetik, menggerakkan mouse, menggulir, dan menyentuh layar dihitung sebagai aktivitas.</li>
    <li>Isian yang belum disimpan tidak tersimpan otomatis. Simpan pekerjaan Anda sebelum meninggalkan komputer.</li>
    <li>Akun yang dinonaktifkan tidak bisa masuk. Bila akun dinonaktifkan saat Anda memakai Stoku, Anda langsung keluar pada klik berikutnya.</li>
</ul>
@include('help.diagram', ['name' => 'ug-keluar-otomatis', 'caption' => 'Keluar otomatis setelah 30 menit tanpa aktivitas, dengan peringatan 2 menit sebelumnya.'])

<h3 id="beranda">Beranda</h3>
<p>Setelah masuk Anda melihat <b>Beranda</b>. Beranda memberi peringatan supaya Anda tahu apa yang perlu dikerjakan.</p>
<ul class="g-list">
    <li><b>Sapaan.</b> Satu baris di paling atas, misalnya &quot;Selamat siang, Budi&quot; (nama depan Anda), dengan tanggal dan jam kecil di bawahnya. Di HP tanggalnya ditulis singkat.</li>
    <li><b>Cuaca.</b> Kartu kecil di kanan sapaan (di HP berupa label kecil di samping tanggal), misalnya &quot;Gerimis · Malang&quot;. Cuaca mengikuti <b>posisi perangkat Anda</b>: saat Beranda dibuka, browser bisa meminta izin lokasi, tekan <b>Izinkan</b>. Bila Anda menolak, bila lokasi tidak bisa dipakai, atau bila Stoku dibuka lewat alamat tanpa HTTPS, cuaca memakai kota yang diisi Supervisor di menu <b>Perusahaan</b>. Bila itu juga kosong, kartu cuaca tidak tampil dan Beranda tetap rapi. Kartu ini mengambil data dari internet lewat browser Anda (lihat catatan di bawah).</li>
    <li><b>Kata motivasi</b> di bagian atas.</li>
    @if ($can('SO'))
        <li><b>Pengingat Stok Opname.</b> Kalimatnya &quot;Stok Opname terakhir ... Berikutnya ...&quot;. Warna biasa berarti jadwalnya masih jauh. Kuning berarti jadwalnya tinggal 3 hari atau kurang, sampai hari jadwalnya. Merah berarti terlambat. Bila ada opname yang sedang berjalan, tertulis &quot;Stok Opname SO-... sedang berjalan (terisi N dari M)&quot;. Tombol <b>Buka Stok Opname</b> membuka menunya. Tombol <b>Mulai Opname</b> hanya untuk Supervisor.</li>
    @endif
    <li><b>Kotak ringkasan.</b> Setiap kotak bisa diklik dan membuka menu terkait.
        <ul class="g-list">
            <li><b>Barang aktif</b> dan <b>Di / bawah titik pesan ulang</b> membuka Barang.</li>
            <li><b>Hampir kedaluwarsa / kedaluwarsa</b> membuka Laporan Kedaluwarsa.</li>
            <li><b>Penerimaan hari ini</b> dan <b>Pengeluaran hari ini</b> menghitung dokumen yang sudah Terkonfirmasi hari ini.</li>
            <li><span class="g-role">hanya Supervisor</span> <b>Nilai stok</b> dan <b>Dokumen menunggu persetujuan</b>.</li>
        </ul>
    </li>
    <li><b>Peringatan titik pesan ulang.</b> Daftar barang yang <b>stok tersedia</b>-nya sama dengan atau di bawah <b>Titik Pesan Ulang</b>. Stok tersedia adalah jumlah semua batch yang belum kedaluwarsa. Kolom <b>Status Pesanan</b> menunjukkan apakah barang sudah Anda pesan. Cara mencatatnya ada di bagian <a href="#catat-pemesanan">Catat Pemesanan</a>.</li>
    <li><b>Peringatan kedaluwarsa.</b> Batch yang masih ada stoknya dan sudah atau hampir kedaluwarsa (kurang dari 30 hari). Yang paling dulu ada di atas. Tombol <b>Laporan Kedaluwarsa</b> membuka laporannya.</li>
    <li><span class="g-role">hanya Supervisor</span> <b>Nilai Stok.</b> Grafik nilai stok per minggu dalam satu bulan. Tombol <b>Bulan lalu</b> dan <b>Bulan depan</b> berpindah bulan.</li>
</ul>

<h4>Catatan cuaca</h4>
<ul class="g-list">
    <li>Browser Anda mengirim posisi perangkat (dibulatkan, kira-kira ketelitian kota) ke layanan cuaca <b>Open-Meteo</b> dan ke layanan nama kota <b>BigDataCloud</b>. Stoku sendiri tidak menyimpan posisi Anda. Hasilnya diingat di browser selama satu jam supaya tidak meminta berulang.</li>
    <li>Di komputer, posisinya berasal dari Wi-Fi atau alamat jaringan, jadi kira-kira tepat sampai tingkat kota, bukan alamat Anda.</li>
    <li>Menolak izin lokasi tidak mengganggu pekerjaan: browser mengingat penolakan itu dan Stoku tidak menanyakannya lagi selama seminggu. Mengubah izin: pengaturan situs di browser.</li>
    <li>Tidak ada internet atau layanan cuaca tidak menjawab: kartu cuaca diam atau tidak tampil. Pekerjaan lain tidak terganggu.</li>
</ul>

<h3 id="versi">Versi Stoku</h3>
<p>Di samping nama <b>STOKU</b> (panel kiri dan halaman masuk) ada tulisan kecil dan pudar, misalnya <b>ver 0.133 A</b>. Angkanya naik sendiri setiap kali Stoku diperbarui. Huruf di belakangnya adalah tahap: <b>A</b> (alpha, masih dibangun dan dicoba tim sendiri), <b>B</b> (beta, uji coba dengan pengguna sungguhan, fitur dibekukan), <b>RC</b> (calon rilis). Setelah rilis resmi huruf hilang dan versinya menjadi seperti <b>ver 1.0.0</b>. Sentuh tulisannya untuk melihat nama tahap lengkap. Sebutkan versi ini saat melapor masalah.</p>

<h3 id="status-koneksi">Baris jaringan di bawah nama Anda</h3>
<p>Di bawah nama Anda (kiri bawah di komputer; di HP pada daftar <b>Menu</b>) ada satu baris kecil dan pudar, misalnya <b>10.238.189.21 &rarr; 10.238.189.159</b>. Angka pertama adalah alamat jaringan perangkat Anda, angka kedua alamat server Stoku. Sentuh atau arahkan kursor ke baris itu untuk melihat nama komputernya. Bila Anda melapor masalah, sebutkan kedua angka ini supaya penyebabnya mudah dicari.</p>
<ul class="g-list">
    <li><b>Normal.</b> Hanya baris angka itu.</li>
    <li><b>&quot;Server tidak terjangkau&quot;.</b> Alamat server berkedip merah muda dan tulisan kecil muncul. Browser Anda tidak mendapat jawaban dari server dua kali berturut-turut. Penyebabnya bisa server mati, kabel atau Wi-Fi putus, atau jaringan kantor bermasalah (browser tidak bisa membedakannya). Tulisan hilang sendiri begitu server menjawab lagi. Menunggu sebentar biasanya cukup; bila lama, hubungi pengelola.</li>
    <li><b>&quot;Perangkat offline&quot;.</b> Alamat perangkat Anda berkedip kuning. Perangkat Anda sendiri tidak punya jaringan (Wi-Fi atau data mati). Nyalakan jaringan, lalu halaman pulih sendiri.</li>
    <li>Pemeriksaan dilakukan ringan setiap 10 detik selama halaman terlihat. Pemeriksaan ini tidak memperpanjang masa masuk Anda (tetap keluar otomatis setelah 30 menit tanpa aktivitas).</li>
    <li>Bila pengaturan &quot;kurangi gerakan&quot; di perangkat Anda menyala, alamat tidak berkedip; hanya warna dan tulisannya yang berubah.</li>
</ul>

<h3 id="catat-pemesanan">Catat Pemesanan</h3>
<h4>Untuk apa</h4>
<p>Mencatat bahwa barang yang stoknya menipis sudah Anda pesan ke supplier. Catatan ini sederhana. Ini bukan dokumen PO (surat pesanan resmi): tidak ada harga, persetujuan, atau cetakan. Fungsinya agar rekan Anda tahu pesanan ke mana, berapa, dan kapan.</p>
<h4>Siapa yang bisa</h4>
<p>Data Entry dan Supervisor. Grup yang diatur Administrator perlu hak <b>Baca &amp; tulis (RW)</b> untuk modul <b>Riwayat Pemesanan</b>. Tanpa hak itu, tombol <b>Catat Pemesanan</b> dan <b>Tutup Pesanan</b> tidak tampil dan Stoku menolak simpanannya.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Pesan barang ke supplier lebih dulu. Pemesanan dilakukan di luar Stoku.</li>
    <li>Di Beranda, tekan <b>Catat Pemesanan</b>.</li>
    <li>Pilih <b>Barang</b>. Daftarnya hanya berisi barang yang stoknya menipis dan belum punya pesanan terbuka.</li>
    <li>Pilih <b>Supplier</b>. Pembelian tunai? Pilih <b>Kontan</b>. Supplier belum ada? Tekan tombol <b>+</b> di samping pilihan (lihat <a href="#tombol-plus">Tombol plus</a>).</li>
    <li>Isi <b>Jumlah Dipesan</b>. Jumlahnya harus lebih dari 0 dan memakai satuan stok barang. Satuannya tampil di bawah isian.</li>
    <li>Isi <b>Tanggal Pesan</b>. Bawaannya hari ini. Tanggal tidak boleh lebih dari hari ini.</li>
    <li><b>No. Referensi Pemesanan</b> dan <b>Catatan</b> boleh kosong.</li>
    <li>Tekan <b>Simpan</b>. Status barang itu di Beranda menjadi <b>Dipesan</b>.</li>
</ol>
@include('help.diagram', ['name' => 'ug-pesan-ulang', 'caption' => 'Dari peringatan titik pesan ulang, Catat Pemesanan, sampai pesanan tertutup.'])
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li><b>Status Pesanan</b> di Beranda punya tiga warna.
        <ul class="g-list">
            <li>Merah <b>Belum dipesan</b>: belum ada catatan pesanan.</li>
            <li>Hijau <b>Dipesan tanggal · nomor referensi</b>: belum ada yang diterima.</li>
            <li>Kuning <b>Diterima sebagian tanggal · nomor referensi</b>: sebagian sudah datang, pesanan masih terbuka.</li>
        </ul>
    </li>
    <li>Di bawah label status ada keterangan. Contohnya: <i>Sudah dipesan 24 pcs ke Kontan pada 05/11/2026, ref PO-12 oleh Sari. Diterima sejak dipesan: 0 dari 24 pcs.</i></li>
    <li>Angka <b>Diterima sejak dipesan</b> adalah jumlah Penerimaan Barang yang sudah Terkonfirmasi untuk barang itu sejak Tanggal Pesan. Penerimaan yang dibatalkan tidak dihitung.</li>
    <li>Pesanan <b>tertutup sendiri</b> bila jumlah yang diterima sudah memenuhi jumlah dipesan. Bila baru sebagian, statusnya <b>Diterima sebagian</b> dan pesanan tetap terbuka. Catatan lama yang tidak punya jumlah tertutup oleh penerimaan pertama.</li>
    <li>Supplier tidak akan mengirim sisanya? Tekan <b>Tutup Pesanan</b> di samping label status. Isi alasan bila perlu, lalu tekan <b>Tutup Pesanan</b> di jendela itu. Pesanan menjadi <b>Ditutup manual</b>. Tidak ada yang dihapus. Pesanan tidak bisa dibuka lagi, tetapi Anda bisa mencatat pesanan baru.</li>
    <li>Pesanan yang sudah tertutup (selesai atau ditutup manual) hilang dari peringatan Beranda. @if ($can('ORD')) Lihat semuanya di <a href="#t-ord">Riwayat Pemesanan</a>.@else Supervisor dan Data Entry yang punya menu <b>Riwayat Pemesanan</b> bisa melihatnya.@endif</li>
    <li><b>Perkiraan tiba.</b> Untuk pesanan yang belum menerima apa pun, keterangan ditambah <i>Perkiraan tiba {tanggal}.</i> Tanggalnya adalah Tanggal Pesan ditambah lead time. Lead time adalah lama dari pesan sampai barang datang.</li>
    <li>Pesanan lebih lama dari biasanya? Keterangan ditambah <i>Sudah {n} hari, biasanya {m}. Periksa pesanan.</i> Setelah ada penerimaan pertama, pesanan tidak ditandai terlambat lagi.</li>
    <li>Dari mana lead time? Stoku menghitung rata-rata sampai 5 pesanan terakhir barang itu, dibulatkan ke atas, dari Tanggal Pesan sampai Penerimaan <b>pertama</b>. Pesanan yang ditutup manual tanpa penerimaan tidak dihitung. Datanya kurang dari 3 pesanan? Stoku memakai isian <b>Lead Time (hari)</b> di form Barang. Keduanya kosong? Tidak ada kalimat tambahan.</li>
    <li>Keterangan yang sama tampil di laporan <b>Ringkasan Stok</b> (kolom <b>Supplier Pesanan</b>, <b>Jumlah Dipesan</b>, <b>Info Pemesanan</b>, dan <b>Lead Time Otomatis (d)</b>) dan di form Barang.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Tombol Catat Pemesanan tidak ada.</b> Tidak ada barang yang menipis, atau semua barang yang menipis sudah punya pesanan terbuka.</li>
    <li><b>Barang tidak ada di daftar.</b> Stoknya belum sampai Titik Pesan Ulang, atau barang itu sudah punya pesanan terbuka.</li>
    <li><b>Pesan &quot;Barang ini tidak berada di bawah titik pesan ulang&quot;.</b> Stok barang berubah sejak Beranda dibuka. Muat ulang Beranda.</li>
    <li><b>Salah catat pesanan.</b> Stoku belum punya tombol untuk mengubah atau menghapus catatan pesanan. Tekan <b>Tutup Pesanan</b>, lalu catat pesanan yang benar.</li>
</ul>

<h3 id="menu">Menu dan tab</h3>
<ul class="g-list">
    <li>Menu ada di panel kiri, dikelompokkan: <b>Data Master</b>, <b>Transaksi</b>, <b>Laporan</b>, dan <b>Keamanan</b> (menempel di bawah). Hanya menu yang boleh Anda buka yang tampil.</li>
    <li>Setiap menu yang Anda pilih terbuka sebagai <b>tab</b> kerja. Anda bisa membuka beberapa tab sekaligus dan berpindah di antaranya tanpa kehilangan isian.</li>
    <li>Klik nama atau foto Anda untuk membuka <b>menu profil</b>: <b>Notifikasi</b>, <b>Edit profil</b>, <b>Bantuan &amp; dukungan</b> (panduan ini), <b>Bahasa</b>, <b>Ubah password</b>, dan <b>Keluar aplikasi</b>.</li>
    <li>Tombol <b>F1</b> di layar mana pun membuka panduan ini.</li>
</ul>

<h3 id="tampilan-hp">Tampilan HP dan Tampilan Desktop</h3>
<p>Stoku punya dua tampilan. <b>Tampilan Desktop</b> untuk komputer (menu di sisi kiri). <b>Tampilan HP</b> untuk ponsel yang dipegang tegak: satu tombol <b>&#8801;</b> melayang di kiri atas dan isi selebar layar, tanpa bilah permanen di atas maupun di bawah.</p>
<ul class="g-list">
    <li><b>Di ponsel</b>, Stoku selalu memakai Tampilan HP, mulai dari halaman Masuk (isian dan tombol besar), dan tidak ada tombol untuk pindah ke Tampilan Desktop. Tampilan mengikuti perangkat: ponsel memakai Tampilan HP, komputer memakai Tampilan Desktop. Untuk berganti, keluar lalu masuk dari perangkat lain.</li>
    <li><b>Di komputer atau tablet dengan layar sempit</b> (lebih sempit dari 600 px, atau HP yang diputar mendatar), halaman Masuk dan Beranda <b>beralih sendiri</b> ke Tampilan HP, juga bila Anda baru mempersempit jendela. Bila jendela dilebarkan lagi, Stoku <b>kembali sendiri</b> ke Tampilan Desktop. Peralihan otomatis ini hanya berlaku selama Anda belum memilih tampilan sendiri. Bila peralihan otomatis tidak berjalan, muncul pertanyaan <b>Ingin tampilan yang lebih nyaman di smartphone?</b>: <b>Ketuk di sini</b> beralih ke Tampilan HP, <b>Tetap desktop</b> menutupnya dan pilihan itu diingat.</li>
    <li><b>Memilih sendiri (hanya di komputer):</b> di Tampilan Desktop buka menu profil dan pilih <b>Tampilan HP</b>; jalan kembali ke Tampilan Desktop ada di ketukan <b>&#8801;</b>, lalu <b>Menu</b>. Pilihan yang Anda buat sendiri diingat di perangkat itu dan <b>tidak diubah otomatis</b>.</li>
    <li>Tablet dan ponsel yang meminta &quot;situs desktop&quot; di peramban dianggap komputer.</li>
</ul>

<h3 id="hp-menu">Tombol &#8801; dan bilah menu di HP</h3>
<p>Ketuk tombol <b>&#8801;</b> (kotak membulat di kiri atas): tombolnya hilang dan <b>bilah di atas layar</b> muncul menggantikannya.</p>
<ul class="g-list">
    <li><b>Layar yang punya aksi</b> menampilkan aksi layar itu: formulir (<b>Simpan</b> berwarna biru, <b>Urungkan</b>, <b>Kembali</b>, <b>Baru</b>), daftar (<b>Cari</b>, <b>Data Baru</b>, <b>Saring</b>, <b>Ekspor</b>; Saring dan Ekspor membuka panel dari atas, kolom diatur dari panel Saring), laporan (<b>Cetak</b>, <b>Kertas</b>, <b>Grup</b>, <b>Hemat tinta</b>).</li>
    <li><b>Layar tanpa aksi</b> (mis. Beranda) langsung menampilkan <b>lima menu utama</b>: <b>Beranda</b>, <b>Stok</b>, <b>Transaksi</b>, <b>Opname</b>, dan <b>Menu</b> paling kanan. <b>Menu</b> membuka daftar semua menu yang boleh Anda buka, ditambah Keluar. Di jendela komputer yang dipersempit, ada juga <b>Tampilan Desktop</b> untuk kembali; di ponsel tombol itu tidak ada.</li>
    <li>Bilah menutup sendiri setelah Anda memilih, menggulir, mengetuk di luar, atau diam beberapa detik. Pengecualian: bilah aksi formulir tidak menutup selama isian belum disimpan. Titik kuning di tombol menandai isian yang belum disimpan, dan pindah layar saat itu meminta konfirmasi.</li>
    <li>Layar anak punya tombol kembali di baris judul.</li>
</ul>

<h3 id="hp-layar">Daftar, formulir, dan laporan di HP</h3>
<p>Semua layar sudah disesuaikan untuk layar kecil: tidak ada tabel yang harus digeser ke samping untuk membaca atau mengisi.</p>
<ul class="g-list">
    <li><b>Daftar</b> (Barang, Penerimaan, Pengeluaran, Saldo Awal, Penyesuaian, Riwayat Pemesanan, Stok Opname, Supplier, Satuan, Kategori, Lokasi, Departemen, Penerima, Pengguna, Grup, Modul) tampil sebagai <b>kartu</b>: judul, lencana status, dan beberapa isian penting. Ketuk kartu untuk membukanya. Cari, Saring, Kolom, dan Ekspor ada di bilah atas.</li>
    <li><b>Formulir data master</b> (Barang, Supplier, Satuan, Kategori, Lokasi, Departemen, Penerima, Perusahaan, Pengguna, Grup) berupa <b>satu kolom</b>: nama isian di atas isiannya, huruf isian besar, pilihan dropdown dan pilihan radio mudah diketuk, pesan salah terbaca jelas. Simpan, Urungkan, dan Kembali ada di bilah atas. Tabel kecil di formulir Barang (Batch dan Satuan masuk) tampil sebagai kartu. Lupa password dan Atur ulang password memakai tampilan masuk HP.</li>
    <li><b>Formulir dokumen</b> (Penerimaan, Pengeluaran, Saldo Awal, Penyesuaian): setiap barang berupa <b>satu kartu</b>. Nomor baris dan tombol Simpan / Urungkan baris ada di atas kartu; Barang selebar layar; isian pendek (Tanggal Kedaluwarsa, Jml, Satuan, Harga) berdampingan dua atau tiga per baris; Catatan Baris di bawahnya. Baris jumlah di bawah tabel disembunyikan di HP; ringkasan total tetap tampil.</li>
    <li><b>Laporan</b> (Ringkasan Stok, Kartu Stok, Kedaluwarsa, Penerimaan, Pengeluaran, Opname &amp; Penyesuaian) tampil sebagai <b>daftar kartu yang terbaca</b>: kotak kriteria dan ringkasan di atas, lalu satu kartu per baris (kolom pertama sebagai judul, kolom lain berpasangan label dan nilai), dikelompokkan bila Anda memilih <b>Grup</b>; barang kedaluwarsa bermerah. Tombol <b>Cetak</b> tetap mencetak lembar kertas yang sama dengan di komputer.</li>
    <li><b>Layar yang dibuat khusus untuk HP:</b> Beranda (kartu ringkasan), <b>Cari Barang</b> (bagian berikut), dan <b>Hitung Stok Opname</b> satu barang per kartu (lihat bagian Stok Opname).</li>
    <li>Layar yang belum punya tampilan HP sendiri dibuka dengan tampilan komputernya di dalam kerangka HP; tabel lebar di dalamnya bisa digeser ke samping.</li>
</ul>

<h3 id="hp-cari-barang">Cari Barang (khusus HP)</h3>
<p>Bagi yang boleh membuka Barang: ketuk <b>Cari Barang</b> di Beranda atau di daftar <b>Menu</b>. Satu kolom besar di atas menerima ketikan atau hasil pemindai barcode (Bluetooth atau USB, yang bertingkah seperti keyboard lalu Enter). Kolom sudah aktif saat layar terbuka dan terpilih seluruhnya setiap kali Anda mengetuknya, jadi pindai berikutnya menimpa yang lama.</p>
<ul class="g-list">
    <li>Cari menurut <b>kode barang</b>, <b>kode batch</b>, <b>nama</b>, atau <b>kode lokasi</b>. Kode barang atau kode batch yang lengkap langsung membuka barangnya (batch yang dipindai diberi bingkai biru).</li>
    <li>Selain itu muncul kartu hasil: nama, kode, lokasi, lencana <b>AMAN</b> atau <b>PESAN ULANG</b>, dan stok tersedia.</li>
    <li>Ketuk kartu untuk melihat stok <b>per batch</b>, kedaluwarsa paling awal di atas (urutan FEFO), lengkap dengan tanggal kedaluwarsa, sisa hari, dan lencana <b>HAMPIR</b> atau <b>KEDALUWARSA</b>. Nilai stok dalam rupiah hanya tampil bagi Supervisor.</li>
</ul>

<h3 id="hp-batas">Batasan Tampilan HP saat ini</h3>
<ul class="g-list">
    <li><b>Kamera belum ada.</b> Pemindaian memakai alat pemindai barcode (Bluetooth / USB). Kamera dan pemasangan Stoku sebagai aplikasi di layar utama butuh alamat <b>https</b>.</li>
    <li><b>Hitung Stok Opname</b> menyimpan isian yang belum terkirim di HP dan mengirimnya lagi bila sinyal kembali, tetapi belum ada mode offline penuh.</li>
    <li>Formulir <b>Grup</b> menampilkan 24 modul sebagai kartu yang panjang (satu per modul); tetap bisa dipakai, hanya perlu menggulir cukup jauh.</li>
    <li>Tampilan HP baru diuji di emulasi browser pada lebar 360, 393, dan 430 px. Bila ada layar yang terpotong atau tombol yang sulit diketuk di HP Anda, buka halaman <b>/ukur</b> (bagian berikut), salin hasilnya, dan kirim bersama nama layar yang bermasalah.</li>
</ul>

<h3 id="ukur-layar">Mengukur layar HP</h3>
<p>Halaman <b>/ukur</b> (mis. <code>http://alamat-server/ukur</code>, tanpa masuk) menampilkan ukuran layar yang sebenarnya dilihat browser: lebar x tinggi, kepadatan piksel, arah layar, area aman, dan tampilan yang dipakai (HP atau Desktop). Kegunaannya untuk menyetel tampilan HP. Buka di HP yang mau diukur, ketuk <b>Salin hasil</b>, lalu tempel ke chat. Bila tombol tidak bisa menyalin (alamat tanpa https), teks hasil tampil di bawah dan sudah terpilih: tekan lama lalu pilih Salin. Tidak ada yang dikirim atau disimpan di server. Angkanya ikut berubah bila layar diputar, keyboard muncul, atau layar dizoom.</p>

<h2 id="grid">4. Cara memakai Grid</h2>
<p><b>Grid</b> adalah daftar data: barang, supplier, dokumen, dan seterusnya. Semua grid bekerja dengan cara yang sama.</p>
<table class="g-table">
    <thead><tr><th>Bagian</th><th>Fungsi</th></tr></thead>
    <tbody>
        <tr><td>Kotak hitung di atas (mis. <b>Semua barang</b>, <b>Pesan ulang</b>, <b>Nonaktif</b>)</td><td>Menunjukkan jumlah data tiap kelompok. Klik satu kotak untuk menyaring grid. Klik <b>Semua ...</b> untuk kembali.</td></tr>
        <tr><td><b>Cari</b></td><td>Ketik sebagian kata, grid langsung menyempit. Tulisan di kotak (mis. &quot;Cari kode, nama, lokasi...&quot;) menyebut kolom yang dicari.</td></tr>
        <tr><td><span class="g-btn">Saring</span></td><td>Membuka pilihan saringan (mis. Kategori, Satuan). Angka di tombol adalah jumlah saringan aktif. <b>Reset saringan</b> menghapus semuanya.</td></tr>
        <tr><td><span class="g-btn">Kolom</span></td><td>Pilih kolom yang ingin tampil. Pilihan ini hanya mengubah tampilan Anda.</td></tr>
        <tr><td><span class="g-btn">Ekspor</span></td><td>Mengunduh isi grid (sesuai pencarian, saringan, dan kolom yang tampil) sebagai berkas CSV untuk Excel.</td></tr>
        <tr><td><span class="g-btn">+ Data Baru</span></td><td>Membuka form kosong untuk menambah data. Tombol ini tidak tampil bila Anda tidak berhak menambah.</td></tr>
        <tr><td>Judul kolom</td><td>Klik untuk mengurutkan naik atau turun.</td></tr>
        <tr><td>Bagian bawah</td><td>Baris <b>Total (semua hasil)</b> menjumlahkan kolom angka sesuai saringan. <b>Ke halaman</b>, <b>Tampil</b> 10 / 25 / 50 / 100 baris, dan tombol halaman mengatur halaman yang tampil. Di kanan ada hitungan, mis. [1 – 2 dari 2].</td></tr>
        <tr><td>Klik 2x pada baris</td><td>Membuka datanya di form. Hasilnya sama dengan ikon pensil (<b>Edit</b>) atau <b>Buka</b>.</td></tr>
    </tbody>
</table>
<h3>Kolom Aksi dan Aktif / Nonaktif</h3>
<ul class="g-list">
    <li>Grid <b>master</b> punya ikon di awal baris. Pensil berarti <b>Edit</b>. Lingkaran coret berarti <b>Nonaktifkan</b>. Untuk data nonaktif, ikon itu berubah menjadi <b>Aktifkan</b>.</li>
    <li>Data <b>tidak pernah dihapus</b>. Data yang tidak dipakai lagi dinonaktifkan. Ia hilang dari pilihan di form baru, tetapi riwayatnya tetap utuh. Lihat yang nonaktif lewat kotak <b>Nonaktif</b> di atas grid.</li>
    <li>Grid <b>transaksi</b> (dokumen stok) hanya punya ikon <b>Buka</b> di awal baris. Dokumen tidak dinonaktifkan. <b>Konfirmasi</b>, <b>Setujui</b>, <b>Batalkan</b>, dan <b>Hapus</b> hanya ada di form dokumen.</li>
</ul>
<h3>Warna di grid</h3>
<ul class="g-list">
    <li><b>Kotak warna di master.</b> Kolom pertama grid master memakai kotak berwarna berisi inisial. Warna hanya membantu membedakan. Tidak ada arti lain.</li>
    <li><b>Grid transaksi polos.</b> Nomor dokumen tebal, tanpa kotak warna. Satu-satunya warna ada di kolom <b>Status</b>:
        <table class="g-table">
            <tbody>
                <tr><td><span class="g-st g-green">Terkonfirmasi</span> <span class="g-st g-green">Disetujui</span></td><td>Sudah berlaku dan masuk ke stok.</td></tr>
                <tr><td><span class="g-st g-grey">Draf</span></td><td>Baru disimpan. Stok belum berubah.</td></tr>
                <tr><td><span class="g-st g-yellow">Menunggu Persetujuan</span> <span class="g-st g-yellow">Pembatalan Menunggu Persetujuan</span></td><td>Menunggu keputusan Supervisor.</td></tr>
                <tr><td><span class="g-st g-red">Dibatalkan</span> <span class="g-st g-red">Ditolak</span></td><td>Tidak berlaku. Stok sudah dikembalikan atau tidak pernah berubah.</td></tr>
            </tbody>
        </table>
    </li>
    <li>Di grid <b>Barang</b>, baris merah muda berstatus <b>PESAN ULANG</b> berarti stok sudah di atau di bawah Titik Pesan Ulang. <b>AMAN</b> hijau berarti stok cukup.</li>
    <li>Di grid dokumen, tanda panah <b>&gt;</b> di awal baris membuka daftar <b>Barang</b> di dokumen itu tanpa membuka form. Di bawah nomor dokumen tertulis pihaknya (supplier, penerima, atau alasan).</li>
</ul>

<h2 id="form">5. Cara memakai Form</h2>
<p>Form dipakai untuk menambah atau mengubah data. Ada dua jenis: <b>form master</b> (barang, supplier, dan seterusnya) dan <b>form dokumen</b> (transaksi stok).</p>
<h3>Tombol umum</h3>
<table class="g-table">
    <thead><tr><th>Tombol</th><th>Fungsi</th></tr></thead>
    <tbody>
        <tr><td><span class="g-btn">Simpan</span></td><td>Menyimpan isian. Di form dokumen, Simpan membuat <b>Draf</b>. Stok belum berubah.</td></tr>
        <tr><td><span class="g-btn">Urungkan</span></td><td>Mengembalikan isian ke keadaan terakhir tersimpan.</td></tr>
        <tr><td><span class="g-btn">Kembali</span></td><td>Menuju <b>grid</b> modul itu, bukan layar sebelumnya. Isian yang belum disimpan tidak tersimpan.</td></tr>
        <tr><td><span class="g-btn">Baru</span></td><td>Membuka form kosong baru dari modul yang sama.</td></tr>
        <tr><td><span class="g-btn">Nonaktifkan</span> / <span class="g-btn">Aktifkan</span></td><td>Khusus form master. Mematikan atau menghidupkan lagi data.</td></tr>
        <tr><td>Tab <b>Data</b> dan <b>Riwayat</b></td><td>Data adalah isian. Riwayat adalah jejak: siapa membuat, mengubah, mengonfirmasi, mengajukan pembatalan, atau membatalkan, lengkap dengan alasan.</td></tr>
    </tbody>
</table>
<h3>Mengisi form</h3>
<ol class="g-steps">
    <li>Isian bertanda <b>bintang merah (*)</b> wajib diisi. Bila ada yang kosong atau salah, Stoku menampilkan pesan merah di bawah isian itu dan tidak menyimpan.</li>
    <li>Kolom abu-abu atau terkunci (mis. <b>Kode Barang</b>, <b>No. Dokumen</b>) diisi otomatis atau tidak bisa diubah setelah disimpan.</li>
    <li>Tekan <b>Simpan</b>. Pesan hijau di atas layar berarti berhasil.</li>
    <li>Tombol hanya bereaksi sekali. Tidak perlu menekan dua kali, dan data tidak tersimpan ganda.</li>
</ol>

<h3 id="tombol-plus">Tombol plus (Tambah baru)</h3>
<p>Datanya belum ada di pilihan? Anda tidak perlu keluar dari layar. Pilihan <b>Supplier</b>, <b>Penerima</b>, <b>Departemen</b>, <b>Kategori</b>, <b>Satuan</b>, dan <b>Lokasi</b> punya tombol <b>+</b> di sebelah kanan. Tulisan bantuannya <b>Tambah baru</b>.</p>
<ol class="g-steps">
    <li>Tekan tombol <b>+</b> di samping pilihan.</li>
    <li>Isi kotak kecil yang terbuka. Judulnya mis. <b>Tambah Supplier</b>.</li>
    <li>Tekan <b>Simpan</b>. Datanya masuk ke master dan <b>langsung terpilih</b> di isian Anda.</li>
</ol>
<ul class="g-list">
    <li><b>Tempatnya:</b>
        <ul class="g-list">
            <li>Penerimaan Barang: Supplier, Penerima, Departemen.</li>
            <li>Catat Pemesanan: Supplier.</li>
            <li>Pengeluaran Barang dan Penyesuaian Stok: Penerima, Departemen.</li>
            <li>Form Penerima: Departemen.</li>
            <li>Form Barang: Kategori, Satuan, Lokasi. Jendela Satuan masuk: Satuan.</li>
        </ul>
    </li>
    <li><b>Siapa yang boleh:</b> hanya yang boleh mengelola master itu. Supplier, Penerima, dan Departemen: Data Entry dan Supervisor. Kategori, Satuan, dan Lokasi: Supervisor. Bila Anda tidak berhak, tombolnya tidak tampil.</li>
    <li><b>Isiannya:</b>
        <ul class="g-list">
            <li>Supplier: Nama Supplier (wajib), Telepon Kantor, dan Nama Kontak. Kodenya otomatis, mis. SUP-001.</li>
            <li>Penerima: Nama Penerima.</li>
            <li>Departemen: Nama Departemen. Kodenya otomatis.</li>
            <li>Kategori: Awalan Kode (3 huruf atau angka) dan Nama Kategori.</li>
            <li>Lokasi: Kode Lokasi dan Nama Lokasi.</li>
            <li>Satuan: Kode Satuan dan Nama Satuan.</li>
        </ul>
    </li>
    <li><b>Nama yang sudah ada ditolak</b> dengan pesan merah di kotak kecil itu. Pengecualiannya Penerima, karena dua orang boleh bernama sama.</li>
    <li>Tombol tidak tampil di dokumen yang sudah Terkonfirmasi, karena dokumennya terkunci.</li>
    <li>Alamat dan wilayah supplier bisa dilengkapi nanti di menu Suppliers.</li>
</ul>

<h3>Baris detail (tabel Barang di form dokumen)</h3>
<ol class="g-steps">
    <li>Tekan <span class="g-btn">+ Tambah Barang</span> untuk menambah baris.</li>
    <li>Pada kolom <b>Barang</b>, ketik sebagian kode atau nama, lalu pilih dari daftar. Satu barang hanya boleh muncul sekali dalam satu dokumen (Pengeluaran dan Penyesuaian).</li>
    <li>Isi kolom lain pada baris itu. Kolom abu-abu dihitung otomatis: <b>Satuan</b> (kecuali di Penerimaan Barang, yang satuannya dipilih), <b>Tersedia</b>, <b>Jml Sistem</b>, <b>Subtotal</b>, <b>Selisih</b>. Baris <b>Jumlah</b> di bawah tabel menjumlahkan kolom tertentu.</li>
    <li>Setiap baris punya dua keadaan. <b>Baru diisi</b>: di ujung baris ada ikon <b>Simpan baris</b> (disket) dan <b>Batalkan isian baris</b> (panah putar). Simpan menuntaskan baris dan mengunci. Batalkan membuang baris baru. <b>Sudah ditulis</b>: ikon <b>Ubah baris</b> (pensil) dan <b>Hapus baris</b> (tong sampah). Ubah membuka baris lagi.</li>
    <li>Setelah dokumen <b>Terkonfirmasi</b>, ikon-ikon itu hilang karena baris tidak bisa diubah. Selama <b>Draf</b>, ikonnya selalu ada.</li>
    <li>Form baru sudah berisi satu baris (jumlah 1). Dokumen wajib punya minimal satu baris terisi. Tombol <b>Simpan</b> dan <b>Konfirmasi</b> di atas form ikut menyimpan baris yang sedang terbuka dan sudah lengkap.</li>
</ol>

<h3 id="siklus">Siklus dokumen: Simpan, Konfirmasi, Hapus, Batalkan</h3>
<p>Setiap dokumen stok melalui tahap yang sama. Di bagian atas form ada penunjuk tahap (<b>Draf</b> &gt; <b>Terkonfirmasi</b> &gt; <b>Ditutup</b>) yang menyala sesuai posisi dokumen.</p>
<table class="g-table">
    <thead><tr><th>Tahap atau tombol</th><th>Artinya</th></tr></thead>
    <tbody>
        <tr><td><span class="g-st g-grey">Draf</span> lewat <span class="g-btn">Simpan</span></td><td>Dokumen tersimpan dan nomornya dibuat otomatis (mis. RCV-2610-0001). <b>Stok belum berubah.</b> Draf masih boleh diubah.</td></tr>
        <tr><td><span class="g-btn">Hapus</span></td><td>Hanya untuk Draf. Dokumen dibuang permanen. Tombol ini hilang setelah Konfirmasi.</td></tr>
        <tr><td><span class="g-btn">Konfirmasi</span></td><td>Dokumen diproses ke stok. Setelah itu dokumen <b>terkunci</b> dan tidak bisa diubah. Syaratnya minimal satu baris. Tanggal dan jam dokumen adalah saat Konfirmasi (Saldo Awal memakai tanggal yang Anda pilih).</td></tr>
        <tr><td><span class="g-btn">Batalkan</span></td><td>Membatalkan dokumen yang sudah dikonfirmasi. <b>Alasan wajib</b> diisi.@if ($anyTrx) Lihat <a href="#t-batal">Membatalkan dokumen</a>.@endif</td></tr>
        <tr><td><span class="g-btn">Setujui</span> / <span class="g-btn">Tolak</span> <span class="g-role">hanya Supervisor</span></td><td>Memutuskan Penyesuaian Stok yang diajukan, atau pengajuan pembatalan.</td></tr>
        <tr><td><span class="g-btn">Cetak</span></td><td>Ada di form semua dokumen stok, apa pun statusnya. Hasil cetak memuat <b>mode</b> dokumen (DRAFT, CONFIRMED, atau CANCELLED). Dokumen yang belum sah atau tidak sah diberi tanda air.</td></tr>
    </tbody>
</table>
@include('help.diagram', ['name' => 'ug-siklus-dokumen', 'caption' => 'Siklus dokumen Saldo Awal, Penerimaan Barang, dan Pengeluaran Barang.'])
<div class="g-tip">Salah ketik pada dokumen yang sudah Terkonfirmasi? Dokumen terkunci. Batalkan lalu buat dokumen baru. Atau koreksi selisih stoknya lewat <b>Penyesuaian Stok</b>.</div>
