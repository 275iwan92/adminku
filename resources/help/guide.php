<?php

/* REMARK
 * Tujuan       : Satu-satunya sumber isi tiga mode Panduan Pengguna (Presentasi bertingkat, Interaktif, Blog): kelompok modul dan "tur" (satu tugas, 3-7 langkah) beserta tangkapan layar, titik langkah, alur, dan tautan ke bagian Detail.
 * Struktur     : groups[kunci] = [judul, tagline, hue, gambar sampul, kode modul pemilik (any-of; kosong = selalu tampil), sub-judul]
 *                tours[id]    = [group, judul, ringkasan, kode modul yang dibutuhkan (any-of; kosong = selalu), gambar, anchor Detail, diagram SVG (resources/help/diagrams) atau null,
 *                                alur (daftar label pendek), langkah [[x%, y%, judul, teks]...], tips (daftar teks), terkait (id tur)]
 *                x% dan y% = posisi titik pada tangkapan layar 1440x900 (public/media/ug/{gambar}.png); gambar diambil dari data demo PT XYZ dan diambil ulang bila tampilan berubah.
 * Dipakai oleh : App\Support\GuideCatalog (menyaring per hak akses), help/ug/mode-*.blade.php, tests/Feature/GuideModesTest.
 * Status       : Aktif. Teks langkah harus sama dengan label di layar dan selaras dengan bagian Detail.
 */
return [
    'groups' => [
        'dasar' => ['Dasar AdminKU', 'Beranda, Grid, dan Form: pola yang sama di semua menu.', 150, 'home', [], 'Mulai di sini'],
        'alur' => ['Alur Kerja', 'Dari input data, persetujuan, sampai baca laporan: satu jalur utuh.', 280, 'recv', [], 'Input, approval, laporan'],
        'stok' => ['Stok','Barang masuk, keluar, dan hitung fisik, sisa stok dihitung otomatis.', 165, 'products', ['ITM', 'RCV', 'ISS', 'ADJ', 'SO', 'ORD', 'OPN'], 'Gudang dan opname'],
        'beli' => ['Pembelian', 'Dari permintaan sampai pesanan dan barang datang.', 215, 'po', ['PUP', 'PUK', 'PUO', 'PUV'], 'PP, PO, PV'],
        'kirim' => ['Pengiriman', 'Surat jalan dan rencana kirim barang keluar.', 35, 'delnotes', ['SJL', 'PGK'], 'Surat jalan dan trip'],
        'kas' => ['Keuangan', 'Uang masuk, uang keluar, dan arus kas per rekening.', 95, 'cashoutnew', ['KSM', 'KSK', 'KSR', 'R-KFLOW', 'R-KIN', 'R-KOUT'], 'Kas dan laporan kas'],
        'sdm' => ['SDM', 'Karyawan, rekrutmen, dan catatan kedisiplinan.', 320, 'employees', ['HRE', 'HRR', 'HRD'], 'Kepegawaian'],
        'ga' => ['Personalia (GA)', 'Perawatan kendaraan dan AC sebelum terlambat.', 190, 'fleet', ['GVM', 'GAM', 'GVS', 'GAS'], 'Kendaraan dan AC'],
        'lap' => ['Laporan', 'Baca stok, riwayat, dan kedaluwarsa kapan saja.', 260, 'summary', ['R-SUM', 'R-CARD', 'R-IN', 'R-OUT', 'R-EXP', 'R-ADJ', 'STB'], 'Stok dan riwayat'],
        'sec' => ['Keamanan', 'Pengguna, grup, dan hak akses.', 0, 'users', ['USR', 'GRP'], 'Administrator'],
    ],

    'tours' => [
        'beranda' => ['dasar', 'Kenali Beranda', 'Beranda memberi peringatan supaya Anda tahu apa yang perlu dikerjakan hari ini.', [], 'home', 'beranda', 'ug-pesan-ulang',
            ['Buka Beranda', 'Lihat kotak ringkasan', 'Klik untuk membuka menu', 'Buka bantuan'],
            [
                [26, 24, 'Kotak ringkasan', 'Klik kotak untuk membuka menu terkait.'],
                [27, 33, 'Panduan Pengguna', 'Tombol ini membuka panduan. F1 di layar mana pun sama.'],
                [14, 95, 'Menu profil', 'Klik nama Anda: Notifikasi, Profil, Bahasa, Password, Keluar.'],
            ],
            ['Barang di titik pesan ulang punya tombol Catat Pemesanan di Beranda.', 'Beranda juga menampilkan jadwal Stok Opname dan barang hampir kedaluwarsa.'],
            ['grid']],

        'grid' => ['dasar', 'Kuasai Grid', 'Cari, saring, urutkan, pilih kolom, dan ekspor ke Excel dari satu layar daftar.', [], 'products', 'grid', null,
            ['Pilih kotak hitung', 'Cari kata', 'Urutkan kolom', 'Ekspor CSV'],
            [
                [14, 4, 'Kotak hitung', 'Klik satu kotak untuk menyaring. Klik Semua untuk kembali.'],
                [11, 9, 'Cari', 'Ketik sebagian kata, daftar langsung menyempit.'],
                [55, 14, 'Judul kolom', 'Klik judul kolom untuk mengurutkan naik atau turun.'],
                [81, 9, 'Kolom', 'Pilih kolom yang tampil. Hanya mengubah tampilan Anda.'],
                [88, 9, 'Ekspor', 'Unduh isi grid sebagai CSV untuk Excel.'],
                [49, 20, 'Klik 2x pada baris', 'Membuka data di form, sama dengan ikon pensil.'],
            ],
            ['Baris PESAN ULANG berwarna merah muda: stok sudah di titik pesan ulang.', 'Data tidak dihapus. Yang tidak dipakai dinonaktifkan dan tetap ada riwayatnya.'],
            ['form']],

        'form' => ['dasar', 'Isi Form dengan Cepat', 'Tombol plus, baris barang, dan Simpan: cara kerja form dokumen stok.', [], 'recv', 'form', 'ug-siklus-dokumen',
            ['Pilih pemasok', 'Tambah barang', 'Isi jumlah', 'Simpan jadi Draf'],
            [
                [32.4, 22, 'Tombol plus', 'Pemasok belum ada? Tekan + dan isi kotak kecil. Langsung terpilih.'],
                [93, 39, '+ Tambah Barang', 'Menambah baris barang ke dokumen.'],
                [16, 46, 'Cari barang', 'Ketik sebagian kode atau nama, lalu pilih dari daftar.'],
                [88, 3.6, 'Simpan', 'Simpan membuat Draf. Stok belum berubah sebelum dikonfirmasi.'],
            ],
            ['Isian bertanda bintang merah wajib diisi.', 'Setelah Terkonfirmasi, dokumen terkunci. Koreksi lewat Batalkan atau Penyesuaian Stok.'],
            ['pengeluaran']],

        'alur-input' => ['alur', 'Alur Input Data', 'Dari data acuan, baris barang, Simpan jadi Draf, sampai Konfirmasi membuat stok berubah.', [], 'recv', 'siklus', 'ug-siklus-dokumen',
            ['Siapkan Data Master', 'Isi dokumen', 'Simpan jadi Draf', 'Konfirmasi ke stok'],
            [
                [32.4, 22, 'Isi data acuan', 'Pemasok, penerima, dan departemen dipilih dari Data Master. Belum ada? Tekan +.'],
                [93, 39, 'Isi baris barang', '+ Tambah Barang, pilih barang, lalu isi jumlah.'],
                [88.3, 3.6, 'Simpan jadi Draf', 'Nomor dokumen dibuat otomatis. Stok belum berubah.'],
                [28, 3.6, 'Penunjuk tahap', 'Draf, Terkonfirmasi, Ditutup. Menyala sesuai posisi dokumen.'],
            ],
            ['Draf boleh diubah atau dihapus. Konfirmasi memproses dokumen ke stok lalu menguncinya.', 'Konfirmasi butuh minimal satu baris barang terisi.'],
            ['alur-approval', 'alur-laporan']],

        'alur-approval' => ['alur', 'Alur Persetujuan (Approval)', 'Dokumen yang butuh keputusan Supervisor: diajukan, disetujui atau ditolak, lalu berlaku.', [], 'cashoutnew', 'siklus', 'ug-pembatalan',
            ['Simpan Draf', 'Confirm', 'Menunggu Supervisor', 'Disetujui atau Ditolak'],
            [
                [36.1, 4.4, 'Jalur persetujuan', 'Draf, Terkonfirmasi, Persetujuan, Disetujui, lalu Ditutup.'],
                [68, 54.4, 'Lengkapi sebelum diajukan', 'Isi jumlah dan bukti dulu: dokumen terkunci setelah Confirm.'],
                [86.4, 4.4, 'Simpan, lalu Confirm', 'Simpan membuat Draf. Confirm memberi nomor dan mengirim ke antrean Supervisor.'],
                [42.8, 37.1, 'Dicek saat Approve', 'Saldo kas yang dipakai dicek ulang saat Supervisor menyetujui.'],
            ],
            ['Supervisor yang menyetujui bukan pembuat dokumen. Penolakan wajib beralasan.', 'Pembatalan dokumen stok: Supervisor langsung membatalkan, Data Entry mengajukan dan menunggu Setujui atau Tolak.'],
            ['alur-input', 'alur-laporan']],

        'alur-laporan' => ['alur', 'Alur Baca Laporan', 'Setelah dokumen Terkonfirmasi, stok terhitung dan siap dibaca di laporan.', ['R-SUM', 'R-CARD', 'R-IN', 'R-OUT', 'R-EXP', 'STB'], 'summary', 'laporan', 'ug-alur-kerja',
            ['Konfirmasi dokumen', 'Pilih laporan', 'Atur periode', 'Cetak atau ekspor'],
            [
                [6.3, 6.6, 'Pilih periode', 'Atur tanggal dan kategori yang ingin dibaca.'],
                [51, 9.6, 'Tampilkan', 'Menampilkan pratinjau laporan.'],
                [74.6, 21.6, 'Kelompokkan', 'Tanpa, Per Kategori, atau Per Status.'],
                [95.4, 21.6, 'Cetak', 'Mencetak atau menyimpan sebagai PDF.'],
            ],
            ['Laporan kosong? Mungkin dokumennya masih Draf. Hanya dokumen Terkonfirmasi yang masuk stok.', 'Kartu Stok menunjukkan riwayat satu barang, Ringkasan Stok menunjukkan semua barang.'],
            ['alur-input']],

        'pengeluaran' => ['stok', 'Keluarkan Barang', 'Catat barang keluar ke penerima. Stok tidak boleh menjadi minus.', ['ISS'], 'issue', 't-iss', 'ug-siklus-dokumen',
            ['Pilih penerima', 'Tambah barang', 'Isi jumlah', 'Konfirmasi'],
            [
                [32.4, 22, 'Penerima', 'Pilih penerima barang. Belum ada? Tekan +.'],
                [62, 22, 'Departemen', 'Kosong berarti departemen bawaan penerima.'],
                [93, 36, '+ Tambah Barang', 'Satu barang hanya boleh muncul sekali per dokumen.'],
                [8.8, 43.5, 'Cari barang', 'Kolom Tersedia menunjukkan stok yang boleh dikeluarkan.'],
                [88, 3.6, 'Simpan', 'Simpan menjadi Draf. Konfirmasi membuat stok berkurang.'],
            ],
            ['Kolom abu-abu dihitung otomatis.'],
            ['opname']],

        'opname' => ['stok', 'Jadwalkan Stok Opname', 'Hitung fisik rutin supaya stok di AdminKU cocok dengan stok di rak.', ['SO'], 'opname', 't-so', 'ug-stok-opname',
            ['Atur jadwal', 'Simpan Jadwal', 'Mulai Opname', 'Tinjau selisih'],
            [
                [13, 11.7, 'Frekuensi Opname', 'Pilih frekuensi, hari, dan tanggal. Beranda mengingatkan jadwalnya.'],
                [5.3, 16.4, 'Simpan Jadwal', 'Menyimpan jadwal opname rutin.'],
                [94.4, 25.7, 'Mulai Opname', 'Membuka hitungan baru dari barang yang ada.'],
                [11.8, 20.4, 'Status opname', 'Pantau Sedang dihitung, Tinjau, dan Selesai.'],
            ],
            ['Langkah menghitung dan menutup opname ada di bagian Detail.'],
            ['pengeluaran']],

        'pr' => ['beli', 'Ajukan Permintaan Pembelian', 'Mulai pembelian dari bagian peminta: pilih barang master atau ketik barang baru.', ['PUP'], 'prnew', 'pur-pup', 'ug-siklus-dokumen',
            ['Pilih bagian', 'Tambah barang', 'Isi jumlah', 'Simpan'],
            [
                [17.4, 22.4, 'Bagian peminta', 'Wajib diisi. Tanda bintang merah berarti harus ada.'],
                [93, 28, '+ Tambah Barang', 'Menambah baris barang yang diminta.'],
                [31.3, 35.6, 'Barang baru (ketik)', 'Barang belum ada di master? Ketik nama dan satuannya.'],
                [34.7, 3.6, 'Jalur status', 'Draf, Terkonfirmasi, Persetujuan, Disetujui, lalu Ditutup.'],
                [88, 3.6, 'Simpan', 'Simpan menjadi Draf.'],
            ],
            ['Estimasi harga kosong diisi harga beli terakhir bila ada.'],
            ['po']],

        'po' => ['beli', 'Pantau Pesanan Pembelian (PO)', 'Lihat PO yang perlu tindakan dan berapa persen sudah diterima.', ['PUO'], 'po', 'pur-puo', null,
            ['Lihat Perlu tindakan', 'Cek Diterima', 'Cek Status', 'Buat PO baru'],
            [
                [12.4, 3.7, 'Perlu tindakan', 'Kotak hitung menyaring PO yang menunggu Anda.'],
                [81, 17, 'Diterima', 'Persentase barang PO yang sudah datang.'],
                [92, 17, 'Status PO', 'Dari Diajukan sampai Selesai.'],
                [95, 9, '+ Data Baru', 'Membuka PO kosong.'],
            ],
            ['PO yang sudah diterima penuh berstatus Selesai.'],
            ['kirim']],

        'kirim' => ['kirim', 'Surat Jalan dan Trip', 'Surat jalan berasal dari Pengeluaran Barang dan dipakai di trip pengiriman.', ['SJL'], 'delnotes', 't-sjl', null,
            ['Pilih surat jalan', 'Lihat status trip', 'Cetak', 'Rencanakan kirim'],
            [
                [11.3, 3.7, 'Belum dipakai', 'Menyaring surat jalan yang belum masuk trip.'],
                [7.3, 16.7, 'No. Surat Jalan', 'Nomor mengikuti dokumen Pengeluaran Barang asalnya.'],
                [54, 17.3, 'Trip', 'Menunjukkan trip yang memakai surat jalan ini.'],
                [95, 9, '+ Data Baru', 'Membuat surat jalan baru.'],
            ],
            ['Sopir membuka trip dari HP dan menandai tiap pemberhentian.'],
            ['pengeluaran']],

        'uangkeluar' => ['kas', 'Catat Uang Keluar', 'Catat pengeluaran kas lengkap dengan kebutuhan, kas asal, dan bukti.', ['KSK'], 'cashoutnew', 'kas-ksk', 'ug-kas-alur',
            ['Pilih kebutuhan', 'Pilih kas asal', 'Isi jumlah', 'Lampirkan bukti'],
            [
                [34.9, 36.9, 'Tambah kebutuhan', 'Kebutuhan belum ada? Tekan + dan tambah dari sini.'],
                [42.8, 37.1, 'Kas Asal', 'Pilih Tunai atau Rekening bank.'],
                [68, 54.4, 'Jumlah (IDR)', 'Wajib diisi dan lebih dari 0.'],
                [18.9, 53.2, 'Lampiran Bukti', 'Foto atau PDF, tidak wajib, paling besar 5 MB.'],
                [36.1, 4.4, 'Jalur status', 'Draf, Terkonfirmasi, Persetujuan, Disetujui, Ditutup.'],
            ],
            ['Uang Keluar bisa ditaut ke Uang Masuk yang sudah Disetujui.'],
            ['laporan']],

        'karyawan' => ['sdm', 'Kelola Karyawan', 'Kartu karyawan menandai kontrak, SIM, dan SP yang perlu perhatian.', ['HRE'], 'employees', 'hr-hre', null,
            ['Tambah karyawan', 'Saring perhatian', 'Ganti tampilan', 'Buka kartu'],
            [
                [4.7, 3.2, '+ Karyawan', 'Menambah data karyawan baru.'],
                [20.6, 3.2, 'Perlu perhatian', 'Kontrak atau SIM hampir habis dan data belum lengkap.'],
                [77, 3.2, 'Cari', 'Cari nama, kode, atau jabatan.'],
                [93.4, 3.2, 'Kartu atau Daftar', 'Ganti tampilan kartu dan daftar.'],
                [8.8, 25.6, 'Kartu karyawan', 'Terlihat SP aktif dan sisa masa kontrak.'],
            ],
            ['Bagian di bar atas menyaring karyawan per departemen.'],
            ['grid']],

        'kendaraan' => ['ga', 'Pantau Perawatan Kendaraan', 'Pajak, KIR, dan servis berikutnya berstatus Aman, Segera, atau Terlambat.', ['GVM'], 'fleet', 'ga-gvm', 'ug-ga-perpanjang-servis',
            ['Lihat ringkasan', 'Cek status', 'Catat servis', 'Perpanjang dokumen'],
            [
                [37.7, 6.7, 'Terlambat', 'Dokumen atau servis yang sudah lewat.'],
                [92, 29, 'Status', 'Aman, Segera (30 hari atau 1.000 km), atau Terlambat.'],
                [66, 29, 'Servis berikutnya', 'Sisa kilometer dan hari menuju servis.'],
                [4, 15.7, 'Setup', 'Pengaturan kendaraan dan jenisnya.'],
                [88, 20.9, 'Kolom', 'Pilih kolom yang tampil.'],
            ],
            ['Sopir dengan hak Catat Odometer cukup mengisi odometer dari HP.'],
            ['ac']],

        'ac' => ['ga', 'Jadwalkan Perawatan AC', 'Cuci dan servis besar AC terpantau, dari jadwal sampai kunjungan vendor.', ['GAM'], 'accare', 'ga-gam', 'ug-ga-kunjungan-ac',
            ['Lihat ringkasan', 'Atur jadwal', 'Kunjungan vendor', 'Cek status'],
            [
                [37.7, 6.7, 'Terlambat', 'Cuci atau servis besar yang sudah lewat.'],
                [11.6, 15.7, 'Penjadwalan', 'Atur jadwal cuci dan servis AC.'],
                [4, 15.7, 'Setup', 'Area, ruang, unit, dan vendor AC ada di sini.'],
                [47, 31.4, 'Cuci berikutnya', 'Tanggal dan sisa hari menuju cuci.'],
                [92, 31.4, 'Status AC', 'Aman, Segera, atau Terlambat.'],
            ],
            ['Kunjungan terdekat tampil di atas daftar.'],
            ['kendaraan']],

        'laporan' => ['lap', 'Baca Ringkasan Stok', 'Atur periode dan kategori, lalu cetak laporan siap A4.', ['R-SUM'], 'summary', 'r-sum', null,
            ['Atur periode', 'Tampilkan', 'Pilih grup', 'Cetak'],
            [
                [6.3, 6.6, 'Dari tanggal', 'Atur periode dan kategori laporan.'],
                [51, 9.6, 'Tampilkan', 'Menampilkan pratinjau cetak.'],
                [74.6, 21.6, 'Grup', 'Kelompokkan Tanpa, Per Kategori, atau Per Status.'],
                [88.4, 21.6, 'Hemat Tinta', 'Cetak tanpa warna latar.'],
                [95.4, 21.6, 'Cetak', 'Mencetak atau menyimpan sebagai PDF.'],
            ],
            ['Ukuran kertas A4 potret, A4 lanskap, atau A5 lanskap.'],
            ['grid']],

        'pengguna' => ['sec', 'Atur Pengguna dan Grup', 'Peran dan Grup menentukan modul yang boleh dibuka tiap orang.', ['USR'], 'users', 'sec-usr', null,
            ['Tambah pengguna', 'Pilih peran', 'Pilih grup', 'Nonaktifkan'],
            [
                [95, 9, '+ Data Baru', 'Menambah pengguna baru.'],
                [65.3, 14.1, 'Peran', 'Data Entry, Supervisor, atau Administrator.'],
                [83, 14.1, 'Grup', 'Grup menentukan modul yang boleh dibuka.'],
                [2.6, 19.4, 'Ikon pensil', 'Membuka pengguna untuk diubah.'],
            ],
            ['Pengguna tidak dihapus. Nonaktifkan bila sudah tidak dipakai.'],
            ['grid']],
    ],
];
