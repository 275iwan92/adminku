{{-- REMARK
 Tujuan       : Panduan Pengguna bagian 9a HR (kepegawaian, tanpa gaji): Karyawan (profil, pekerjaan, dokumen, keluarga, kontrak, Nonaktifkan dengan alasan), Rekrutmen (kandidat dengan kunci NIK, papan kanban, Setup tahap), Bagian (layar Departemen), dan hak akses lewat Grup.
                Pola tetap tiap modul: Untuk apa, Siapa yang bisa, Langkah, Yang perlu diperhatikan, Masalah umum. Istilah sama persis dengan label di layar.
 Sumber hulu  : EmployeeController / employees, Employee, EmployeeRequest, Modules (kode HRE, peran minimal), User::canAccess / canWrite, Menu::layers (Bagian = departments.index; Rekrutmen dan Kedisiplinan sementara).
 Dipakai oleh : help/user-guide.blade.php (hanya bila pengguna punya modul HRE atau HRR). Tidak ada diagram.
 Status       : Aktif.
--}}
@if ($anyHr)
<h2 id="hr">9a. HR</h2>
<p>Menu HR di AdminKU mencatat <b>kepegawaian</b>: siapa karyawannya, di bagian mana, apa dokumen dan kontraknya. HR di sini <b>tidak</b> mengurus gaji, absensi, atau cuti.</p>
<table class="g-table">
    <thead><tr><th>Layar</th><th>Isi singkat</th></tr></thead>
    <tbody>
        <tr><td><a href="#hr-hre">Karyawan</a></td><td>Daftar karyawan dan kartunya: profil, pekerjaan, dokumen, keluarga, kontrak</td></tr>
        @if ($can('HRR'))<tr><td><a href="#hr-hrr">Rekrutmen</a></td><td>Kandidat dengan kunci NIK KTP: papan kanban per tahap, daftar dengan pencarian, jadwal dan hasil tiap tahap</td></tr>@endif
        <tr><td>Bagian</td><td>Sama dengan <b>Departemen</b> di Data Master (satu daftar yang sama); jumlah karyawan tiap bagian ikut tampil</td></tr>
    </tbody>
</table>
<ul class="g-list">
    <li><b>Hak HR datang dari Grup, bukan dari peran.</b> Menu HR hanya tampil bila Grup Anda diberi modul <b>Karyawan</b> (RO untuk membaca, RW untuk menyimpan). Administrator melihat semuanya.</li>
    <li>Menu HR milik Administrator juga memuat <b>Kedisiplinan</b>. Itu layar sementara bertuliskan "Segera hadir"; belum bisa dipakai.</li>
    <li>Data karyawan (NIK KTP, NPWP, alamat) bersifat pribadi: berikan modul Karyawan hanya kepada petugas HR.</li>
</ul>

@if ($can('HRE'))
<h3 id="hr-hre">Karyawan</h3>
<h4>Untuk apa</h4>
<p>Menyimpan data pokok kepegawaian satu karyawan dan memantau yang perlu ditindaklanjuti: kontrak yang hampir habis, dokumen (misalnya SIM) yang hampir habis, dan data yang belum lengkap.</p>
<h4>Siapa yang bisa</h4>
<p>Peran Supervisor ke atas yang Grup-nya punya modul <b>Karyawan</b> (RO untuk membaca, RW untuk menambah dan mengubah).</p>
<h4>Langkah: menambah karyawan</h4>
<ol class="g-steps">
    <li>Buka <b>HR</b> lalu <b>Karyawan</b>, tekan <span class="g-btn">New</span>.</li>
    <li>Tab <b>Profil</b>: isi nama, jenis kelamin, tanggal lahir, NIK KTP (16 angka), HP, wilayah dan alamat KTP, serta kontak darurat. Kode karyawan (E-0001 dan seterusnya) terisi sendiri.</li>
    <li>Tab <b>Pekerjaan</b>: pilih <b>Bagian</b>, isi jabatan, <b>Status Kerja</b> (Tetap, Kontrak, Harian, Magang), tanggal masuk, dan <b>Akun Jays</b> bila karyawan ini memakai AdminKU.</li>
    <li>Tab <b>Dokumen</b>: tambah KTP, KK, NPWP, SIM, BPJS, dan lainnya dengan nomor dan Berlaku s/d (bila ada). Tab <b>Keluarga</b> dan <b>Kontrak</b> diisi dengan cara sama.</li>
    <li>Tekan <span class="g-btn">Save</span>.</li>
</ol>
<h4>Langkah: karyawan keluar</h4>
<ol class="g-steps">
    <li>Buka karyawannya, tab <b>Pekerjaan</b>. Pilih <b>Alasan Keluar</b> dan isi <b>Tanggal Efektif Keluar</b> (catatan boleh kosong).</li>
    <li>Tekan <span class="g-btn">Deactivate</span>. Akun Jays karyawan itu ikut nonaktif (akun Anda sendiri tidak).</li>
    <li>Salah tekan? <span class="g-btn">Activate</span> mengembalikannya. Alasan <b>Meninggal</b> tidak bisa diaktifkan kembali.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Karyawan <b>tidak dihapus</b>; riwayatnya tetap tersimpan. Karyawan yang keluar tampil di daftar dengan status <b>Keluar: alasan</b>.</li>
    <li>Kolom <b>Perhatian</b> menampilkan satu catatan terburuk: kontrak terbaru yang habis dalam 60 hari (atau sudah lewat), dokumen yang habis dalam 30 hari, atau <b>Data belum lengkap</b> (NIK, wilayah KTP, atau HP kosong).</li>
    <li>Kode karyawan tidak bisa diubah setelah disimpan. NIK KTP harus unik.</li>
    <li>Berkas pindaian dokumen belum disimpan di sini; yang dicatat hanya nomor dan masa berlakunya.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Bagian tidak ada di pilihan.</b> Tambahkan dulu di Departemen (Data Master) atau aktifkan kembali.</li>
    <li><b>Akun Jays tidak ada di pilihan.</b> Akun itu sudah dipakai karyawan lain, atau nonaktif.</li>
    <li><b>Deactivate ditolak.</b> Alasan keluar atau tanggal efektif belum diisi.</li>
</ul>
@endif
@if ($can('HRR'))
<h3 id="hr-hrr">Rekrutmen</h3>
<h4>Untuk apa</h4>
<p>Mendata kandidat (kunci <b>NIK KTP</b>) dan mengikuti prosesnya dari Daftar sampai Diterima: kapan tes dan interview, siapa penilainya, dan hasilnya.</p>
<h4>Siapa yang bisa</h4>
<p>Peran Supervisor ke atas yang Grup-nya punya modul <b>Rekrutmen</b> (RO untuk membaca, RW untuk menambah, memindah, dan mengubah).</p>
<h4>Langkah: mendaftarkan kandidat</h4>
<ol class="g-steps">
    <li>Buka <b>HR</b> lalu <b>Rekrutmen</b>, tekan <span class="g-btn">Kandidat</span>.</li>
    <li>Isi <b>NIK KTP</b> (16 angka), nama, dan <b>Bagian Dilamar</b> (boleh lebih dari satu). HP, e-mail, pendidikan, sumber, foto KTP, dan CV boleh diisi. Di HP, Pilih File bisa langsung membuka kamera.</li>
    <li>Tekan <span class="g-btn">Save</span>. Kandidat baru selalu mulai dari tahap <b>Daftar</b>.</li>
</ol>
<h4>Langkah: mengelola proses</h4>
<ol class="g-steps">
    <li>Di <b>Papan</b>, seret kartu ke tahap mana pun (boleh loncat atau mundur). Tiap perpindahan tercatat sebagai satu baris riwayat.</li>
    <li>Klik 2x kartu untuk membuka kartu kandidat. Di tab <b>Proses</b> isi tanggal, jam, penilai, dan ubah <b>Hasil</b> menjadi Lulus atau Tidak lulus.</li>
    <li>Kandidat di tahap <b>Diterima</b> punya tombol <span class="g-btn">Jadikan karyawan</span>: form Karyawan terbuka dengan data kandidat terisi; periksa lalu Save.</li>
    <li>Tombol <span class="g-btn">Daftar</span> menampilkan semua kandidat dengan pencarian NIK atau nama dan filter tahap, status, sumber, dan hasil.</li>
    <li>Tombol <span class="g-btn">Setup</span> mengatur urutan tahap. Daftar dan Diterima tetap di awal dan akhir; tahap tengah bebas ditambah, diganti nama, dan diurutkan.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>NIK yang pernah melamar atau sudah jadi karyawan diberi peringatan di kartu kandidat. Melamar lagi dicatat sebagai baris baru.</li>
    <li>Kandidat tidak dihapus: ubah <b>Status</b> menjadi Ditolak atau Mundur, maka kartunya hilang dari papan tetapi tetap ada di Daftar.</li>
    <li>Foto KTP hanya disimpan sebagai lampiran; NIK dan nama diketik. Berkas hanya bisa dibuka pemegang hak Rekrutmen.</li>
    <li>Tahap yang sudah dipakai kandidat tidak dihapus, hanya dinonaktifkan.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Kandidat tidak muncul di papan.</b> Statusnya Ditolak atau Mundur (lihat di Daftar), atau tahapnya dinonaktifkan di Setup.</li>
    <li><b>Simpan ditolak.</b> NIK bukan 16 angka, atau belum ada bagian yang dipilih.</li>
</ul>
@endif
@endif
