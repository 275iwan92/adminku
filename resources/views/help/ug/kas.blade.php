{{-- REMARK
 Tujuan       : Panduan Pengguna bagian 9c Kas: Uang Masuk (sumber dana ke satu Kas/Rekening), Uang Keluar (wajib ditaut ke satu Uang Masuk, jumlah dibatasi sisa saldo), dan Kas/Rekening (master, dibuka lewat Setup).
                Pola tetap tiap modul: Untuk apa, Siapa yang bisa, Langkah, Yang perlu diperhatikan, Masalah umum. Istilah sama persis dengan label di layar.
 Sumber hulu  : CashInController / cash-in.*, CashOutController / cash-out.*, CashAccountController / cash-accounts.*, CashIn, CashOut, CashAccount, Modules (kode KSM, KSK, KSR), User::canAccess / canWrite / canApprove.
 Dipakai oleh : help/user-guide.blade.php (hanya bila pengguna punya modul KSM, KSK, atau KSR). Diagram: ug-kas-alur.
 Status       : Aktif.
--}}
@if ($anyKas)
<h2 id="kas">9c. Kas</h2>
<p>Menu Kas mencatat dana yang masuk ke kas atau rekening perusahaan dan dana yang keluar darinya: <b>Uang Masuk</b>, <b>Uang Keluar</b>, dan master <b>Kas / Rekening</b> (dibuka lewat tombol Setup, bukan butir menu terpisah).</p>
<table class="g-table">
    <thead><tr><th>Layar</th><th>Isi singkat</th></tr></thead>
    <tbody>
        @if ($can('KSM'))<tr><td><a href="#kas-ksm">Uang Masuk</a></td><td>Dana diterima ke satu Kas/Rekening dari satu sumber dana (teks bebas)</td></tr>@endif
        @if ($can('KSK'))<tr><td><a href="#kas-ksk">Uang Keluar</a></td><td>Pengeluaran yang wajib ditaut ke satu Uang Masuk sebagai sumber dananya</td></tr>@endif
        @if ($can('KSR'))<tr><td><a href="#kas-ksr">Kas / Rekening</a></td><td>Daftar kas tunai dan rekening bank beserta saldo berjalan</td></tr>@endif
    </tbody>
</table>
<ul class="g-list">
    <li><b>Hak Kas datang dari Grup, bukan dari peran.</b> Menu Kas hanya tampil bila Grup Anda diberi modul <b>Uang Masuk</b> atau <b>Uang Keluar</b> (RO untuk membaca, RW untuk menyimpan dan mengajukan). Administrator melihat semuanya.</li>
    <li>Menyetujui atau menolak Uang Masuk dan Uang Keluar hanya untuk peran <b>Supervisor</b>, bukan <b>Admin Data</b> dan bukan pembuat dokumen itu sendiri. Admin Data membuka dan mengisi layar Kas seperti Supervisor, tetapi tombol <span class="g-btn">Approve</span> / <span class="g-btn">Reject</span> tidak tampil baginya.</li>
    <li><b>Transfer antar-kas belum ada.</b> Memindahkan dana dari satu Kas/Rekening ke Kas/Rekening lain belum bisa dicatat; ditunda untuk versi berikutnya.</li>
</ul>
@include('help.diagram', ['name' => 'ug-kas-alur', 'caption' => 'Uang Masuk disetujui menjadi sumber dana; Uang Keluar wajib menautnya dan dibatasi sisa saldo.'])

@if ($can('KSM'))
<h3 id="kas-ksm">Uang Masuk</h3>
<h4>Untuk apa</h4>
<p>Mencatat dana yang diterima ke satu <b>Kas / Rekening</b> dari satu <b>sumber dana</b> (ditulis bebas, misalnya nama institusi, donatur, atau &quot;Pendapatan operasional&quot;). Uang Masuk yang sudah <b>Disetujui</b> menjadi sumber dana yang bisa ditaut oleh Uang Keluar.</p>
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya modul <b>Uang Masuk</b> (RO untuk membaca, RW untuk menyimpan dan mengajukan). Menyetujui atau menolak hanya untuk Supervisor, bukan Admin Data dan bukan pembuatnya sendiri.</p>
<h4>Langkah: membuat dan mengajukan</h4>
<ol class="g-steps">
    <li>Buka <b>Kas</b> lalu <b>Uang Masuk</b>, tekan <span class="g-btn">New</span>.</li>
    <li>Isi <b>Tanggal</b> dan pilih <b>Kas Tujuan</b>.</li>
    <li>Isi <b>Sumber Dana</b> (bebas, wajib) dan <b>Jumlah (IDR)</b>. <b>Keterangan</b> boleh kosong.</li>
    <li>Lampirkan foto atau PDF bukti penerimaan dana di <b>Lampiran Bukti</b> bila ada (tidak wajib; JPG, PNG, atau PDF, paling besar 5 MB).</li>
    <li>Tekan <span class="g-btn">Save</span> untuk menyimpan sebagai draf (belum bernomor). Tekan <span class="g-btn">Confirm</span>: nomor <b>UM-YYMM-0001</b> lahir, dan dokumen masuk antrean persetujuan Supervisor.</li>
</ol>
<h4>Langkah: menyetujui</h4>
<ol class="g-steps">
    <li>Uang Masuk berstatus <b>Diajukan</b>. Supervisor (bukan pembuatnya) membukanya lalu menekan <span class="g-btn">Approve</span> atau <span class="g-btn">Reject</span> (alasan wajib).</li>
    <li>Uang Masuk yang ditolak atau dibatalkan bisa disalin menjadi draf baru dengan <span class="g-btn">Duplicate</span>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Status: <b>Draf</b>, <b>Diajukan</b>, <b>Disetujui</b>, <b>Ditolak</b>, <b>Dibatalkan</b>.</li>
    <li>Setelah <b>Disetujui</b>, form menampilkan daftar semua Uang Keluar yang menautnya, beserta jumlah yang sudah terpakai dan sisa saldonya.</li>
    <li><span class="g-btn">Cancel</span> (alasan wajib) bisa untuk Uang Masuk yang diajukan atau disetujui oleh pembuat atau Supervisor, <b>selama belum ada Uang Keluar yang ditaut dan masih diajukan atau disetujui</b>. <span class="g-btn">Delete</span> hanya untuk draf.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Save atau Confirm ditolak.</b> Kas Tujuan belum dipilih, Sumber Dana kosong, atau Jumlah kosong atau 0.</li>
    <li><b>Tombol Approve tidak muncul.</b> Anda pembuat Uang Masuk itu, atau peran Anda Admin Data atau bukan Supervisor ke atas.</li>
    <li><b>Cancel ditolak.</b> Uang Masuk ini sudah punya Uang Keluar yang diajukan atau disetujui; batalkan atau tolak Uang Keluar itu dulu.</li>
</ul>
@endif

@if ($can('KSK'))
<h3 id="kas-ksk">Uang Keluar</h3>
<h4>Untuk apa</h4>
<p>Mencatat pengeluaran dana. Setiap Uang Keluar <b>wajib ditaut ke satu Uang Masuk</b> yang sudah <b>Disetujui</b>, sebagai sumber dananya. <b>Kas Asal</b> tidak dipilih sendiri: ia otomatis ikut Kas Tujuan milik Uang Masuk yang ditaut.</p>
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya modul <b>Uang Keluar</b> (RO untuk membaca, RW untuk menyimpan dan mengajukan). Menyetujui atau menolak hanya untuk Supervisor, bukan Admin Data dan bukan pembuatnya sendiri.</p>
<h4>Langkah: membuat dan mengajukan</h4>
<ol class="g-steps">
    <li>Buka <b>Kas</b> lalu <b>Uang Keluar</b>, tekan <span class="g-btn">New</span>.</li>
    <li>Isi <b>Tanggal</b>, lalu pilih <b>Ditaut Ke Uang Masuk</b>. Pilihannya hanya Uang Masuk yang sudah <b>Disetujui</b>, dengan sisa saldo tertulis di tiap pilihan. <b>Kas Asal</b> terisi sendiri mengikuti pilihan ini.</li>
    <li>Isi <b>Jumlah (IDR)</b> dan <b>Untuk Apa</b> (wajib). <b>Keterangan</b> boleh kosong.</li>
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
    <li>Jumlah Uang Keluar tidak dibatasi saat Save atau Confirm, hanya dicek ulang saat <b>Approve</b>. Dua Uang Keluar atas Uang Masuk yang sama boleh diajukan bersamaan; yang disetujui lebih dulu memakai sisa saldonya lebih dulu.</li>
    <li><span class="g-btn">Cancel</span> (alasan wajib) bisa untuk Uang Keluar yang diajukan atau disetujui, oleh pembuat atau Supervisor. <span class="g-btn">Delete</span> hanya untuk draf.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Save atau Confirm ditolak.</b> Uang Masuk belum dipilih, Jumlah kosong atau 0, atau Untuk Apa kosong.</li>
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
    <li>Buka <b>Kas</b> lalu <b>Uang Masuk</b> atau <b>Uang Keluar</b>, tekan <span class="g-btn">Setup</span>, lalu <span class="g-btn">New</span>.</li>
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
@endif
