# Lembar Uji HP: Android dan iPhone

Untuk menguji Tampilan HP Stoku di perangkat sungguhan, sekitar 45 sampai 60 menit. Rujukan lengkapnya ada di `docs/QC-SKENARIO.md` bagian 18 (HPV-01 sampai HPV-30); lembar ini mengurutkannya agar bisa dikerjakan tanpa bolak-balik. Tandai kolom **A** (Android) dan **i** (iPhone) dengan ✓ bila lulus, ✗ bila gagal. Bila gagal, tulis singkat apa yang terjadi di kolom Catatan.

## 0. Persiapan (5 menit)

- **Alamat:** buka alamat server di browser HP (mis. `http://192.168.x.x/` lewat WiFi kantor). Alamat ini `http` biasa, jadi **kamera, pasang ke layar utama, dan mode offline tidak bisa diuji** (butuh https).
- **Akun uji:** Supervisor, Data Entry, dan Administrator dari `database/seeders/UserSeeder.php`. Beberapa langkah butuh Administrator (bagian 7).
- **Data:** satu Stok Opname berstatus *Sedang dihitung* (QC bagian 15); barang yang kedaluwarsa dan yang hampir kedaluwarsa; minimal satu penerimaan dan satu pengeluaran terkonfirmasi.
- **Pakai browser bawaan:** Chrome di Android, Safari di iPhone. Uji sekali lagi di browser lain bila sempat (Samsung Internet, Chrome iOS).
- **Catat dulu:** model HP, versi Android / iOS, browser dan versinya.
- **Cookie lama:** bila HP sudah pernah dipakai membuka Stoku, hapus data situs Stoku dulu supaya peralihan otomatis ikut teruji.

## 1. Ukur layar (HPV-17) — 3 menit

| No | Langkah | Lulus bila | A | i | Catatan |
|---|---|---|---|---|---|
| 1.1 | Buka `/ukur` (tanpa masuk). Catat angka di kotak ringkasan. | Muncul ringkasan seperti `393 x 852 - DPR 3 - potret - phone`. | | | |
| 1.2 | Putar layar mendatar, lalu tegak lagi. | Angka berubah mengikuti putaran. | | | |
| 1.3 | Ketuk **Salin hasil**, tempel ke chat dan kirim. | Teks salinan berisi baris ringkasan yang sama. Tanpa https: teks terbuka dan terpilih, salin manual. | | | |

## 2. Masuk dan berganti tampilan (HPV-01, 02, 05, 06) — 5 menit

| No | Langkah | Lulus bila | A | i | Catatan |
|---|---|---|---|---|---|
| 2.1 | Buka halaman Masuk. | Tampilan HP: satu kolom, isian dan tombol besar, tanpa tombol *Tampilan Desktop* (ponsel selalu Tampilan HP). | | | |
| 2.2 | Ketuk kolom nama pengguna, lalu kolom password. | Layar **tidak membesar sendiri** saat isian difokus (khusus iPhone), keyboard tidak menutup tombol Masuk. | | | |
| 2.3 | Masuk. | Beranda HP: kartu ringkasan besar, satu tombol ≡ melayang di kiri atas, tidak ada gulir ke samping. | | | |
| 2.4 | ≡ > **Menu**. Tutup browser, buka lagi. | Tidak ada tombol Tampilan Desktop; tetap Tampilan HP tanpa muat ulang aneh atau berputar. | | | |

## 3. Menu dan bilah atas (HPV-03, 03b, 04) — 5 menit

| No | Langkah | Lulus bila | A | i | Catatan |
|---|---|---|---|---|---|
| 3.1 | Di Beranda ketuk ≡. | Tombol hilang, bilah di atas: Beranda, Stok, Transaksi, Opname, **Menu** (paling kanan). Menutup sendiri setelah sekitar 6 detik atau ketuk di luar. | | | |
| 3.2 | Ketuk **Menu**. | Daftar semua menu yang boleh dibuka akun itu, plus Keluar. | | | |
| 3.3 | Buka Barang > **Data Baru**, isi Nama Barang. Ketuk ≡. | Titik kuning di tombol dan "belum disimpan"; bilah tidak menutup sendiri; pindah layar meminta konfirmasi. | | | |
| 3.4 | Buka daftar Barang. | Berupa kartu (nama + kode, lencana, stok, lokasi). Ketuk kartu membuka barangnya. Bilah: Cari, Data Baru, Saring, Ekspor. | | | |

## 4. Cari Barang (HPV-19, 19b, 19c) — 5 menit

| No | Langkah | Lulus bila | A | i | Catatan |
|---|---|---|---|---|---|
| 4.1 | Beranda > **Cari Barang**. | Kolom besar sudah aktif tanpa diketuk, keyboard terbuka. | | | |
| 4.2 | Ketik sebagian nama (mis. `baut`), Enter. | Kartu hasil: nama, kode, lokasi, lencana AMAN / PESAN ULANG, stok. | | | |
| 4.3 | Ketik atau pindai kode barang lengkap (huruf kecil juga), Enter. | Langsung membuka barang: stok per batch, kedaluwarsa paling awal di atas, lencana HAMPIR / KEDALUWARSA. | | | |
| 4.4 | Ulangi dengan kode batch (`KODE-YYMMDD`). | Batch itu berbingkai biru. Kolom cari kosong dan aktif lagi. | | | |
| 4.5 | Cari `TIDAK-ADA-9999`, lalu kode lokasi (mis. `B1-02`). | Pesan "tidak ada" yang jelas; kode lokasi menampilkan barang di lokasi itu. | | | |
| 4.6 | Bila ada pemindai Bluetooth / USB: pindai satu barcode. | Terisi dan langsung diproses seperti Enter. | | | |

## 5. Dokumen: Penerimaan dan Pengeluaran (HPV-18, 18c) — 10 menit

| No | Langkah | Lulus bila | A | i | Catatan |
|---|---|---|---|---|---|
| 5.1 | Transaksi > Penerimaan: daftar. | Kartu (nomor, pihak, lencana status, tanggal, referensi), tanpa gulir ke samping. | | | |
| 5.2 | **Data Baru**, isi Supplier, ketuk **+ Tambah Barang**. | Satu kartu per barang: nomor kiri atas, tombol Simpan / Urungkan kanan atas, Barang selebar layar, Jml / Satuan / Tanggal Kedaluwarsa berdampingan. | | | |
| 5.3 | Ketuk kolom Barang dan cari satu barang. | Daftar pilihan muat di layar dan bisa diketuk. | | | |
| 5.4 | Ketuk kolom **Tanggal Kedaluwarsa**. | Muncul pemilih tanggal bawaan HP dan memilihnya mengisi kolom. | | | |
| 5.5 | Ketuk kolom **Jml**. | Keyboard angka (dengan koma desimal), layar tidak membesar. | | | |
| 5.6 | Ketuk Simpan pada kartu, lalu **Simpan** di bilah, lalu **Konfirmasi**. | Dokumen tersimpan lalu terkonfirmasi; stok bertambah. Tombol di kartu mudah diketuk dengan jempol. Bila **Konfirmasi** tidak ada di bilah, cari di **Aksi lain**. | | | |
| 5.7 | Ulangi singkat untuk Pengeluaran (pilih batch, jumlah, Konfirmasi). | Sama, dan batch FEFO terpilih lebih dulu. | | | |

## 6. Laporan (HPV-18b) — 5 menit

| No | Langkah | Lulus bila | A | i | Catatan |
|---|---|---|---|---|---|
| 6.1 | Laporan > **Ringkasan Stok**, ketuk Terapkan. | Daftar kartu yang terbaca (kode + nama, pasangan label / nilai), kotak kriteria di atas. | | | |
| 6.2 | Pilih **Grup** (Per Kategori / Per Status). | Kartu dikelompokkan dengan judul dan jumlah baris. | | | |
| 6.3 | Buka **Laporan Kedaluwarsa**. | Barang yang sudah kedaluwarsa bermerah. | | | |
| 6.4 | Ketuk **Cetak**. | Dialog cetak browser terbuka dengan lembar kertas (bukan kartu). Catat bila tidak ada. | | | |

## 7. Hitung Stok Opname (HPV-07 sampai 15) — 15 menit

| No | Langkah | Lulus bila | A | i | Catatan |
|---|---|---|---|---|---|
| 7.1 | Opname > **Hitung sekarang**. | Satu barang per kartu, kolom jumlah besar, "Barang 1 / N", tombol Sebelumnya / Berikutnya menempel di bawah. Tidak ada stok sistem (hitung buta). | | | |
| 7.2 | Ketik `12,5`, Enter. | Pindah ke barang 2, tertulis Tersimpan, jumlah terisi bertambah. | | | |
| 7.3 | Kosongkan jumlah barang 3, ketuk Berikutnya. Isi `0` di barang 4. | Barang 3 tetap Belum dihitung; barang 4 dihitung nol. | | | |
| 7.4 | Di barang 5 centang **Tidak dihitung** tanpa catatan. | Perlu catatan; baris belum terisi sampai catatan ada. | | | |
| 7.5 | Cari sebagian kode barang, pilih hasilnya. Lalu kode lengkap + Enter. | Lompat ke barang itu, kolom jumlah siap. Kode tak ada: "Tidak ketemu". | | | |
| 7.6 | Nyalakan **mode pesawat**, isi satu barang, pindah. Matikan mode pesawat. | Status merah "Gagal menyimpan", isian tetap. Sinyal kembali: terkirim sendiri atau lewat Coba lagi, berubah Tersimpan. | | | |
| 7.7 | Isi satu barang, lalu kunci layar HP 30 detik, buka lagi. | Isian masih ada; yang belum terkirim terkirim. | | | |
| 7.8 | *Butuh dua perangkat:* di komputer (akun lain) isi barang X; di HP (belum dimuat ulang) isi barang X dengan angka lain. | Peringatan baris sudah diubah akun lain; wajib alasan untuk menimpa. | | | |
| 7.9 | Isi semua barang sampai akhir, ketuk Berikutnya. | Ringkasan "N dari N terisi" dan **Selesai Hitung**; sesudahnya kembali ke daftar Opname dengan status Tinjau. | | | |

## 8. Formulir data master (HPV-30 sampai 30g) — 10 menit (Administrator)

| No | Langkah | Lulus bila | A | i | Catatan |
|---|---|---|---|---|---|
| 8.1 | Barang > Data Baru. Ketuk Kategori, pilih satu. Ketuk tombol **aa**. | Satu kolom; daftar pilihan setinggi jari; tidak membesar; **aa** bisa diketuk. | | | |
| 8.2 | Ketuk **+** di samping Kategori, lalu X, buka lagi, kosongkan, Simpan. | Dialog muat; X dan tombol bawah mudah diketuk; galat terbaca. | | | |
| 8.3 | Barang yang sudah ada: bagian Batch, Satuan masuk, kotak **Saran pesan ulang**. | Berupa kartu; isian saran selebar kotak; tombol Pakai saran mudah diketuk. | | | |
| 8.4 | Supplier > Data Baru > Simpan kosong. | Isian wajib bertanda merah dengan pesan terbaca. | | | |
| 8.5 | Perusahaan: isi alamat beberapa baris, ganti Gaya Kop, pilih logo. | Kotak alamat cukup tinggi; radio mudah diketuk; pratinjau muat; pilih logo membuka galeri / kamera HP. | | | |
| 8.6 | Keamanan > Pengguna > Data Baru, Grup, Daftar Modul. | Satu kolom; tombol tampil / sembunyi password mudah diketuk; Pengguna, Grup, dan Daftar Modul berupa kartu. | | | |
| 8.7 | Keluar. Dari Masuk ketuk **Lupa password**. | Tampil seperti halaman Masuk HP (tanpa jam dan kutipan). | | | |

## 9. Khusus perangkat sungguhan — 5 menit

Hal-hal ini **tidak bisa diuji di emulasi browser**. Catat apa yang terjadi, lulus atau tidak.

| No | Langkah | Lulus bila | A | i | Catatan |
|---|---|---|---|---|---|
| 9.1 | Putar mendatar di Beranda, Cari Barang, dan satu formulir. | Tetap terbaca dan bisa dipakai; tidak ada yang terpotong. | | | |
| 9.2 | iPhone: lihat tepi atas dan bawah layar (poni, garis bawah). | Isi tidak tertutup poni atau indikator beranda. | | | |
| 9.3 | Buka keyboard di formulir panjang, ketuk isian paling bawah. | Isian yang sedang diisi terlihat di atas keyboard, tidak tertutup. | | | |
| 9.4 | Android: tekan tombol / gestur **Kembali** saat di formulir yang belum disimpan, dan di daftar. | Catat: kembali ke layar sebelumnya, atau keluar dari Stoku, atau minta konfirmasi. | | | |
| 9.5 | Geser turun dari atas halaman saat mengisi formulir. | Tidak memuat ulang sendiri dan tidak membuang isian. | | | |
| 9.6 | Buka tampilan lain: iPad atau tablet bila ada. | Tampilan Desktop (tablet dianggap komputer); catat apakah nyaman. | | | |
| 9.7 | Buka juga Panduan Pengguna (menu profil > Bantuan) di HP. | Terbaca, daftar isi mudah diketuk, tidak ada gulir ke samping. | | | |

## 10. Komputer sekali (HPV-16) — 2 menit

Di komputer, buka Beranda, Stok Opname, dan satu laporan. **Lulus bila** tampilan desktop (menu kiri, tabel) sama seperti sebelumnya; satu-satunya tambahan adalah **Tampilan HP** di menu profil. Perkecil jendela sampai di bawah 600 px: Stoku pindah ke Tampilan HP sendiri; lebarkan lagi: kembali ke desktop.

## Melapor

Untuk setiap ✗, kirim: **nomor langkah**, apa yang terjadi, **tangkapan layar**, model HP + versi OS + browser, dan hasil `/ukur` dari HP itu. Jika banyak yang gagal di satu langkah yang sama pada satu jenis HP, itu biasanya satu penyebab; sebut saja sekali.

| Ringkasan | Android | iPhone |
|---|---|---|
| Perangkat dan versi | | |
| Jumlah langkah lulus | | |
| Jumlah langkah gagal | | |
| Temuan paling mengganggu | | |
