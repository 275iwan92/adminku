{{-- REMARK
 Tujuan       : Panduan Pengguna bagian 9c Kas: Uang Masuk (sumber dana ke satu Kas/Rekening), Uang Keluar (wajib ditaut ke satu Uang Masuk, jumlah dibatasi sisa saldo), Kas/Rekening (master, dibuka lewat Setup),
                dan tiga laporan (Arus Kas, Penerimaan, Pengeluaran) per Sumber Dana. Pola tetap tiap modul: Untuk apa, Siapa yang bisa, Langkah, Yang perlu diperhatikan, Masalah umum. Istilah sama persis dengan label di layar.
 Sumber hulu  : CashInController / cash-in.*, CashOutController / cash-out.*, CashAccountController / cash-accounts.*, CashReportController / reports.cash-*, CashIn, CashOut, CashAccount,
                Modules (kode KSM, KSK, KSR, R-KFLOW, R-KIN, R-KOUT), User::canAccess / canWrite / canApprove.
 Dipakai oleh : help/user-guide.blade.php (hanya bila pengguna punya salah satu modul Kas). Diagram: ug-kas-alur.
 Status       : Aktif.
--}}
@if ($anyKas)
<h2 id="kas">9c. Kas</h2>
<p>Menu <b>Keuangan</b> mencatat dana yang masuk ke kas atau rekening perusahaan dan dana yang keluar darinya: <b>Uang Masuk</b>, <b>Uang Keluar</b>, tiga laporan, dan master <b>Kas / Rekening</b> (dibuka lewat tombol Setup, bukan butir menu terpisah). Kas sekarang satu-satunya sub-kelompok di bawah induk Keuangan; bagian ini menyebutnya singkat sebagai &quot;Kas&quot;.</p>
<table class="g-table">
    <thead><tr><th>Layar</th><th>Isi singkat</th></tr></thead>
    <tbody>
        @if ($can('KSM'))<tr><td><a href="#kas-ksm">Uang Masuk</a></td><td>Dana diterima ke Kas Tunai atau satu rekening bank, dari satu sumber dana (dipilih dari daftar)</td></tr>@endif
        @if ($can('KSK'))<tr><td><a href="#kas-ksk">Uang Keluar</a></td><td>Pengeluaran per kebutuhan (kategori) dari Kas Tunai atau satu rekening bank; boleh ditaut ke satu Uang Masuk</td></tr>@endif
        @if ($can('KSR'))<tr><td><a href="#kas-ksr">Kas / Rekening</a></td><td>Daftar kas tunai dan rekening bank beserta saldo berjalan</td></tr>@endif
        @if ($can('R-KFLOW'))<tr><td><a href="#kas-rflow">Laporan Arus Kas</a></td><td>Buku kas per kas/rekening: saldo awal, baris kronologis, saldo akhir</td></tr>@endif
        @if ($can('R-KIN'))<tr><td><a href="#kas-rkin">Laporan Penerimaan</a></td><td>Uang Masuk: total Tunai, total Bank, rincian per rekening, per sumber dana</td></tr>@endif
        @if ($can('R-KOUT'))<tr><td><a href="#kas-rkout">Laporan Pengeluaran</a></td><td>Uang Keluar per kebutuhan (kategori), bisa diganti per sumber dana</td></tr>@endif
    </tbody>
</table>
<ul class="g-list">
    <li><b>Hak Kas datang dari Grup, bukan dari peran.</b> Menu Kas hanya tampil bila Grup Anda diberi modul <b>Uang Masuk</b> atau <b>Uang Keluar</b> (RO untuk membaca, RW untuk menyimpan dan mengajukan). Administrator melihat semuanya.</li>
    <li>Menyetujui atau menolak Uang Masuk dan Uang Keluar hanya untuk peran <b>Supervisor</b>, bukan <b>Admin Data</b> dan bukan pembuat dokumen itu sendiri. Admin Data membuka dan mengisi layar Kas seperti Supervisor, tetapi tombol <span class="g-btn">Approve</span> / <span class="g-btn">Reject</span> tidak tampil baginya.</li>
    <li><b>Beranda Kas (dashboard).</b> Klik <b>Keuangan</b> di panel kiri: angka saldo kas tunai dan rekening bank, masuk dan keluar bulan ini, grafik <b>pengeluaran per kebutuhan</b> (3 bulan terakhir), dan tren masuk dan keluar 6 bulan. Batang grafik bisa diklik dan membuka Laporan Pengeluaran. Hanya yang <b>Disetujui</b> yang dihitung.</li>
    <li><b>Transfer antar-kas belum ada.</b> Memindahkan dana dari satu Kas/Rekening ke Kas/Rekening lain belum bisa dicatat; ditunda untuk versi berikutnya.</li>
</ul>
@include('help.diagram', ['name' => 'ug-kas-alur', 'caption' => 'Uang Masuk disetujui menjadi sumber dana; Uang Keluar wajib menautnya dan dibatasi sisa saldo.'])

@if ($can('KSM'))
<h3 id="kas-ksm">Uang Masuk</h3>
<h4>Untuk apa</h4>
<p>Mencatat dana yang diterima ke <b>Kas Tunai</b> atau ke satu <b>rekening bank</b>, dari satu <b>sumber dana</b> yang dipilih dari daftar (misalnya &quot;Pendapatan operasional&quot;, &quot;Penjualan barang bekas&quot;, &quot;Donatur Bapak Hadi&quot;). Uang Masuk yang sudah <b>Disetujui</b> menjadi sumber dana yang bisa ditaut oleh Uang Keluar.</p>
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya modul <b>Uang Masuk</b> (RO untuk membaca, RW untuk menyimpan dan mengajukan). Menyetujui atau menolak hanya untuk Supervisor, bukan Admin Data dan bukan pembuatnya sendiri.</p>
<h4>Langkah: membuat dan mengajukan</h4>
<ol class="g-steps">
    <li>Buka <b>Keuangan</b> lalu <b>Uang Masuk</b>, tekan <span class="g-btn">New</span>.</li>
    <li>Isi <b>Tanggal</b>. Pada <b>Kas Tujuan</b> pilih <b>Tunai</b> (otomatis masuk Kas Tunai) atau <b>Rekening bank</b>. Bila Rekening bank, pilih rekeningnya di daftar <b>Rekening Bank</b>. Rekening belum ada? Tekan tombol <span class="g-btn">+</span> di sebelahnya, isi <b>Nama Bank</b>, <b>No. Rekening</b>, <b>Atas Nama</b>, lalu <b>Simpan</b>: rekening baru langsung terpilih.</li>
    <li>Isi <b>Jumlah (IDR)</b> (angka besar berwarna hijau) dan pilih <b>Sumber Dana</b> di daftar. Sumber belum ada? Tekan <span class="g-btn">+</span> di sebelahnya, ketik namanya, <b>Simpan</b>. <b>Keterangan</b> boleh kosong.</li>
    <li>Lampirkan foto atau PDF bukti penerimaan dana di <b>Lampiran Bukti</b> bila ada (tidak wajib; JPG, PNG, atau PDF, paling besar 5 MB).</li>
    <li>Tekan <span class="g-btn">Save</span> untuk menyimpan sebagai draf (belum bernomor). Tekan <span class="g-btn">Confirm</span>: nomor <b>UM-YYMM-0001</b> lahir, dan dokumen masuk antrean persetujuan Supervisor.</li>
</ol>
<h4>Langkah: menyetujui</h4>
<ol class="g-steps">
    <li>Uang Masuk berstatus <b>Diajukan</b>. Supervisor (bukan pembuatnya) membukanya lalu menekan <span class="g-btn">Approve</span> atau <span class="g-btn">Reject</span> (alasan wajib).</li>
    <li>Uang Masuk yang ditolak atau dibatalkan bisa disalin menjadi draf baru dengan <span class="g-btn">Duplicate</span>.</li>
</ol>
<h4>Kelola Sumber Dana</h4>
<p>Tombol <span class="g-btn">Kelola Sumber Dana</span> di daftar Uang Masuk membuka daftar semua sumber dana: tambah (<span class="g-btn">New</span> atau tombol <span class="g-btn">+</span> di form), ubah nama, atau <span class="g-btn">Delete</span>. Nama sumber disalin ke tiap Uang Masuk, jadi menghapusnya <b>tidak mengubah</b> dokumen lama. Tersedia untuk pengguna yang boleh menulis Uang Masuk.</p>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Status: <b>Draf</b>, <b>Diajukan</b>, <b>Disetujui</b>, <b>Ditolak</b>, <b>Dibatalkan</b>.</li>
    <li>Setelah <b>Disetujui</b>, form menampilkan daftar semua Uang Keluar yang menautnya, beserta jumlah yang sudah terpakai dan sisa saldonya.</li>
    <li><span class="g-btn">Cancel</span> (alasan wajib) bisa untuk Uang Masuk yang diajukan atau disetujui oleh pembuat atau Supervisor, <b>selama belum ada Uang Keluar yang ditaut dan masih diajukan atau disetujui</b>. <span class="g-btn">Delete</span> hanya untuk draf.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Save atau Confirm ditolak.</b> Rekening bank belum dipilih (saat memilih Rekening bank), Sumber Dana belum dipilih, atau Jumlah kosong atau 0. Memilih Tunai butuh satu kas jenis Tunai yang aktif di Setup Kas / Rekening.</li>
    <li><b>Rekening yang sama ditolak saat ditambah.</b> Bank dan No. Rekening itu sudah ada; pilih dari daftar.</li>
    <li><b>Tombol Approve tidak muncul.</b> Anda pembuat Uang Masuk itu, atau peran Anda Admin Data atau bukan Supervisor ke atas.</li>
    <li><b>Cancel ditolak.</b> Uang Masuk ini sudah punya Uang Keluar yang diajukan atau disetujui; batalkan atau tolak Uang Keluar itu dulu.</li>
</ul>
@endif

@if ($can('KSK'))
<h3 id="kas-ksk">Uang Keluar</h3>
<h4>Untuk apa</h4>
<p>Mencatat pengeluaran dana dari <b>Kas Tunai</b> atau satu <b>rekening bank</b> (<b>Kas Asal</b>). Menaut ke satu Uang Masuk yang sudah <b>Disetujui</b> bersifat <b>opsional</b>: bila ditaut, Kas Asal otomatis ikut kas Uang Masuk itu dan <b>terkunci</b>, dan jumlahnya dibatasi sisa saldo Uang Masuk itu; bila tidak ditaut, Anda memilih sendiri kasnya dan jumlahnya dibatasi <b>saldo kas</b> (saldo awal + Uang Masuk disetujui - Uang Keluar disetujui pada kas itu).</p>
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya modul <b>Uang Keluar</b> (RO untuk membaca, RW untuk menyimpan dan mengajukan). Menyetujui atau menolak hanya untuk Supervisor, bukan Admin Data dan bukan pembuatnya sendiri.</p>
<h4>Langkah: membuat dan mengajukan</h4>
<ol class="g-steps">
    <li>Buka <b>Keuangan</b> lalu <b>Uang Keluar</b>, tekan <span class="g-btn">New</span>.</li>
    <li>Form terbagi dua bagian. Bagian kiri: <b>No. Uang Keluar</b>, <b>Tanggal</b>, <b>Digunakan Untuk Kebutuhan</b>, <b>Keterangan</b>, <b>Lampiran Bukti</b>. Bagian kanan (panel abu-abu): <b>Ditaut Ke Uang Masuk</b> dan <b>Jumlah (IDR)</b>.</li>
    <li>(Opsional) Pilih <b>Ditaut Ke Uang Masuk</b>: hanya Uang Masuk yang sudah <b>Disetujui</b>, dengan sisa saldo tertulis di tiap pilihan. Bila dipilih, saklar Kas Asal dan rekening terisi mengikuti kas Uang Masuk itu dan tidak bisa diubah.</li>
    <li>Tanpa tautan, pilih <b>Kas Asal</b>: <b>Tunai</b> (otomatis kas tunai) atau <b>Rekening bank</b> lalu pilih rekeningnya (tombol <span class="g-btn">+</span> menambah rekening baru). Di bawah pilihan kas tampil <b>Saldo kas ini</b>, dan di bawah Jumlah tampil saldo setelah pengeluaran ini.</li>
    <li>Isi <b>Jumlah (IDR)</b> (angka besar berwarna merah). Bila melebihi sisa saldo, angka berubah kuning dan muncul peringatan &quot;Jumlah melebihi sisa&quot;; kurangi jumlahnya atau pilih Uang Masuk lain.</li>
    <li>Pilih <b>Digunakan Untuk Kebutuhan</b> (wajib): satu kategori pendek, misalnya Transportasi, Pembelian bahan pokok, Pembelian bahan pendukung. Kategori belum ada? Tekan <span class="g-btn">+</span> di sebelahnya. Tulis rinciannya di <b>Keterangan</b>; untuk kategori <b>Lainnya</b>, Keterangan <b>wajib</b> diisi.</li>
    <li>Lampirkan foto atau PDF bukti pengeluaran di <b>Lampiran Bukti</b> bila ada (tidak wajib; JPG, PNG, atau PDF, paling besar 5 MB).</li>
    <li>Tekan <span class="g-btn">Save</span> untuk menyimpan sebagai draf (belum bernomor). Tekan <span class="g-btn">Confirm</span>: nomor <b>UK-YYMM-0001</b> lahir, dan dokumen masuk antrean persetujuan Supervisor.</li>
</ol>
<h4>Langkah: menyetujui</h4>
<ol class="g-steps">
    <li>Uang Keluar berstatus <b>Diajukan</b>. Supervisor (bukan pembuatnya) membukanya lalu menekan <span class="g-btn">Approve</span> atau <span class="g-btn">Reject</span> (alasan wajib).</li>
    <li>Saat <span class="g-btn">Approve</span>, <b>sisa saldo Uang Masuk yang ditaut dicek ulang</b>. Bila sisa saldo sudah tidak cukup (misalnya terpakai Uang Keluar lain yang disetujui lebih dulu), persetujuan ditolak.</li>
    <li>Uang Keluar yang ditolak atau dibatalkan bisa disalin menjadi draf baru dengan <span class="g-btn">Duplicate</span>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Status: <b>Draf</b>, <b>Diajukan</b>, <b>Disetujui</b>, <b>Ditolak</b>, <b>Dibatalkan</b>.</li>
    <li>Jumlah yang melebihi sisa saldo Uang Masuk tertaut atau saldo kas ditolak saat Save dan dicek ulang saat <b>Approve</b> (dua Uang Keluar yang berebut saldo yang sama: yang disetujui lebih dulu menang, yang kedua ditolak). Dua Uang Keluar atas Uang Masuk yang sama boleh diajukan bersamaan; yang disetujui lebih dulu memakai sisa saldonya lebih dulu.</li>
    <li><span class="g-btn">Cancel</span> (alasan wajib) bisa untuk Uang Keluar yang diajukan atau disetujui, oleh pembuat atau Supervisor. <span class="g-btn">Delete</span> hanya untuk draf.</li>
</ul>
<h4>Kelola Kebutuhan</h4>
<p>Tombol <span class="g-btn">Kelola Kebutuhan</span> di daftar Uang Keluar membuka daftar kategori kebutuhan: tambah, ubah nama, <span class="g-btn">Delete</span>. Kategori yang <b>sudah dipakai</b> Uang Keluar tidak dihapus, hanya <b>dinonaktifkan</b> (hilang dari pilihan, laporan dan grafik lama tetap utuh) dan bisa diaktifkan lagi. Uang Keluar lama yang bernama bebas tampil sebagai <b>Belum dikategorikan</b> di laporan.</p>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Save atau Confirm ditolak.</b> Uang Masuk belum dipilih, Jumlah kosong, 0, atau melebihi sisa saldo, Kebutuhan belum dipilih, atau Keterangan kosong padahal kebutuhannya <b>Lainnya</b>.</li>
    <li><b>Saldo kas tidak cukup lagi.</b> Muncul saat Approve: saldo kas Kas Asal sudah terpakai Uang Keluar lain yang disetujui lebih dulu. Kurangi jumlahnya atau pilih kas lain.</li>
    <li><b>Sisa saldo Uang Masuk tidak cukup lagi.</b> Muncul saat Approve: sisa saldo Uang Masuk yang ditaut sudah terpakai Uang Keluar lain yang disetujui lebih dulu. Tolak atau batalkan dokumen ini, atau pilih Uang Masuk lain lewat Duplicate.</li>
    <li><b>Tombol Approve tidak muncul.</b> Anda pembuat Uang Keluar itu, atau peran Anda Admin Data atau bukan Supervisor ke atas.</li>
</ul>
@endif

@if ($can('KSR'))
<h3 id="kas-ksr">Kas / Rekening</h3>
<h4>Untuk apa</h4>
<p>Daftar kas tunai dan rekening bank perusahaan, masing-masing dengan saldo awal sendiri. <b>Saldo Berjalan</b> dihitung, bukan diketik: saldo awal ditambah semua Uang Masuk Disetujui ke kas itu, dikurangi semua Uang Keluar Disetujui yang ditaut ke Uang Masuk kas itu.</p>
<h4>Siapa yang bisa</h4>
<p>Peran Supervisor ke atas yang Grup-nya punya modul <b>Kas / Rekening</b> (RO untuk membaca, RW untuk menambah dan mengubah). Dibuka lewat tombol <span class="g-btn">Setup</span> di layar Uang Masuk atau Uang Keluar.</p>
<h4>Langkah: menambah Kas / Rekening</h4>
<ol class="g-steps">
    <li>Buka <b>Keuangan</b> lalu <b>Uang Masuk</b> atau <b>Uang Keluar</b>, tekan <span class="g-btn">Setup</span>, lalu <span class="g-btn">New</span>.</li>
    <li>Isi <b>Nama</b> dan pilih <b>Jenis</b>: <i>Tunai</i> atau <i>Bank</i>.</li>
    <li>Isi <b>Saldo Awal (IDR)</b>. Untuk Bank, isi juga <b>Nama Bank</b>, <b>No. Rekening</b>, dan <b>Atas Nama</b> (boleh kosong untuk Tunai).</li>
    <li>Tekan <b>Simpan</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li><b>Saldo Berjalan</b> baru tampil setelah Kas/Rekening disimpan, dan selalu dihitung ulang, tidak bisa diketik langsung.</li>
    <li>Kas / Rekening <b>tidak dihapus</b>; yang tidak dipakai lagi dinonaktifkan lewat <span class="g-btn">Deactivate</span>. Kas nonaktif tidak muncul di pilihan Kas Tujuan pada Uang Masuk baru, tetapi Uang Masuk lama tetap menunjuknya.</li>
    <li>Saldo Awal <b>tidak terkunci</b> setelah ada transaksi; mengubahnya ikut mengubah Saldo Berjalan. Ubah dengan hati-hati pada Kas / Rekening yang sudah dipakai.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Kas / Rekening tidak ada di pilihan Kas Tujuan.</b> Sudah dinonaktifkan. Aktifkan kembali di Setup bila masih dipakai.</li>
    <li><b>Simpan ditolak.</b> Nama kosong, atau Jenis belum dipilih.</li>
</ul>
@endif

@if ($can('R-KFLOW'))
<h3 id="kas-rflow">Laporan Arus Kas</h3>
<h4>Untuk apa</h4>
<p>Buku kas: saldo awal periode, baris Uang Masuk dan Uang Keluar yang sudah <b>Disetujui</b> secara kronologis, dan saldo akhir — per <b>Kas / Rekening</b>, atau digabung jadi satu buku <b>Semua Kas</b>.</p>
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya modul <b>Laporan Arus Kas</b> (RO cukup, laporan ini hanya membaca).</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>Keuangan</b> lalu <b>Laporan Arus Kas</b>.</li>
    <li>Atur <b>Dari tanggal</b> dan <b>Sampai tanggal</b>, pilih <b>Kas</b> (atau biarkan Semua kas), lalu tekan <b>Tampilkan</b>.</li>
    <li>Pilih kelompok <b>Per Kas / Rekening</b> (satu buku per kas) atau <b>Gabungan Semua Kas</b> lewat tombol Grup di atas tabel.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Hanya Uang Masuk dan Uang Keluar berstatus <b>Disetujui</b> yang dihitung; draf dan yang diajukan tidak masuk buku kas.</li>
    <li>Baris <b>Saldo awal</b> di atas bukan Saldo Awal yang diisi di layar Kas / Rekening: ia sudah mencakup semua Uang Masuk dan Uang Keluar Disetujui <b>sebelum</b> Dari tanggal. Pilih periode yang mencakup sejak kas itu mulai dipakai untuk melihat Saldo Awal aslinya di baris pertama.</li>
    <li>Kolom <b>Saldo Berjalan</b> adalah saldo kas itu setelah baris tersebut: Saldo awal baris pertama ditambah atau dikurangi tiap transaksi berikutnya secara berurutan.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Buku kosong.</b> Tidak ada Uang Masuk atau Uang Keluar Disetujui dalam periode itu untuk kas yang dipilih. Perluas periodenya atau pilih Semua kas.</li>
</ul>
@endif

@if ($can('R-KIN'))
<h3 id="kas-rkin">Laporan Penerimaan</h3>
<h4>Untuk apa</h4>
<p>Semua Uang Masuk dengan ringkasan <b>Masuk Tunai</b> dan <b>Masuk Bank</b>. Baris dikelompokkan per <b>Kas / Rekening</b> (Kas Tunai dan tiap rekening bank beserta subtotalnya); kelompok bisa diganti ke <b>Per Sumber Dana</b>. Kolom diterima, terpakai (lewat Uang Keluar yang ditaut dan disetujui), dan sisa tetap ada.</p>
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya modul <b>Laporan Penerimaan</b> (RO cukup).</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>Keuangan</b> lalu <b>Laporan Penerimaan</b>.</li>
    <li>Atur periode, pilih <b>Sumber Dana</b> (atau Semua sumber dana) dan <b>Status</b> (bawaan: Disetujui), lalu tekan <b>Tampilkan</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Status bawaan adalah <b>Disetujui</b>; pilih <b>Semua</b> di isian Status untuk ikut melihat Draf, Diajukan, Ditolak, dan Dibatalkan.</li>
    <li>Kolom <b>Terpakai</b> dan <b>Sisa</b> dihitung dari Uang Keluar yang disetujui, berapa pun status Uang Masuk itu sendiri.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Daftar kosong.</b> Tidak ada Uang Masuk yang cocok dengan filter Periode, Sumber Dana, dan Status. Ubah Status jadi Semua untuk ikut melihat Draf dan Diajukan.</li>
</ul>
@endif

@if ($can('R-KOUT'))
<h3 id="kas-rkout">Laporan Pengeluaran</h3>
<h4>Untuk apa</h4>
<p>Semua Uang Keluar dikelompokkan per <b>Kebutuhan</b> (kategori), misalnya Transportasi atau Pembelian bahan pokok, dengan subtotal tiap kategori dan ringkasan <b>Keluar Tunai</b> dan <b>Keluar Bank</b>. Kelompok bisa diganti ke <b>Per Kas / Rekening</b> (Tunai dan tiap rekening bank) atau <b>Per Sumber Dana</b> (lewat Uang Masuk yang ditautnya; yang tanpa tautan tampil sebagai &quot;Tanpa Uang Masuk&quot;). Kolom <b>Keterangan</b> memuat rincian tiap pengeluaran.</p>
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya modul <b>Laporan Pengeluaran</b> (RO cukup).</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>Keuangan</b> lalu <b>Laporan Pengeluaran</b>.</li>
    <li>Atur periode, pilih <b>Kas</b>, <b>Sumber Dana</b>, dan <b>Status</b> (bawaan: Disetujui, atau Semua), lalu tekan <b>Tampilkan</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Sumber Dana di sini ikut <b>Uang Masuk yang ditaut</b>, bukan kolom milik Uang Keluar itu sendiri.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Daftar kosong.</b> Tidak ada Uang Keluar yang cocok dengan filter Periode, Kas, Sumber Dana, dan Status. Ubah Status jadi Semua untuk ikut melihat Draf dan Diajukan.</li>
</ul>
@endif
@endif
