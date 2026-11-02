{{-- REMARK
 Tujuan       : Panduan Pengguna bagian 9 GA (General Affairs): Perawatan Kendaraan (Monitoring Kendaraan dan Detail kendaraan, Perpanjang Dokumen Kendaraan, Catat Servis Kendaraan, Catat Odometer, Master Kendaraan, Jenis Kendaraan)
                dan Perawatan AC (Penjadwalan AC, Kunjungan AC, Denah AC, Master AC, Area dan Gedung AC, Ruang AC, Vendor AC), arti Aman / Segera / Terlambat, dan hak akses lewat Grup.
                Pola tetap tiap modul: Untuk apa, Siapa yang bisa, Langkah, Yang perlu diperhatikan, Masalah umum. Istilah sama persis dengan label di layar.
 Sumber hulu  : FleetController / fleet/*, Vehicle*Controller / vehicle-docs, vehicle-services, vehicle-odometers, vehicles, vehicle-types, VehicleCare, AcScheduleController / ac-schedule, AcVisitController / ac-visits, AcPlanController / ac-plan,
                AcUnit / AcArea / AcRoom / AcVendor controller dan view, AcCare, AcVisits, AcMessage, AcVisitPrint, Modules (kode GV* dan GA*, peran minimal), User::canAccess / canWrite, Menu::layers (Perawatan Gedung sementara).
 Dipakai oleh : help/user-guide.blade.php (hanya bila pengguna punya salah satu modul GA; tiap bagian bergantung $can). Diagram: ug-ga-perpanjang-servis, ug-ga-kunjungan-ac.
 Status       : Aktif.
--}}
@if ($anyGa)
@php
    // Tautan ke bagian modul lain hanya bila pengguna boleh membukanya (kalau tidak, hanya teks).
    $ref = fn (string $code, string $id, string $label) => $can($code) ? '<a href="#'.$id.'">'.e($label).'</a>' : e($label);
@endphp
<h2 id="ga">9. GA</h2>
<p>Menu GA di AdminKU membantu bagian Umum (GA) Jays memantau dan mencatat perawatan aset: <b>kendaraan</b> (pajak, STNK dan plat, KIR, asuransi, servis, odometer) dan <b>AC</b> (jadwal cuci, kunjungan vendor, denah). Modul GA tidak menyentuh stok gudang.</p>
<table class="g-table">
    <thead><tr><th>Modul</th><th>Isi singkat</th></tr></thead>
    <tbody>
        @if ($can('GVM'))<tr><td>{!! $ref('GVM', 'ga-gvm', 'Monitoring Kendaraan') !!}</td><td>Status semua kendaraan aktif, lalu Detail satu kendaraan: dokumen, jadwal perawatan, riwayat servis</td></tr>@endif
        @if ($can('GVD'))<tr><td>{!! $ref('GVD', 'ga-gvd', 'Perpanjang Dokumen Kendaraan') !!}</td><td>Mencatat pajak tahunan, STNK dan plat 5 tahunan, KIR, atau asuransi yang baru dibayar</td></tr>@endif
        @if ($can('GVS'))<tr><td>{!! $ref('GVS', 'ga-gvs', 'Catat Servis Kendaraan') !!}</td><td>Mencatat servis dan pekerjaan yang dikerjakan; jadwal berikutnya dihitung otomatis</td></tr>@endif
        @if ($can('GVO'))<tr><td>{!! $ref('GVO', 'ga-gvo', 'Catat Odometer') !!}</td><td>Mencatat kilometer yang tertera (satu-satunya layar untuk sopir)</td></tr>@endif
        @if ($can('GVK'))<tr><td>{!! $ref('GVK', 'ga-gvk', 'Master Kendaraan') !!}</td><td>Daftar kendaraan: plat, nama, jenis, wajib KIR, odometer awal</td></tr>@endif
        @if ($can('GVT'))<tr><td>{!! $ref('GVT', 'ga-gvt', 'Jenis Kendaraan') !!}</td><td>Jenis kendaraan beserta Rencana Perawatan (pekerjaan dan intervalnya)</td></tr>@endif
        @if ($can('GAS'))<tr><td>{!! $ref('GAS', 'ga-gas', 'Penjadwalan AC') !!}</td><td>Kalender kunjungan vendor, kapasitas harian, Jadwalkan yang dipilih, Susun otomatis</td></tr>@endif
        @if ($can('GAV'))<tr><td>{!! $ref('GAV', 'ga-gav', 'Kunjungan AC') !!}</td><td>Pesanan kunjungan vendor: Kirim, konfirmasi, selesai, jadwal ulang, batal, foto bukti, Berita Acara</td></tr>@endif
        @if ($can('GAD'))<tr><td>{!! $ref('GAD', 'ga-gad', 'Denah AC') !!}</td><td>Denah per area dengan titik AC berwarna menurut status</td></tr>@endif
        @if ($can('GAK'))<tr><td>{!! $ref('GAK', 'ga-gak', 'Master AC') !!}</td><td>Daftar unit AC: kode aset, ruang, merek, tanggal pasang</td></tr>@endif
        @if ($can('GAA'))<tr><td>{!! $ref('GAA', 'ga-gaa', 'Area dan Gedung AC') !!}</td><td>Area atau gedung beserta gambar denahnya</td></tr>@endif
        @if ($can('GAR'))<tr><td>{!! $ref('GAR', 'ga-gar', 'Ruang AC') !!}</td><td>Ruang di tiap area, kode ruang, dan PIC</td></tr>@endif
        @if ($can('GAE'))<tr><td>{!! $ref('GAE', 'ga-gae', 'Vendor AC') !!}</td><td>Vendor jasa AC beserta nomor WhatsApp dan e-mail</td></tr>@endif
        @if ($can('GAP'))<tr><td>{!! $ref('GAP', 'ga-gap', 'Pengaturan GA') !!}</td><td>Ambang Segera, interval cuci dan servis besar, hari dan jam kunjungan, kapasitas harian <span class="g-role">hanya Supervisor ke atas</span></td></tr>@endif
    </tbody>
</table>

<h3 id="ga-hak">Siapa boleh apa di GA</h3>
<ul class="g-list">
    <li><b>Hak GA datang dari Grup, bukan dari peran.</b> Menu GA hanya tampil bila Grup Anda diberi modulnya. Administrator melihat semuanya.</li>
    <li>Tiap modul punya dua tingkat: <b>RO</b> (hanya baca: boleh membuka layar) dan <b>RW</b> (baca dan tulis: boleh juga menyimpan, mengirim, dan mengubah). Form Perpanjang Dokumen Kendaraan, Catat Servis Kendaraan, dan Catat Odometer baru bisa dibuka dengan hak <b>RW</b>.</li>
    <li>Tombol yang tidak boleh Anda pakai tidak tampil. Contoh: tanpa hak RW pada Catat Servis Kendaraan, tombol <span class="g-btn">Catat Servis</span> tidak ada di Monitoring Kendaraan.</li>
    <li>Pembagian yang lazim: petugas admin GA mendapat semua modul GA, sopir hanya <b>Catat Odometer</b>. Administrator mengatur ini di menu {!! $can('GRP') ? '<a href="#sec-grp">Grup</a>' : 'Grup' !!}.</li>
    <li><b>Pengaturan GA</b> hanya untuk peran Supervisor ke atas: Data Entry tidak bisa memakainya walau Grup-nya dicentang.</li>
    <li><b>Menu GA ringkas:</b> satu butir <b>Perawatan AC</b> dan satu butir <b>Monitoring Kendaraan</b>. Layar lainnya dibuka lewat tombol di panelnya (<span class="g-btn">Setup</span>, <span class="g-btn">Penjadwalan</span>, dan seterusnya). Pengguna yang tidak punya panelnya (mis. sopir yang hanya punya Catat Odometer) tetap melihat layar yang diizinkan di menu.</li>
    <li>Menu GA milik Administrator juga memuat <b>Perawatan Gedung</b>. Itu layar sementara bertuliskan "Segera hadir"; belum bisa dipakai.</li>
</ul>

<h3 id="ga-status">Arti Aman, Segera, dan Terlambat</h3>
<p>Kendaraan dan AC diberi status berwarna supaya yang mendesak kelihatan lebih dulu.</p>
<table class="g-table">
    <thead><tr><th>Status</th><th>Kendaraan</th><th>AC</th></tr></thead>
    <tbody>
        <tr><td><span class="g-st g-green">Aman</span></td><td>Dokumen berlaku lebih dari 30 hari lagi, dan servis masih jauh (lebih dari 1.000 km dan lebih dari 30 hari).</td><td>Jatuh tempo masih lebih dari 30 hari lagi.</td></tr>
        <tr><td><span class="g-st g-yellow">Segera</span></td><td>Dokumen berlaku 30 hari lagi atau kurang, atau pajak tahunan, STNK dan plat, atau KIR (bagi kendaraan wajib KIR) <b>Belum dicatat</b>. Servis: sisa 1.000 km atau kurang, atau 30 hari atau kurang.</td><td>Jatuh tempo dalam 30 hari atau kurang.</td></tr>
        <tr><td><span class="g-st g-red">Terlambat</span></td><td>Tanggal Berlaku sampai sudah lewat, atau servis sudah melewati kilometer atau tanggalnya.</td><td>Tanggal jatuh tempo sudah lewat.</td></tr>
    </tbody>
</table>
<ul class="g-list">
    <li>Status satu kendaraan adalah yang <b>paling buruk</b> dari semua dokumen dan semua pekerjaan perawatannya.</li>
    <li>Angka 30 hari dan 1.000 km adalah bawaan. Angka yang berlaku tertulis di kotak <b>Segera</b> (kendaraan) dan di keterangan layar Denah AC. Mengubahnya di {!! $ref('GAP', 'ga-gap', 'Pengaturan GA') !!}.</li>
    <li>Asuransi yang belum dicatat tidak membuat kendaraan berstatus Segera.</li>
</ul>

@if ($can('GVM'))
<h3 id="ga-gvm">Monitoring Kendaraan</h3>
<h4>Untuk apa</h4>
<p>Satu layar untuk memantau semua kendaraan aktif: dokumen mana yang akan atau sudah habis, servis apa yang segera jatuh tempo, dan berapa biaya servis tahun ini. Layar ini hanya untuk dibaca; pencatatannya lewat tiga form (lihat di bawah).</p>
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya modul <b>Monitoring Kendaraan</b> (RO sudah cukup). Detail kendaraan dan lampirannya ikut modul ini.</p>
<h4>Langkah: membaca Monitoring Kendaraan</h4>
<ol class="g-steps">
    <li>Buka <b>GA</b> lalu <b>Monitoring Kendaraan</b>.</li>
    <li>Baca empat kotak di atas: <b>Kendaraan aktif</b>; <b>Terlambat</b> (dokumen atau servis sudah lewat); <b>Segera</b> (dalam 30 hari atau 1.000 km); dan <b>Biaya servis</b> tahun ini.</li>
    <li>Baca tabelnya. Kolom yang tampil: <b>Kendaraan</b> (plat dan nama), <b>Pajak tahunan</b>, <b>KIR</b>, <b>Servis berikutnya</b>, dan <b>Status</b>. Urutannya Terlambat dulu, lalu Segera, lalu Aman.</li>
    <li>Kolom <b>Jenis</b>, <b>Odometer (km)</b>, <b>STNK dan plat</b>, dan <b>Asuransi</b> tersembunyi. Tampilkan lewat tombol <span class="g-btn">Kolom</span>.</li>
    <li>Cari kendaraan di kotak pencarian (plat, nama, jenis, atau status).</li>
    <li>Klik 2x barisnya, atau tekan <span class="g-btn">Buka</span>, untuk membuka Detail kendaraan.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Sel dokumen berbunyi tanggal Berlaku sampai dan sisa waktunya, mis. <i>12-03-2027 · 120 hari lagi</i> atau <i>lewat 5 hari</i>. <i>Tidak wajib</i> muncul di KIR untuk mobil penumpang. <i>Belum dicatat</i> berarti belum pernah ada perpanjangan yang dicatat.</li>
    <li>Sel <b>Servis berikutnya</b> menunjuk pekerjaan yang paling dekat jatuh tempo, mis. <i>Oli mesin · 800 km lagi / 20 hari lagi</i>. <i>Tidak ada jadwal</i> berarti Jenis Kendaraannya belum punya rencana perawatan.</li>
    <li>Hanya kendaraan <b>aktif</b> yang tampil. Kendaraan nonaktif tersimpan di {!! $ref('GVK', 'ga-gvk', 'Master Kendaraan') !!}.</li>
    <li>Tombol <span class="g-btn">Perpanjang Dokumen</span>, <span class="g-btn">Catat Servis</span>, dan <span class="g-btn">Catat Odometer</span> di atas tabel tampil sesuai hak RW Anda.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Kendaraan tidak muncul.</b> Belum didaftarkan di Master Kendaraan, atau sudah dinonaktifkan.</li>
    <li><b>Status masih Segera atau Terlambat padahal sudah diperpanjang.</b> Perpanjangan belum dicatat di Perpanjang Dokumen Kendaraan, atau Berlaku sampai yang dimasukkan kurang jauh. Buka Detail kendaraan dan periksa tabel dokumennya.</li>
    <li><b>Servis berikutnya masih lewat padahal sudah diservis.</b> Pekerjaannya belum dicentang di Catat Servis Kendaraan, sehingga jadwalnya belum dihitung ulang.</li>
</ul>

<h3 id="ga-detail">Detail kendaraan</h3>
<h4>Untuk apa</h4>
<p>Memperlihatkan satu kendaraan selengkapnya: dokumen dan masa berlakunya, jadwal perawatan dengan batang kemajuan, dan riwayat servis.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Dari Monitoring Kendaraan, klik 2x baris kendaraan.</li>
    <li>Judulnya berisi plat, nama, dan status kendaraan. Baris di bawahnya memuat jenis, tahun, dan <i>Wajib KIR</i> atau <i>Tidak wajib KIR</i>; kendaraan nonaktif ditandai <i>NONAKTIF</i>.</li>
    <li>Kotak ringkasan: <b>Odometer terakhir</b> (dengan tanggalnya), <b>Pajak tahunan</b> (sisa waktu), <b>Servis berikutnya</b> (pekerjaan terdekat), dan <b>Biaya servis</b> tahun ini untuk kendaraan ini.</li>
    <li>Bagian <b>Dokumen dan masa berlaku</b>: kolom <b>Dokumen</b>, <b>Berlaku sampai</b>, <b>Sisa waktu</b>, <b>No. dokumen</b>, <b>Lampiran</b> (klik namanya untuk melihat foto atau PDF), dan <b>Status</b>. Status <i>Tidak wajib</i> dan <i>Belum dicatat</i> juga muncul di sini.</li>
    <li>Bagian <b>Jadwal perawatan</b>: kolom <b>Pekerjaan</b>, <b>Interval</b>, <b>Terakhir dikerjakan</b>, <b>Berikutnya</b>, <b>Sisa</b>, <b>Kemajuan</b> (batang; penuh berarti tiba waktunya), dan <b>Status</b>.</li>
    <li>Bagian <b>Riwayat servis</b>: <b>Tanggal</b>, <b>Pekerjaan</b>, <b>Bengkel</b>, <b>Odometer</b>, dan <b>Biaya</b>. Yang tampil 30 servis terakhir.</li>
    <li>Tombol <span class="g-btn">Kembali</span> menuju Monitoring Kendaraan. Tombol <span class="g-btn">Perpanjang Dokumen</span>, <span class="g-btn">Catat Servis</span>, dan <span class="g-btn">Catat Odometer</span> membuka form dengan kendaraan ini sudah terpilih (hanya untuk kendaraan aktif dan bila Anda punya hak RW).</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li><b>Masa berlaku dokumen</b> yang dipakai adalah tanggal Berlaku sampai <b>terbesar</b> untuk tiap jenis dokumen.</li>
    <li><b>Jadwal perawatan</b> dihitung dari servis terakhir tiap pekerjaan: odometer ditambah interval km, tanggal ditambah interval bulan; yang tercapai lebih dulu menentukan. Pekerjaan yang belum pernah dicatat dihitung dari odometer awal kendaraan (<i>Belum pernah · acuan ...</i>).</li>
    <li>Bila jenis kendaraan belum punya rencana, layar menulis <i>Jenis kendaraan ini belum punya rencana perawatan. Isi di Jenis Kendaraan.</i></li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Tombol input tidak ada.</b> Kendaraannya nonaktif, atau Grup Anda belum diberi hak RW pada modul pencatatannya.</li>
    <li><b>Lampiran tidak terbuka.</b> Lampiran hanya terbuka bagi pengguna yang punya modul Monitoring Kendaraan dan sudah masuk.</li>
</ul>
@endif

@if ($can('GVD'))
<h3 id="ga-gvd">Perpanjang Dokumen Kendaraan</h3>
<h4>Untuk apa</h4>
<p>Mencatat dokumen kendaraan yang baru dibayar atau diuji, supaya tanggal Berlaku sampai dan status kendaraan ikut diperbarui. Ada empat jenis: pajak tahunan, STNK dan plat 5 tahunan, KIR, dan asuransi.</p>
@include('help.diagram', ['name' => 'ug-ga-perpanjang-servis', 'caption' => 'Dari Monitoring Kendaraan ke Detail kendaraan, lalu tiga form pencatatan dan hasilnya.'])
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya hak <b>RW</b> pada modul Perpanjang Dokumen Kendaraan, biasanya petugas admin GA. Sopir tidak mendapat modul ini.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>GA</b> lalu <b>Perpanjang Dokumen Kendaraan</b>. Dari Detail kendaraan, tekan <span class="g-btn">Perpanjang Dokumen</span> agar kendaraannya sudah terpilih.</li>
    <li>Pilih <b>Kendaraan</b>.</li>
    <li>Pilih <b>Jenis Dokumen</b>: <i>Pajak tahunan</i>, <i>STNK dan plat 5 tahunan</i>, <i>KIR</i>, atau <i>Asuransi</i>.</li>
    <li>Isi <b>Tanggal Bayar / Uji</b> (bawaan hari ini).</li>
    <li><b>Berlaku Sampai</b> terisi sendiri: tanggal bayar ditambah masa berlaku jenis dokumen (pajak 12 bulan, STNK dan plat 60 bulan, KIR 6 bulan, asuransi 12 bulan). Ubah bila tanggal di dokumen berbeda; sesudah Anda mengubahnya, angka itu tidak ditimpa lagi.</li>
    <li>Isi <b>Biaya (IDR)</b>. <b>No. Dokumen</b> dan <b>Catatan</b> boleh kosong (mis. nomor STNK, nomor KIR, atau nomor polis).</li>
    <li>Lampirkan foto atau PDF bukti di <b>Lampiran</b> (dianjurkan, tidak wajib; JPG, PNG, atau PDF, paling besar 5 MB).</li>
    <li>Tekan <b>Simpan</b>. Bila Anda punya Monitoring Kendaraan, layar pindah ke Detail kendaraan dengan pesan <i>diperpanjang sampai ...</i>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Pilihan <b>KIR</b> nonaktif untuk kendaraan yang <b>Wajib KIR</b>-nya Tidak.</li>
    <li>Tanggal Bayar / Uji tidak boleh setelah hari ini. Berlaku Sampai harus setelah tanggal bayar dan paling lama 10 tahun dari hari ini.</li>
    <li>Tiap perpanjangan tersimpan sebagai catatan baru; yang menentukan status adalah Berlaku sampai terbesar untuk jenis itu.</li>
    <li>Hanya kendaraan <b>aktif</b> yang bisa dipilih.</li>
    <li>Layar ini tidak punya tombol ubah atau hapus. Salah mengetik tanggal terlalu jauh? Hubungi Administrator.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Berlaku sampai harus setelah tanggal bayar / uji.</b> Tanggal Berlaku Sampai sama dengan atau sebelum Tanggal Bayar / Uji. Periksa tahunnya.</li>
    <li><b>Kendaraan ini tidak wajib KIR.</b> Kendaraan itu tidak butuh KIR. Ubah Wajib KIR di {!! $ref('GVK', 'ga-gvk', 'Master Kendaraan') !!} bila memang wajib.</li>
    <li><b>Lampiran paling besar 5 MB.</b> Kecilkan foto, atau ambil dengan kualitas lebih rendah.</li>
    <li><b>Layar menolak dibuka (akses hanya-baca).</b> Grup Anda baru RO. Minta Administrator memberi RW.</li>
</ul>
@endif

@if ($can('GVS'))
<h3 id="ga-gvs">Catat Servis Kendaraan</h3>
<h4>Untuk apa</h4>
<p>Mencatat servis yang sudah selesai beserta pekerjaan yang dikerjakan. Jadwal servis berikutnya dihitung sendiri dari tanggal dan odometer servis ini.</p>
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya hak <b>RW</b> pada modul Catat Servis Kendaraan, biasanya petugas admin GA.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>GA</b> lalu <b>Catat Servis Kendaraan</b>, atau tekan <span class="g-btn">Catat Servis</span> di Detail kendaraan.</li>
    <li>Pilih <b>Kendaraan</b>. Daftar pekerjaan di bawahnya berubah mengikuti jenis kendaraan itu.</li>
    <li>Isi <b>Tanggal Servis</b> (bawaan hari ini) dan <b>Odometer (km)</b> (bawaan odometer terakhir; ketik angka yang sebenarnya).</li>
    <li>Centang <b>Pekerjaan yang Dikerjakan</b> dari jadwal perawatan kendaraan itu. Jadwal tiap pekerjaan yang dicentang mulai lagi dari servis ini.</li>
    <li>Isi <b>Bengkel</b> dan <b>Biaya Total (IDR)</b>.</li>
    <li>Isi <b>Catatan</b>. Wajib bila tidak ada pekerjaan yang dicentang (servis di luar jadwal).</li>
    <li>Tekan <b>Simpan</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Servis ikut menaikkan <b>Odometer terakhir</b> kendaraan. Odometer tidak boleh lebih kecil dari catatan terakhir.</li>
    <li>Tanggal Servis tidak boleh setelah hari ini.</li>
    <li>Pekerjaan hanya dari rencana perawatan jenis kendaraan itu. Pekerjaan lain dituliskan di Catatan.</li>
    <li>Modul ini <b>tidak mengurangi stok</b> gudang. Oli atau filter yang diambil dari gudang dicatat sendiri di Pengeluaran Barang.</li>
    <li>Jenis kendaraan belum punya rencana? Layar menulis <i>Jenis kendaraan ini belum punya rencana perawatan</i>. Lengkapi dulu di {!! $ref('GVT', 'ga-gvt', 'Jenis Kendaraan') !!}.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Odometer ... lebih kecil dari catatan terakhir.</b> Pesan menyebut angka dan tanggal terakhir. Periksa angka yang diketik; odometer tidak boleh turun.</li>
    <li><b>Centang pekerjaan yang dikerjakan, atau isi catatan.</b> Tidak ada pekerjaan yang dicentang dan Catatan kosong.</li>
    <li><b>Pekerjaan yang dipilih bukan bagian dari jadwal kendaraan ini.</b> Jenis kendaraan diganti selagi form terbuka. Muat ulang layar.</li>
</ul>
@endif

@if ($can('GVO'))
<h3 id="ga-gvo">Catat Odometer</h3>
<h4>Untuk apa</h4>
<p>Mencatat kilometer yang tertera di panel kendaraan secara cepat, tanpa servis. Sopir memakainya, mis. tiap awal minggu. Jadwal servis dihitung dari angka ini.</p>
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya hak <b>RW</b> pada modul Catat Odometer. Ini satu-satunya modul GA untuk sopir.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>GA</b> lalu <b>Catat Odometer</b>.</li>
    <li>Pilih <b>Kendaraan</b>.</li>
    <li>Isi <b>Tanggal</b> (bawaan hari ini) dan <b>Kilometer</b> (bawaan odometer terakhir; ketik angka di panel).</li>
    <li><b>Dicatat Oleh</b> sudah berisi nama Anda; ubah bila orang lain yang membaca.</li>
    <li>Pilih <b>Foto Odometer</b> (dianjurkan, tidak wajib; JPG, PNG, atau PDF, paling besar 5 MB). Dari ponsel, pilihan kamera muncul saat memilih berkas.</li>
    <li>Tekan <b>Simpan</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Angka foto <b>tidak dibaca otomatis</b>. Ketik kilometernya sendiri; foto hanya bukti.</li>
    <li>Kilometer tidak boleh lebih kecil dari catatan terakhir, dan Tanggal tidak boleh setelah hari ini.</li>
    <li>Tanpa modul Monitoring Kendaraan, layar tetap di form dan menampilkan pesan berhasil.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Odometer lebih kecil dari catatan terakhir.</b> Salah ketik, atau ada catatan sebelumnya yang terlalu besar. Periksa angkanya; bila catatan lamanya yang salah, hubungi Administrator.</li>
    <li><b>Kendaraan tidak ada di pilihan.</b> Kendaraannya nonaktif atau belum didaftarkan.</li>
</ul>
@endif

@if ($can('GVK'))
<h3 id="ga-gvk">Master Kendaraan</h3>
<h4>Untuk apa</h4>
<p>Daftar kendaraan Jays. Plat nomor menjadi kode kendaraan. Kendaraan tidak dihapus supaya riwayatnya tetap ada; yang tidak dipakai lagi dinonaktifkan.</p>
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya modul <b>Master Kendaraan</b>: RO untuk melihat, RW untuk menambah, mengubah, dan menonaktifkan.</p>
<h4>Langkah: menambah kendaraan</h4>
<ol class="g-steps">
    <li>Buka <b>Master Kendaraan</b>, tekan <b>+ Data Baru</b>.</li>
    <li>Isi <b>Plat Nomor</b> (unik, mis. N 1234 AB) dan <b>Nama / Model</b>.</li>
    <li>Pilih <b>Jenis Kendaraan</b>. Jenis ini menentukan rencana perawatan yang dipantau.</li>
    <li>Isi <b>Tahun</b>, <b>No. Rangka</b>, dan <b>No. Mesin</b> bila ada.</li>
    <li>Atur <b>Wajib KIR</b>: <i>Ya</i> untuk small truck atau kendaraan angkutan barang, <i>Tidak</i> untuk mobil penumpang.</li>
    <li>Isi <b>Odometer Awal</b> dengan kilometer yang tertera sekarang. Ini dasar jadwal bagi pekerjaan yang belum pernah diservis.</li>
    <li>Tekan <b>Simpan</b>. Lalu catat dokumennya lewat {!! $ref('GVD', 'ga-gvd', 'Perpanjang Dokumen Kendaraan') !!}.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Setelah disimpan, odometer <b>tidak bisa diketik</b> di master (tampil sebagai <b>Odometer Terakhir</b>). Ia berubah lewat {!! $ref('GVO', 'ga-gvo', 'Catat Odometer') !!} atau {!! $ref('GVS', 'ga-gvs', 'Catat Servis Kendaraan') !!}.</li>
    <li><b>Jenis Kendaraan tidak bisa diganti</b> setelah kendaraan punya riwayat servis, karena jadwalnya mengikuti jenis.</li>
    <li>Menonaktifkan (tombol Aksi di grid atau <b>Nonaktifkan</b> di form) menyembunyikan kendaraan dari Monitoring Kendaraan dan dari pilihan form pencatatan. Riwayatnya tetap.</li>
    <li>Kolom <b>No. Rangka</b> dan <b>No. Mesin</b> di grid tersembunyi; tampilkan lewat tombol <span class="g-btn">Kolom</span>. Kotak di atas grid menyaring <b>Semua kendaraan</b> atau <b>Nonaktif</b>.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Plat sudah dipakai.</b> Plat harus unik. Cari kendaraan itu di grid; mungkin sudah ada dan hanya nonaktif.</li>
    <li><b>Jenis kendaraan tidak bisa diganti.</b> Sudah ada riwayat servis. Nonaktifkan kendaraan ini dan daftarkan lagi dengan jenis yang benar, bila perlu.</li>
    <li><b>Odometer diubah lewat Catat Odometer, bukan di master kendaraan.</b> Pakai layar Catat Odometer.</li>
</ul>
@endif

@if ($can('GVT'))
<h3 id="ga-gvt">Jenis Kendaraan</h3>
<h4>Untuk apa</h4>
<p>Mengelompokkan kendaraan dan menetapkan <b>Rencana Perawatan</b>-nya: pekerjaan apa saja yang dipantau (oli, filter, ban, dan seterusnya) dan berapa intervalnya. Rencana dipasang sekali di jenis, berlaku bagi semua kendaraan berjenis itu, sehingga tidak perlu diketik ulang saat mencatat servis.</p>
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya modul <b>Jenis Kendaraan</b>: RO untuk melihat, RW untuk menambah dan mengubah.</p>
<h4>Langkah: menambah jenis dan rencananya</h4>
<ol class="g-steps">
    <li>Buka <b>Jenis Kendaraan</b>, tekan <b>+ Data Baru</b>.</li>
    <li>Isi <b>Kode Jenis</b> (unik, mis. PICKUP) dan <b>Nama Jenis</b>.</li>
    <li>Di tabel <b>Rencana Perawatan</b>, tekan <b>+ Tambah Pekerjaan</b>. Isi <b>Pekerjaan</b> (mis. Oli mesin), lalu <b>Interval (km)</b>, <b>Interval (bulan)</b>, atau keduanya. Simpan barisnya dengan tombol di ujung baris.</li>
    <li>Ulangi untuk tiap pekerjaan, lalu tekan <b>Simpan</b> di form.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Kalau diisi <b>km dan bulan</b>, yang tercapai lebih dulu menentukan jadwal. Contoh: oli tiap 10.000 km atau 6 bulan.</li>
    <li><b>Kode Jenis</b> terkunci setelah disimpan. Nama jenis boleh diubah.</li>
    <li>Mengganti nama sebuah pekerjaan dianggap pekerjaan baru. Pekerjaan yang dibuang dari tabel tidak dipantau lagi; riwayat servis lamanya tetap terbaca.</li>
    <li>Form menunjukkan <b>Kendaraan pemakai</b>. Grid menunjukkan <b>Butir perawatan</b> dan <b>Kendaraan</b> per jenis.</li>
    <li>Nonaktifkan jenis yang tidak dipakai lagi lewat tombol Aksi atau form.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Isi interval km atau interval bulan (atau keduanya) di setiap butir rencana.</b> Satu pekerjaan butuh minimal satu interval.</li>
    <li><b>Nama pekerjaan yang sama muncul lebih dari sekali.</b> Gabungkan atau beri nama berbeda.</li>
    <li><b>Kendaraan baru berstatus Servis berikutnya "Tidak ada jadwal".</b> Jenisnya belum punya pekerjaan di Rencana Perawatan.</li>
</ul>
@endif

@if ($can('GAM'))
<h3 id="ga-gam">Perawatan AC</h3>
<h4>Untuk apa</h4>
<p>Satu butir menu untuk memantau semua AC aktif, seperti Monitoring Kendaraan: AC mana yang terlambat atau segera jatuh tempo cuci dan servis besar, dan AC mana yang sedang rusak. Dari sini Anda membuka <b>Setup</b> dan <b>Penjadwalan</b>.</p>
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya modul <b>Perawatan AC</b> (RO cukup). Tombol <span class="g-btn">Setup</span> dan <span class="g-btn">Penjadwalan</span> tampil sesuai hak modul yang Anda punya.</p>
<h4>Langkah: membaca Perawatan AC</h4>
<ol class="g-steps">
    <li>Buka <b>GA</b> lalu <b>Perawatan AC</b>.</li>
    <li>Baca empat kotak: <b>AC aktif</b>, <b>Terlambat</b> (cuci atau servis besar sudah lewat), <b>Segera</b> (dalam 30 hari), dan <b>Kerusakan terbuka</b> (AC rusak yang belum selesai ditangani). Di bawah tombol tertulis <i>Kunjungan terdekat</i>.</li>
    <li>Baca tabelnya: <b>AC</b>, <b>Area</b>, <b>Cuci berikutnya</b>, <b>Servis besar</b>, <b>Kerusakan 12 bln</b>, dan <b>Status</b> (<i>Rusak</i> bila ada Breakdown terbuka, selain itu Terlambat, Segera, atau Aman). Klik 2x barisnya untuk membuka Denah AC dengan AC itu terpilih.</li>
    <li>Tekan <span class="g-btn">Setup</span> untuk membuka Denah AC, Master AC, Area dan Gedung, Ruang, Vendor AC, dan Pengaturan. Tekan <span class="g-btn">Penjadwalan</span> untuk menjadwalkan kunjungan, melihat Kunjungan, dan mencatat hasil.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Hanya AC <b>aktif</b> yang tampil. Tombol dan ubin yang modulnya tidak Anda punya tidak tampil.</li>
    <li>Perbaikan Breakdown tidak menggeser jadwal cuci dan servis besar.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Tombol Setup atau Penjadwalan tidak ada.</b> Grup Anda belum diberi modul layar tujuannya. Minta Administrator memeriksa di {!! $can('GRP') ? '<a href="#sec-grp">Grup</a>' : 'Grup' !!}.</li>
    <li><b>AC tidak tampil.</b> Belum didaftarkan di Master AC, atau sudah dinonaktifkan.</li>
</ul>
@endif

@if ($can('GAS'))
<h3 id="ga-gas">Penjadwalan AC</h3>
<h4>Untuk apa</h4>
<p>Menjadwalkan kunjungan vendor untuk membersihkan AC. Layar ini menunjukkan AC mana yang perlu dibersihkan, kalender kunjungan satu minggu, dan sisa kapasitas tiap hari. Hasilnya adalah pesanan <b>Draf</b> yang dikirim dari {!! $ref('GAV', 'ga-gav', 'Kunjungan AC') !!}.</p>
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya modul <b>Penjadwalan AC</b>: RO untuk melihat, RW untuk menjadwalkan, menyusun otomatis, dan mengubah kapasitas harian.</p>
<h4>Langkah: menjadwalkan AC</h4>
<ol class="g-steps">
    <li>Buka <b>GA</b>, <b>Perawatan AC</b>, lalu tekan <span class="g-btn">Penjadwalan</span>. Pilih <b>Tipe</b> <i>Preventif (jadwal rutin)</i> (bawaan) lalu <span class="g-btn">Terapkan</span>. Empat kotak di atas: <b>Total AC</b>, <b>Terlambat dicuci</b>, <b>Jatuh tempo</b> (dalam 30 hari), dan <b>Terjadwal minggu ini</b>.</li>
    <li>Pilih <b>Vendor</b>. Atur juga <b>Area</b> (<i>Semua area</i> atau satu area) dan <b>Tampilkan</b> (<i>AC yang perlu dibersihkan</i> atau <i>Semua AC aktif</i>), lalu tekan <span class="g-btn">Terapkan</span>.</li>
    <li>Di <b>Kalender kunjungan</b> (Senin sampai Sabtu), klik hari kunjungan. Tiap hari menunjukkan jumlah AC terjadwal per kapasitas, mis. <i>2/3</i>, dan keterangan <i>1 slot kosong</i>, <i>Penuh</i>, <i>Sudah lewat</i>, atau <i>Bukan hari kunjungan</i>. Tombol <span class="g-btn">Minggu sebelumnya</span>, <span class="g-btn">Minggu ini</span>, dan <span class="g-btn">Minggu berikutnya</span> menggeser kalender.</li>
    <li>Di bagian <b>AC yang perlu dibersihkan</b>, periksa <b>Tanggal kunjungan terpilih</b> dan <b>Sisa kapasitas hari itu</b>. Atur <b>Jam mulai</b> bila perlu (bawaan jam awal kunjungan).</li>
    <li>Centang AC yang akan dikunjungi. Kolomnya: <b>AC dan lokasi</b>, <b>Terakhir dicuci</b>, <b>Jatuh tempo</b>, <b>Pekerjaan</b>, <b>Status</b>, dan <b>Jadwal</b>. Kotak di kepala kolom mencentang semua yang belum terjadwal.</li>
    <li>Tekan <span class="g-btn">Jadwalkan yang dipilih</span>. Pesan hasilnya menyebut nomor pesanan dan status <i>Draf</i>.</li>
</ol>
<h4>Langkah: menjadwalkan perbaikan (Breakdown)</h4>
<ol class="g-steps">
    <li>Di Penjadwalan, ubah <b>Tipe</b> menjadi <i>Breakdown (AC rusak)</i> lalu tekan <span class="g-btn">Terapkan</span>. Semua AC aktif tampil, bukan hanya yang jatuh tempo.</li>
    <li>Isi <b>Tanggal kunjungan</b>, <b>Jam mulai</b>, <b>Tingkat</b> (<i>Biasa</i> atau <i>Mendesak</i>), dan <b>Keluhan</b> (wajib), mis. <i>Tidak dingin, menetes air</i>.</li>
    <li>Centang AC yang rusak lalu tekan <span class="g-btn">Jadwalkan perbaikan</span>. Hasilnya pesanan <b>Draf</b> bertipe Breakdown yang dikirim dari Kunjungan AC seperti biasa; pesannya menyebut keluhan.</li>
    <li><b>Mendesak</b> boleh melampaui kapasitas harian dan jatuh di hari yang bukan hari kunjungan (mis. hari ini atau Minggu), tetapi <b>Alasan mendesak</b> wajib diisi. <i>Biasa</i> tetap mengikuti kapasitas dan hari kunjungan. Jam tetap dalam jendela kunjungan.</li>
</ol>
<h4>Langkah: Susun otomatis dan kapasitas</h4>
<ol class="g-steps">
    <li>Klik tanggal mulai di kalender, lalu tekan <span class="g-btn">Susun otomatis</span>.</li>
    <li>Semua AC berstatus <b>Terlambat</b> atau <b>Segera</b> yang belum terjadwal dijadwalkan sekaligus, paling terlambat dulu, hari demi hari sesuai kapasitas dan hari kunjungan yang diizinkan. Pilihan Area tidak membatasinya. Hasilnya beberapa pesanan Draf.</li>
    <li>Mengubah kapasitas: pilih <b>Kapasitas harian vendor</b> lalu tekan <b>Simpan kapasitas</b>. Jadwal yang sudah dibuat tidak berubah.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li><b>Kapasitas harian</b> dihitung <b>per vendor</b>, bawaan 3 AC per hari. Dua vendor boleh sama-sama penuh pada hari yang sama.</li>
    <li><b>Hari dan jam kunjungan</b> dibatasi: bawaan Senin sampai Jumat, pukul 09.00 sampai 16.00. Layar menulis hari dan jam yang berlaku di bawah kalender. Mengubahnya di {!! $ref('GAP', 'ga-gap', 'Pengaturan GA') !!}.</li>
    <li><b>Pekerjaan</b> dipilih otomatis: <i>Servis besar</i> bila jatuh tempo servis besar sudah lewat atau dalam 30 hari dari tanggal kunjungan, selain itu <i>Cuci rutin</i>. Servis besar sudah mencakup cuci.</li>
    <li><b>Jatuh tempo</b> sebuah AC adalah yang lebih dulu dari cuci berikutnya (3 bulan sesudah pekerjaan terakhir) dan servis besar berikutnya (12 bulan sesudah servis besar terakhir).</li>
    <li><b>Satu AC hanya boleh punya satu pesanan terbuka.</b> AC yang sudah terjadwal tidak bisa dicentang; kolom <b>Jadwal</b> menunjukkan tanggal dan nomor pesanannya.</li>
    <li>Vendor harus <b>aktif</b>. Tanpa vendor aktif layar menulis <i>Belum ada vendor AC aktif</i>; tambahkan di {!! $ref('GAE', 'ga-gae', 'Vendor AC') !!}.</li>
    <li>Tombol <span class="g-btn">Jadwalkan yang dipilih</span> dan <span class="g-btn">Susun otomatis</span> mati sebelum Anda memilih tanggal.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Tanggal ... sudah penuh.</b> Kapasitas harian vendor habis. Pilih hari lain, atau naikkan kapasitas.</li>
    <li><b>Hari ... tidak diizinkan untuk kunjungan.</b> Pilih hari yang tertulis di bawah kalender.</li>
    <li><b>... sudah terjadwal pada ... Satu AC tidak boleh punya dua pesanan terbuka.</b> Batalkan atau selesaikan pesanan lama di Kunjungan AC dulu.</li>
    <li><b>Jam kunjungan harus antara ... dan ...</b> Ubah Jam mulai.</li>
    <li><b>AC tidak ada di daftar.</b> Statusnya Aman. Pilih <i>Semua AC aktif</i> di Tampilkan.</li>
</ul>
@endif

@if ($can('GAV'))
<h3 id="ga-gav">Kunjungan AC</h3>
<h4>Untuk apa</h4>
<p>Mengurus tiap pesanan kunjungan vendor dari awal sampai tuntas: mengirim pemberitahuan ke vendor, mencatat konfirmasi, menyelesaikan, memindahkan jadwal, membatalkan, melampirkan foto bukti, dan mencetak Berita Acara.</p>
@include('help.diagram', ['name' => 'ug-ga-kunjungan-ac', 'caption' => 'Empat tahap Kunjungan AC, serta Jadwal ulang dan Batalkan.'])
<h4>Siapa yang bisa</h4>
<ul class="g-list">
    <li>Melihat daftar dan layar pesanan, membuka pesan WhatsApp, dan mencetak Berita Acara: pengguna yang Grup-nya punya modul <b>Kunjungan AC</b> (RO cukup).</li>
    <li>Kirim, Tandai dikonfirmasi vendor, Tandai selesai, Jadwal ulang, Batalkan, dan Unggah foto: hak <b>RW</b> pada Kunjungan AC.</li>
</ul>
<h4>Langkah: dari Draf sampai Selesai</h4>
<ol class="g-steps">
    <li>Buka <b>GA</b>, <b>Perawatan AC</b>, <span class="g-btn">Penjadwalan</span>, lalu tombol <span class="g-btn">Kunjungan dan Catat hasil</span>. Grid memuat <b>No. Pesanan</b>, <b>Tipe</b> (<i>Preventif</i> atau <i>Breakdown</i>), <b>Tanggal kunjungan</b>, <b>Jam mulai</b>, <b>Vendor</b>, <b>Jumlah AC</b>, dan <b>Status</b>. Kotak <b>Semua</b>, <b>Draf</b>, <b>Terkirim</b>, <b>Dikonfirmasi vendor</b>, <b>Selesai</b>, dan <b>Dijadwal ulang</b> menyaring daftar. Klik 2x sebuah baris untuk membukanya.</li>
    <li>Layar pesanan menunjukkan empat kotak tahap (<b>1. Draf</b>, <b>2. Terkirim</b>, <b>3. Dikonfirmasi</b>, <b>4. Selesai</b>) beserta waktu dan nama yang melakukannya, lalu tanggal, jam, vendor, dan jumlah AC.</li>
    <li><b>Draf.</b> Baca <b>Pesan untuk vendor</b> di <b>Pratinjau pesan</b>. Tekan <span class="g-btn">Kirim</span>, periksa jendela <b>Kirim pemberitahuan</b>, lalu tekan <b>Kirim</b>. E-mail terkirim ke vendor (bila vendor punya alamat e-mail) dan status menjadi <b>Terkirim</b>.</li>
    <li><b>WhatsApp.</b> Tekan <span class="g-btn">Buka WhatsApp</span>. WhatsApp terbuka dengan pesan yang sudah terisi dan nomor vendor; Anda sendiri menekan kirim di WhatsApp. Tidak ada WhatsApp yang terkirim otomatis.</li>
    <li><b>Terkirim.</b> Setelah vendor menjawab, tekan <span class="g-btn">Tandai dikonfirmasi vendor</span>. Status menjadi <b>Dikonfirmasi vendor</b>. Belum ada balasan? <span class="g-btn">Kirim ulang</span> mengirim e-mail lagi.</li>
    <li><b>Dikonfirmasi vendor.</b> Setelah kunjungan berlangsung, tekan <span class="g-btn">Tandai selesai</span>, isi <b>Tanggal selesai</b> (bawaan tanggal kunjungan, atau hari ini bila kunjungan belum tiba), lalu tekan <b>Tandai selesai</b>. Status menjadi <b>Selesai</b>; jatuh tempo berikutnya tiap AC dihitung dari tanggal ini.</li>
</ol>
<h4>Langkah: Catat hasil per AC dan spare-part</h4>
<ol class="g-steps">
    <li>Saat status <b>Dikonfirmasi vendor</b>, buka bagian <b>Catat hasil per AC</b> di layar pesanan.</li>
    <li>Pilih <b>AC</b>, isi <b>Tanggal dikerjakan</b> (tidak sebelum tanggal kunjungan, tidak setelah hari ini), <b>Kondisi / temuan</b>, dan <b>Biaya jasa</b> (opsional). Pada Breakdown isi juga <b>Penyebab kerusakan</b> dan <b>Tindakan</b>.</li>
    <li>Bila ada penggantian, isi sampai tiga <b>Spare-part diganti</b>: nama bebas (saran muncul dari nama yang pernah dipakai), <b>Jumlah</b>, dan <b>Biaya (IDR)</b> opsional. Kosongkan nama bila tidak ada.</li>
    <li>Tekan <span class="g-btn">Simpan hasil</span>. Hasilnya tampil di kolom <b>Hasil</b> tabel AC. Mencatat ulang AC yang sama menggantikan catatan sebelumnya.</li>
    <li>Bila <b>semua</b> AC pada pesanan sudah tercatat, status otomatis menjadi <b>Selesai</b>. Tombol <span class="g-btn">Tandai semua selesai</span> tetap ada untuk menyelesaikan semua AC sekaligus tanpa rincian.</li>
    <li>Spare-part tidak mengurangi stok gudang.</li>
</ol>
<h4>Langkah: salinan untuk PIC, foto bukti, dan Berita Acara</h4>
<ol class="g-steps">
    <li><b>Salinan untuk PIC area.</b> Bagian ini menyiapkan pesan untuk PIC tiap ruang, disarankan dikirim sehari sebelum kunjungan pukul 15.00. Tekan <span class="g-btn">Buka WhatsApp PIC</span> atau <span class="g-btn">E-mail PIC</span>. Tidak terkirim otomatis. Ruang tanpa PIC tercantum di peringatan <i>Ruang tanpa PIC</i>.</li>
    <li><b>Foto bukti</b> (opsional). Setelah status <b>Dikonfirmasi vendor</b> atau <b>Selesai</b>, pilih <b>AC yang difoto</b>, pilih <b>Foto</b> (<i>Sebelum dikerjakan</i> atau <i>Sesudah dikerjakan</i>), pilih <b>Foto bukti</b> (JPG atau PNG, paling besar 5 MB), lalu tekan <span class="g-btn">Unggah foto</span>. Foto yang sama diganti bila diunggah lagi. Foto tampil di kolom <b>Foto sebelum</b> dan <b>Foto sesudah</b>.</li>
    <li><b>Berita Acara.</b> Tekan <span class="g-btn">Cetak Berita Acara</span>. Halaman A4 terbuka dengan kop perusahaan, daftar AC, kolom <b>Kondisi / catatan</b> untuk ditulis tangan, dan tempat tanda tangan <b>Petugas Vendor</b>, <b>PIC Area</b>, dan <b>Petugas GA</b>. Berita Acara dari pesanan yang belum Selesai bertuliskan bahwa dokumen diisi saat kunjungan.</li>
</ol>
<h4>Langkah: Jadwal ulang dan Batalkan</h4>
<ol class="g-steps">
    <li><span class="g-btn">Jadwal ulang</span> tersedia selama pesanan belum Selesai atau Dibatalkan. Isi <b>Tanggal baru</b>, <b>Jam mulai</b>, dan <b>Alasan</b> (wajib), lalu tekan <b>Jadwal ulang</b>.</li>
    <li>Status menjadi <b>Dijadwal ulang</b> dan konfirmasi sebelumnya dilepas. Tekan <span class="g-btn">Kirim</span> lagi supaya vendor mendapat jadwal baru. Riwayatnya tercatat di <b>Riwayat jadwal ulang</b> (<b>Dipindah pada</b>, <b>Dari</b>, <b>Ke</b>, <b>Alasan</b>, <b>Oleh</b>).</li>
    <li><span class="g-btn">Batalkan</span> menutup pesanan dengan <b>Alasan</b> (wajib). Tekan <b>Batalkan kunjungan</b>. AC-nya bisa dijadwalkan lagi.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li><b>Tahapnya berurutan dan tidak bisa dilompati:</b> Draf, Terkirim, Dikonfirmasi vendor, Selesai. Misalnya Tandai selesai hanya muncul setelah Dikonfirmasi vendor.</li>
    <li>Kirim butuh minimal satu AC, tanggal kunjungan yang belum lewat, dan vendor dengan e-mail atau nomor WhatsApp yang sah. Lengkapi di {!! $ref('GAE', 'ga-gae', 'Vendor AC') !!}.</li>
    <li>Bila e-mail gagal terkirim, status <b>tidak berubah</b> dan muncul pesan <i>E-mail belum bisa dikirim saat ini</i>. Coba lagi nanti atau hubungi Administrator.</li>
    <li>Nomor WhatsApp ditulis bebas (mis. 0812 3456 7890). Sistem mengubahnya ke 62... saat membuat tautan.</li>
    <li>Tanggal selesai tidak boleh sebelum tanggal kunjungan dan tidak boleh setelah hari ini.</li>
    <li>Kapasitas harian vendor tetap dicek saat Jadwal ulang.</li>
    <li>Pesanan <b>Selesai</b> dan <b>Dibatalkan</b> hanya dibaca dan dicetak.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Vendor ... belum punya e-mail maupun nomor WhatsApp yang sah.</b> Lengkapi data vendor.</li>
    <li><b>Tanggal kunjungan ... sudah lewat. Jadwal ulang dulu sebelum mengirim.</b> Pakai Jadwal ulang.</li>
    <li><b>... tidak bisa: pesanan berstatus ... Urutan tahap: Draf, Terkirim, Dikonfirmasi vendor, Selesai.</b> Layar belum dimuat ulang atau orang lain sudah memprosesnya. Muat ulang.</li>
    <li><b>Foto hanya bisa dilampirkan setelah pesanan Dikonfirmasi vendor.</b> Tandai dikonfirmasi dulu.</li>
    <li><b>Tombol aksi tidak tampil.</b> Hak Anda RO. Minta Administrator memberi RW.</li>
</ul>
@endif

@if ($can('GAD'))
<h3 id="ga-gad">Denah AC</h3>
<h4>Untuk apa</h4>
<p>Melihat letak semua AC di sebuah area atau lantai, dengan warna status di tiap titik, lalu membuka kartu satu AC.</p>
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya modul <b>Denah AC</b> (RO cukup). Tombol di kartu AC mengikuti hak Anda pada Penjadwalan AC, Kunjungan AC, dan Master AC.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>GA</b> lalu <b>Denah AC</b>.</li>
    <li>Pilih <b>Area / Lantai</b>, tekan <span class="g-btn">Tampilkan</span>.</li>
    <li>Empat kotak: <b>AC di area ini</b> (keterangannya <i>Denah gambar unggahan</i> atau <i>Denah skematis (asumsi)</i>), <b>Aman</b>, <b>Segera</b>, dan <b>Terlambat</b>.</li>
    <li>Titik hijau berarti Aman, kuning Segera, merah Terlambat. Angka di titik adalah dua angka terakhir kode aset. Klik sebuah titik.</li>
    <li>Kartu AC terbuka di bawah denah: <b>Kode ruang</b>, <b>Kode aset</b>, <b>Tipe</b>, <b>Area</b>, <b>Cuci rutin terakhir</b>, <b>Cuci rutin berikutnya</b>, <b>Servis besar terakhir</b>, <b>Servis besar berikutnya</b>, <b>PIC</b>, <b>WhatsApp PIC</b>, <b>Jadwal</b>, dan <b>Pekerjaan berikutnya</b>.</li>
    <li>Dari kartu: <span class="g-btn">Jadwalkan</span> (membuka Penjadwalan AC dengan AC itu tercentang; hanya bila belum terjadwal), <span class="g-btn">Buka kunjungan</span>, atau <span class="g-btn">Master AC</span>.</li>
    <li>Bagian <b>Riwayat dan foto</b> memuat sampai 10 pekerjaan selesai terakhir dengan nomor pesanan dan fotonya.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Tanpa gambar denah, sistem menggambar <b>denah skematis</b> dari persegi tiap ruang. Unggah gambarnya di {!! $ref('GAA', 'ga-gaa', 'Area dan Gedung AC') !!}.</li>
    <li>Letak titik mengikuti persen posisi yang diisi di Master AC. AC tanpa posisi diletakkan di tengah ruangnya.</li>
    <li>Foto di riwayat hanya bisa dibuka bila Anda juga punya modul Kunjungan AC.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Layar kosong dengan tulisan "Belum ada area".</b> Tambahkan area dan ruang dulu di Area dan Gedung AC.</li>
    <li><b>Titik AC tidak pada tempatnya.</b> Isi <b>Denah: Titik Kiri</b> dan <b>Denah: Titik Atas</b> di {!! $ref('GAK', 'ga-gak', 'Master AC') !!}.</li>
    <li><b>Belum ada pekerjaan selesai di riwayat.</b> Jatuh tempo dihitung dari tanggal awal di Master AC sampai ada kunjungan Selesai.</li>
</ul>
@endif

@if ($can('GAK'))
<h3 id="ga-gak">Master AC</h3>
<h4>Untuk apa</h4>
<p>Daftar unit AC. Setiap unit punya <b>kode aset</b> permanen yang ditempel sebagai stiker di unit, dan berada di sebuah ruang yang punya <b>kode ruang</b>.</p>
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya modul <b>Master AC</b>: RO untuk melihat, RW untuk menambah, mengubah, dan menonaktifkan.</p>
<h4>Langkah: menambah unit AC</h4>
<ol class="g-steps">
    <li>Buka <b>Master AC</b>, tekan <b>+ Data Baru</b>.</li>
    <li><b>Kode Aset</b> sudah terisi usulan berikutnya (mis. AC-0004). Boleh diganti sebelum disimpan, harus unik.</li>
    <li>Pilih <b>Ruang</b>.</li>
    <li>Isi <b>Merek</b>, <b>Tipe</b>, dan <b>Kapasitas</b> (PK). <b>Tanggal Pasang</b> bila diketahui.</li>
    <li>Isi <b>Cuci Rutin Terakhir (awal)</b> dan <b>Servis Besar Terakhir (awal)</b>: tanggal pekerjaan itu <b>sebelum</b> tercatat di AdminKU. Ini dasar jatuh tempo sampai ada kunjungan Selesai. Kosong pada Servis Besar berarti dihitung dari Tanggal Pasang.</li>
    <li>Isi <b>Denah: Titik Kiri</b> dan <b>Denah: Titik Atas</b> (persen dari denah) untuk menaruh titik AC; kosong berarti di tengah ruang. Kedua isian harus diisi bersama.</li>
    <li>Tekan <b>Simpan</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li><b>Kode aset versus kode ruang.</b> Kode aset (mis. <code>AC-0004</code>) menempel pada unit dan <b>tidak berubah</b> walau AC dipindah; setelah disimpan terkunci. Kode ruang (mis. <code>MTG</code>) milik ruangnya. AC ditulis di layar sebagai <code>MTG · AC-0004</code> (kode ruang, lalu kode aset). Memindahkan AC = mengganti <b>Ruang</b>, maka kode ruangnya berubah dan kode asetnya tetap.</li>
    <li>AC yang masih punya <b>pesanan kunjungan terbuka</b> tidak bisa dinonaktifkan. Batalkan atau selesaikan kunjungannya dulu.</li>
    <li>AC nonaktif tidak ikut jadwal dan denah.</li>
    <li>Kolom <b>Cuci awal</b> dan <b>Servis besar awal</b> di grid tersembunyi; tampilkan lewat <span class="g-btn">Kolom</span>.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Kode aset ini sudah dipakai unit AC lain.</b> Pakai kode lain.</li>
    <li><b>Kode aset tidak bisa diubah: stikernya sudah menempel di unit.</b> Pindahkan AC dengan mengganti ruangnya.</li>
    <li><b>Tanggal ... tidak boleh setelah hari ini.</b> Tanggal pasang dan tanggal awal tidak boleh di masa depan.</li>
    <li><b>Ruang tidak ada di pilihan.</b> Ruangnya nonaktif atau belum dibuat di {!! $ref('GAR', 'ga-gar', 'Ruang AC') !!}.</li>
</ul>
@endif

@if ($can('GAA'))
<h3 id="ga-gaa">Area dan Gedung AC</h3>
<h4>Untuk apa</h4>
<p>Mengelompokkan ruang menurut area, gedung, atau lantai (mis. Kantor Lt. 1, Gudang, Workshop) dan menyimpan gambar denahnya.</p>
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya modul <b>Area dan Gedung AC</b>: RO untuk melihat, RW untuk menambah, mengubah, dan menonaktifkan.</p>
<h4>Langkah: menambah area</h4>
<ol class="g-steps">
    <li>Buka <b>Area dan Gedung AC</b>, tekan <b>+ Data Baru</b>.</li>
    <li>Isi <b>Nama Area / Gedung</b>. <b>Kode Area</b> boleh dikosongkan; sistem membuatnya dari nama.</li>
    <li>Pilih gambar di <b>Denah (gambar)</b> bila ada (JPG atau PNG, paling besar 5 MB). Tanpa gambar, denah digambar skematis dari persegi ruang.</li>
    <li>Tekan <b>Simpan</b>. Tabel <b>Ruang di area ini</b> memuat <b>Kode</b>, <b>Ruang</b>, <b>PIC</b>, dan <b>Status</b>.</li>
    <li>Mengganti atau membuang gambar: unggah gambar baru, atau ubah <b>Hapus Denah Unggahan</b> menjadi <i>Hapus saat disimpan</i> untuk kembali ke denah skematis.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Area yang masih punya <b>ruang aktif</b> tidak bisa dinonaktifkan: <i>Area masih punya ruang aktif. Nonaktifkan ruangnya dulu.</i></li>
    <li>Titik AC dan persegi ruang memakai persen dari gambar, jadi tetap tepat walau gambarnya diganti dengan ukuran lain, selama letak ruang di gambar baru sama.</li>
    <li>Gambar denah hanya terlihat oleh pengguna yang punya modul Denah AC atau Area dan Gedung AC.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Gambar ditolak.</b> Pakai JPG atau PNG, paling besar 5 MB.</li>
    <li><b>Titik AC bergeser setelah ganti gambar.</b> Sesuaikan persegi ruang dan titik AC dengan gambar baru.</li>
</ul>
@endif

@if ($can('GAR'))
<h3 id="ga-gar">Ruang AC</h3>
<h4>Untuk apa</h4>
<p>Mendaftarkan ruang di tiap area beserta <b>PIC</b> (penanggung jawab ruang) yang menerima salinan pemberitahuan kunjungan dan menandatangani Berita Acara.</p>
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya modul <b>Ruang AC</b>: RO untuk melihat, RW untuk menambah, mengubah, dan menonaktifkan.</p>
<h4>Langkah: menambah ruang</h4>
<ol class="g-steps">
    <li>Buka <b>Ruang AC</b>, tekan <b>+ Data Baru</b>.</li>
    <li>Pilih <b>Area / Gedung</b> dan isi <b>Nama Ruang</b> (mis. Ruang Meeting).</li>
    <li><b>Kode Ruang</b> boleh dikosongkan; sistem membuatnya dari huruf depan nama (Ruang Meeting menjadi RM). Boleh diganti, harus unik.</li>
    <li>Atur persegi ruang di denah: <b>Denah: Kiri</b>, <b>Denah: Atas</b>, <b>Denah: Lebar</b>, <b>Denah: Tinggi</b> (persen, 0 sampai 100; kiri + lebar dan atas + tinggi tidak lebih dari 100). Ruang baru mendapat persegi bawaan di tempat kosong berikutnya.</li>
    <li>Isi <b>PIC (Penanggung Jawab)</b>, <b>WhatsApp PIC</b>, dan <b>E-mail PIC</b>.</li>
    <li>Tekan <b>Simpan</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Ruang dengan PIC yang sama digabung dalam satu pesan salinan di Kunjungan AC. Ruang tanpa PIC diperingatkan.</li>
    <li>Mengubah <b>Kode Ruang</b> mengubah tulisan semua AC di ruang itu (<code>KODE · AC-nnnn</code>).</li>
    <li>Ruang yang masih punya <b>AC aktif</b> tidak bisa dinonaktifkan: pindahkan atau nonaktifkan ACnya dulu.</li>
    <li>Grid memuat <b>Ruang</b>, <b>Area</b>, <b>AC</b> (jumlah), <b>PIC</b>, dan <b>WhatsApp PIC</b>; <b>E-mail PIC</b> tersembunyi.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Kode ruang sudah dipakai.</b> Kode harus unik di seluruh area.</li>
    <li><b>Persegi ruang keluar dari denah.</b> Kurangi lebar atau tinggi, atau geser Kiri atau Atas.</li>
    <li><b>Tidak bisa menonaktifkan ruang.</b> Masih ada AC aktif di dalamnya.</li>
</ul>
@endif

@if ($can('GAE'))
<h3 id="ga-gae">Vendor AC</h3>
<h4>Untuk apa</h4>
<p>Daftar vendor jasa perawatan AC beserta nomor WhatsApp dan e-mail yang dipakai untuk pemberitahuan kunjungan. Vendor AC berdiri sendiri dan tidak bercampur dengan Suppliers barang.</p>
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya modul <b>Vendor AC</b>: RO untuk melihat, RW untuk menambah, mengubah, dan menonaktifkan.</p>
<h4>Langkah: menambah vendor</h4>
<ol class="g-steps">
    <li>Buka <b>Vendor AC</b>, tekan <b>+ Data Baru</b>.</li>
    <li><b>Kode Vendor</b> sudah terisi usulan berikutnya (mis. VND-001); boleh diganti, harus unik.</li>
    <li>Isi <b>Nama Vendor</b> dan <b>Nama Kontak</b>. Nama kontak dipakai untuk sapaan di pesan.</li>
    <li>Isi <b>WhatsApp</b> (mis. 0812 3456 7890) dan <b>E-mail</b>. Isi keduanya bila ada.</li>
    <li>Tekan <b>Simpan</b>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Pemberitahuan butuh minimal salah satu: e-mail atau nomor WhatsApp yang sah. Tanpa keduanya, tombol Kirim di Kunjungan AC menolak.</li>
    <li>Vendor yang <b>nonaktif</b> tidak muncul di Penjadwalan AC.</li>
    <li>Kapasitas harian berlaku per vendor, jadi tiap vendor punya jatah AC per hari sendiri.</li>
    <li>Grid memuat <b>Vendor</b>, <b>Kontak</b>, <b>WhatsApp</b>, dan <b>E-mail</b>.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Kode vendor sudah dipakai.</b> Pakai kode lain.</li>
    <li><b>Tombol Buka WhatsApp tidak ada di Kunjungan AC.</b> Nomor WhatsApp vendor kosong atau kurang dari 9 angka.</li>
    <li><b>Vendor tidak ada di Penjadwalan AC.</b> Vendornya nonaktif.</li>
</ul>
@endif

@if ($can('GAP'))
<h3 id="ga-gap">Pengaturan GA <span class="g-role">hanya Supervisor ke atas</span></h3>
<h4>Untuk apa</h4>
<p>Satu formulir untuk mengatur aturan kerja GA tanpa mengubah database: kapan kendaraan dan AC dianggap Segera, seberapa sering AC dicuci, hari dan jam kunjungan vendor, dan kapasitas harian. Berlaku segera.</p>
<h4>Siapa yang bisa</h4>
<p>Peran Supervisor atau Administrator yang Grup-nya punya hak <b>RW</b> pada modul Pengaturan GA. Data Entry tidak bisa memakainya. Hak ini sengaja tidak dibagikan otomatis; Administrator menunjuk Supervisor yang boleh lewat {!! $can('GRP') ? '<a href="#sec-grp">Grup</a>' : 'Grup' !!}.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka <b>GA</b> lalu <b>Pengaturan GA</b>. Isian terisi nilai yang sedang berlaku.</li>
    <li>Bagian <b>Kendaraan</b>: <b>Segera Jatuh Tempo (hari)</b> (1 sampai 365, bawaan 30) dan <b>Segera Servis (km)</b> (100 sampai 50.000, bawaan 1.000).</li>
    <li>Bagian <b>Perawatan AC</b>: <b>Cuci Rutin Tiap (bulan)</b> (bawaan 3), <b>Servis Besar Tiap (bulan)</b> (bawaan 12), dan <b>Segera Jatuh Tempo (hari)</b> (bawaan 30).</li>
    <li>Centang <b>Hari Kunjungan</b> (minimal satu; bawaan Senin sampai Jumat). Isi <b>Jam Mulai Kunjungan</b> (bawaan 09:00) dan <b>Jam Akhir Kunjungan</b> (bawaan 16:00).</li>
    <li>Isi <b>Kapasitas AC per Hari</b> (1 sampai 20, bawaan 3) dan <b>Jam Pesan PIC Siap Kirim</b> (bawaan 15:00).</li>
    <li>Tekan <b>Simpan</b>. Pesan hasilnya menyatakan pengaturan baru dipakai; <i>Tidak ada perubahan</i> bila tak ada yang berubah.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Jadwal dan pesanan yang <b>sudah dibuat tidak berubah</b>. Pengaturan baru dipakai oleh penjadwalan, status, dan pesan berikutnya.</li>
    <li>Interval servis besar tidak boleh lebih pendek dari interval cuci rutin. Jam akhir harus setelah jam mulai.</li>
    <li>Kapasitas harian juga bisa diubah dari layar Penjadwalan AC (<b>Kapasitas harian vendor</b>); yang tersimpan sama.</li>
    <li>Setiap perubahan dicatat di log aktivitas dengan nilai lama dan barunya.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Interval servis besar tidak boleh lebih pendek dari interval cuci rutin.</b> Naikkan Servis Besar Tiap atau turunkan Cuci Rutin Tiap.</li>
    <li><b>Jam akhir harus setelah jam mulai.</b> Periksa kedua jam.</li>
    <li><b>Pilih minimal satu hari kunjungan.</b> Centang setidaknya satu hari.</li>
    <li><b>Menu Pengaturan GA tidak ada.</b> Grup Anda belum diberi modulnya, atau peran Anda Data Entry.</li>
</ul>
@endif
@endif
