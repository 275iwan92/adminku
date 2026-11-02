# Skenario Uji QC Stoku

Dokumen ini untuk penguji (QC) pemula. Anda tidak perlu bisa memrogram. Anda hanya perlu mengikuti langkah, melihat hasilnya, lalu menulis Lulus atau Gagal.

Pasang aplikasi lebih dulu dengan [README](../README.md). Akun ujinya ada di bagian 3 README.

## Cara memakai dokumen ini

1. Kerjakan kasus uji dari atas ke bawah, per bagian. Banyak kasus memakai data dari kasus sebelumnya di bagian yang sama.
2. Setiap kasus punya nomor (mis. `PNM-03`). Pakai nomor itu saat melapor.
3. Di kolom **Lulus/Gagal**, tulis `L` bila hasil di layar sama dengan **Hasil yang diharapkan**. Tulis `G` bila berbeda.
4. Di kolom **Catatan**, tulis apa saja yang aneh, walau kasusnya Lulus. Untuk kasus Gagal, tulis hasil nyata di layar.
5. Peran ditulis di awal tiap bagian atau di langkah. Masuk dengan akun yang sesuai (lihat README). Keluar dulu (menu profil, Keluar aplikasi) sebelum berganti akun.
6. Jumlah stok barang contoh bisa berubah setelah Anda menguji. Karena itu, banyak kasus meminta Anda **mencatat stok sebelum** lalu membandingkannya **sesudah**. Jangan mengandalkan angka dari ingatan.
7. Bila data sudah kacau, atur ulang dengan [README bagian 5](../README.md#5-mengatur-ulang-data). Ini menghapus semua data, jadi lakukan hanya bila perlu.

Istilah di layar: **Konfirmasi** = memproses dokumen ke stok. **Draf** = tersimpan tetapi stok belum berubah. **Batalkan** = membatalkan dokumen yang sudah dikonfirmasi. **Hapus** = membuang draf. **Setujui / Tolak** = keputusan Supervisor.

Catatan peran: di layar, nama menu bisa berbeda sedikit dari tulisan di sini (misalnya "+ Data Baru"). Bila hanya beda kata dan maknanya sama, tidak perlu dilaporkan.

## Cara melaporkan temuan

Untuk setiap kasus Gagal (atau hal aneh), tulis laporan dengan isi berikut. Satu temuan = satu laporan.

1. **Nomor kasus**, mis. `PNG-04`.
2. **Peran yang dipakai** (Administrator, Supervisor, atau Data Entry).
3. **Langkah yang Anda lakukan**, urut dari awal, cukup untuk diulang orang lain.
4. **Hasil yang diharapkan** (salin dari tabel).
5. **Hasil nyata**: apa yang benar-benar terjadi. Salin pesan di layar persis seperti tertulis.
6. **Tangkapan layar** seluruh jendela browser, termasuk alamat di bagian atas. Bila ada pesan merah atau kuning, pastikan terlihat.
7. **Waktu kejadian** dan browser yang dipakai (Chrome, Edge, dst.).
8. Bila muncul halaman error besar dengan tulisan teknis, sertakan tangkapan layar halaman itu. Penguji tidak perlu memperbaikinya.

Sebelum melapor, cek bagian [Hal yang sengaja belum ada](#hal-yang-sengaja-belum-ada). Hal-hal itu bukan bug.

## Data contoh yang dipakai

Setelah pemasangan, aplikasi berisi data contoh. Yang dipakai di kasus uji:

- Barang tanpa kedaluwarsa: `ATK-0001` Kertas HVS A4 80gr (satuan `box`), `SPR-0001` Baut M6 x 20 mm (`pcs`), `TLS-0001` Obeng Plus/Minus Set (`set`).
- Barang berkedaluwarsa: **Lem Kayu Fox 100 ml** (dua batch), **Cat Semprot Pelindung**, dan **Cairan Pembersih Mesin 5L** (batch yang sudah lewat kedaluwarsa).
- Supplier `SUP-001` sampai `SUP-003`. Penerima antara lain Budi (Teknisi) dan Sari. Departemen antara lain Maintenance dan Admin.
- Kategori: ATK, SPR, TLS. Satuan bawaan: pcs, box, set, pack, roll, can, bottle, dan lainnya.

Untuk kasus yang butuh barang bersih, buat barang baru lewat kasus `BRG-02` dan pakai terus (disebut **barang uji**).

---

## 1. Login dan hak akses (LGN)

| No | Langkah | Hasil yang diharapkan | Lulus/Gagal | Catatan |
|---|---|---|---|---|
| LGN-01 | Buka alamat aplikasi. Masuk sebagai `supervisor` dengan password benar. | Masuk ke Beranda: ada kotak ringkasan, tabel titik pesan ulang, dan (khusus Supervisor) kotak Nilai stok. | | |
| LGN-02 | Keluar (menu profil, Keluar aplikasi). Masuk sebagai `entry`. | Masuk, dan menu Penerimaan Barang terbuka sebagai tab kerja. Tidak ada kotak Nilai stok. | | |
| LGN-03 | Keluar. Masuk sebagai `admin`. | Masuk ke Beranda. Di panel kiri ada kelompok menu Keamanan (Pengguna, Grup, Data Contoh, Daftar Modul). | | |
| LGN-04 | Di halaman masuk, isi username `supervisor` dan password yang salah. Tekan masuk. | Tidak masuk. Muncul pesan galat di halaman masuk. Isian username tetap terisi. | | |
| LGN-05 | Masuk dengan e-mail (`supervisor@sstock.test`) sebagai pengganti username, dengan password benar. | Berhasil masuk. | | |
| LGN-06 | Kosongkan username lalu tekan masuk. Ulangi dengan password kosong. | Pesan "Isi username atau e-mail Anda." dan "Isi password Anda." | | |
| LGN-07 | Salah password 5 kali berturut-turut untuk username yang sama, lalu coba masuk sekali lagi (percobaan ke-6). | Muncul pesan "Terlalu banyak percobaan. Coba lagi dalam ... detik." Setelah waktunya lewat, bisa masuk lagi. | | |
| LGN-08 | Masuk sebagai `entry`. Lihat panel kiri. | Menu yang ada: Barang, Suppliers, Departemen, Penerima, Penerimaan Barang, Pengeluaran Barang, Stok Opname, Kartu Stok, Ringkasan Stok, Laporan Penerimaan, Laporan Pengeluaran, Laporan Kedaluwarsa. **Tidak ada**: Satuan, Kategori, Lokasi Penyimpanan, Saldo Awal, Penyesuaian Stok, Laporan Opname & Penyesuaian, dan Keamanan. | | |
| LGN-09 | Masih sebagai `entry`, ketik langsung di browser alamat `/units`, lalu `/users`, lalu `/docs/adjustment` (tambahkan setelah alamat aplikasi). | Setiap alamat ditolak (halaman terlarang/403). Tidak ada data yang tampil. | | |
| LGN-10 | Masuk sebagai `supervisor`. Lihat panel kiri, lalu ketik `/users` langsung. | Menu Keamanan tidak ada. Alamat `/users` ditolak. | | |
| LGN-11 | Pada halaman masuk, tekan Lupa password?. Isi username `entry`. Kirim. | Pesan umum bahwa tautan dikirim (pesan sama untuk akun apa pun, termasuk yang tidak ada). | | |
| LGN-12 | Buka berkas `storage/logs/laravel.log` di folder aplikasi, cari tautan reset di bagian paling bawah. Buka tautannya, isi password baru (min. 8 karakter), simpan. Lalu masuk dengan password baru. | Password berhasil diganti dan bisa dipakai masuk. **Kembalikan** password `entry` ke `12345678` setelahnya (lewat Ubah password atau reset lagi). | | |
| LGN-13 | Buka lagi tautan reset yang sama (yang sudah dipakai). | Tautan ditolak (tidak bisa dipakai dua kali). | | |
| LGN-14 | Buka halaman masuk. Cari kotak "ingat saya". | Kotak itu tidak ada. Hanya ada isian username / e-mail dan password. | | |
| LGN-15 | Di `.env` ubah `SESSION_LIFETIME=3`, jalankan `php artisan config:clear`, lalu masuk sebagai `entry`. Diamkan tanpa menyentuh mouse atau keyboard sekitar 1 menit. Kembalikan `SESSION_LIFETIME` ke `30` setelah uji. | Di pojok kanan bawah muncul peringatan "Sesi hampir berakhir" dengan hitung mundur, tombol **Tetap masuk** dan **Keluar sekarang**. Tekan Tetap masuk: peringatan hilang dan hitungan mulai lagi. Bila dibiarkan sampai habis, Anda keluar otomatis ke halaman masuk. | | |

## 2. Master barang dan batch (BRG)

| No | Langkah | Hasil yang diharapkan | Lulus/Gagal | Catatan |
|---|---|---|---|---|
| BRG-01 | Sebagai `supervisor`, buka Barang. Lihat grid dan kotak hitung di atas. | Ada kotak Semua barang, Pesan ulang, Nonaktif. Kolom Harga Satuan dan Nilai Stok tampil. Baris dengan stok di/bawah titik pesan ulang berstatus PESAN ULANG (merah muda), yang lain AMAN. Ketik `lem` di Cari: grid menyempit. | | |
| BRG-02 | Masih sebagai `supervisor`: + Data Baru. Isi Nama `Barang Uji QC`, Kategori ATK, Satuan `pcs`. Simpan. | Tersimpan. Kode otomatis berbentuk `ATK-` diikuti 4 angka urut berikutnya. Kode, Kategori terkunci setelah disimpan. Stok 0. Ini **barang uji**: catat kodenya. | | |
| BRG-03 | Buka barang uji. Isi Titik Pesan Ulang `5`, Jml Pesan Ulang `20`, Lead Time `3`, Harga Satuan `1000`. Simpan. | Tersimpan dan terbaca lagi saat dibuka ulang. | | |
| BRG-04 | Coba mengubah kolom Kode Barang dan Kategori di barang uji. Cari kolom untuk mengetik stok. | Kode dan Kategori tidak bisa diubah. Tidak ada kolom untuk mengetik stok. | | |
| BRG-05 | Keluar. Masuk sebagai `entry`. Buka Barang, lalu + Data Baru. Isi Nama `Barang Entry QC`, Kategori ATK, Satuan `pcs`. Simpan. | Data Entry boleh menambah barang. Kode otomatis. Parameter titik pesan ulang, harga, dan lead time kosong atau 0. Kolom Harga Satuan dan Nilai Stok tidak tampil di grid. | | |
| BRG-06 | Sebagai `entry`, buka barang yang sudah ada (klik 2x barisnya). Coba mengubah dan menyimpan. | Form terkunci / tidak bisa diubah oleh Data Entry. | | |
| BRG-07 | Sebagai `supervisor`, buka **Lem Kayu Fox 100 ml**. Lihat bagian Batch. | Ada 2 batch. Kode batch berbentuk `KODEBARANG-YYMMDD` (mis. kedaluwarsa 01 Nov 2026 menjadi akhiran `261101`). Tampil tanggal, stok, status tiap batch. Ada kotak Stok saat ini dan Tersedia. | | |
| BRG-08 | Buka **Cairan Pembersih Mesin 5L**. | Batch-nya berstatus sudah kedaluwarsa. Stok Tersedia lebih kecil dari Stok saat ini, bisa 0 (batch yang sudah lewat tidak dihitung tersedia). | | |
| BRG-09 | Nonaktifkan barang uji (tombol Nonaktifkan di form atau ikon di grid). Buka Penerimaan Barang baru dan cari barang uji. Cek kotak Nonaktif di Barang. | Barang uji tidak muncul di pilihan dokumen baru. Barang muncul di kotak Nonaktif. | | |
| BRG-10 | Aktifkan lagi barang uji. | Barang muncul lagi di pilihan dokumen. | | |
| BRG-11 | Di Barang, tekan Export untuk mengunduh CSV. Buka berkas itu. | Berkas berisi isi grid (sesuai pencarian dan kolom yang tampil). | | |

### Master pendukung (kategori, lokasi, supplier, departemen, penerima)

| No | Langkah | Hasil yang diharapkan | Lulus/Gagal | Catatan |
|---|---|---|---|---|
| BRG-12 | Sebagai `supervisor`, buka Kategori, + Data Baru. Isi Awalan Kode `QC` (2 huruf) dan nama. Simpan. | Ditolak: awalan harus tepat 3 huruf atau angka. | | |
| BRG-13 | Isi Awalan Kode `ATK` (sudah ada). Simpan. | Ditolak: awalan harus unik. | | |
| BRG-14 | Buat Kategori baru dengan awalan `QCT`. Lalu buat barang dalam kategori itu. | Kode barang berawalan `QCT-0001`. | | |
| BRG-15 | Buka Lokasi Penyimpanan, + Data Baru: kode `QC-01`, nama `Rak QC`. Simpan. Buka lagi dan coba mengubah kode. | Tersimpan. Kode terkunci setelah disimpan. | | |
| BRG-16 | Sebagai `entry`, buka Suppliers, + Data Baru: kode `s-qc1` (huruf kecil), nama `Supplier QC`. Simpan. | Tersimpan. Kode otomatis menjadi huruf besar `S-QC1`. Kode tidak bisa diubah setelahnya. | | |
| BRG-17 | Buat supplier lagi dengan kode `S-QC1`. | Ditolak: kode sudah dipakai. | | |
| BRG-18 | Buka Departemen dan Penerima. Tambah satu departemen dan satu penerima (dengan departemen bawaan). | Keduanya tersimpan dan muncul di grid. | | |
| BRG-19 | Nonaktifkan supplier `S-QC1`, lalu buat Penerimaan baru. | Supplier itu tidak muncul di pilihan. | | |
| BRG-20 | Buka Suppliers dan cari **Kontan** (supplier sistem untuk pembelian tunai). Buka formnya, cari tombol Nonaktifkan dan coba mengganti nama. | Kontan ada. Tombol Nonaktifkan tidak ada dan namanya terkunci. Menonaktifkannya dari grid ditolak dengan pesan "Kontan adalah supplier sistem: tidak bisa dinonaktifkan, diganti nama, atau dihapus." | | |

## 3. Supplier dan wilayah (SUP)

Data wilayah biasanya **kosong** di mesin penguji (lihat [README bagian 4](../README.md#4-wilayah-opsional)).

| No | Langkah | Hasil yang diharapkan | Lulus/Gagal | Catatan |
|---|---|---|---|---|
| SUP-01 | Buka Suppliers, lihat grid dan supplier `SUP-001`. | Supplier contoh ada. Kolom wilayah/kota kosong (bila wilayah kosong). | | |
| SUP-02 | Bila data wilayah kosong: + Data Baru, isi kode dan nama saja (tanpa wilayah). Simpan. | Tersimpan tanpa galat. Isian Wilayah tidak bertanda wajib. | | |
| SUP-03 | Ubah supplier itu (ganti nama), tanpa mengisi wilayah. Simpan. | Tersimpan. | | |
| SUP-04 | Bila data wilayah ada (diimpor lewat `sstock:import-geo`): + Data Baru, kosongkan Wilayah. Simpan. | Ditolak: "Wilayah wajib diisi: cari dan pilih minimal sampai kota." | | |
| SUP-05 | Bila data wilayah ada: ketik nama kota atau kode pos di kotak Wilayah, pilih dari daftar. Simpan. | Tersimpan. Kode pos terisi otomatis dari wilayah. Grid menampilkan "Kota, Negara". | | |
| SUP-06 | Isi telepon kantor, nama kontak, HP kontak, dan alamat jalan. Simpan, buka ulang. | Semua tersimpan. | | |

## 4. Saldo Awal (SAL)

Peran: Supervisor. Saldo Awal hanya untuk barang yang **belum pernah** punya pergerakan stok.

| No | Langkah | Hasil yang diharapkan | Lulus/Gagal | Catatan |
|---|---|---|---|---|
| SAL-01 | Sebagai `supervisor`, buka Saldo Awal, + Data Baru. Perhatikan isian awal. | Form baru sudah berisi satu baris barang dengan jumlah 1. Status belum ada nomor sampai disimpan. | | |
| SAL-02 | Isi Saldo per Tanggal dengan tanggal **besok**. Pilih barang uji, Jml Awal `10`, Nilai Satuan `1000`. Simpan. | Ditolak: tanggal tidak boleh lebih dari hari ini. | | |
| SAL-03 | Ganti tanggal menjadi hari ini. Simpan. | Tersimpan sebagai Draf dengan nomor `OPN-YYMM-NNNN`. Stok barang uji masih 0. | | |
| SAL-04 | Tekan Konfirmasi. | Status Terkonfirmasi. Dokumen terkunci. Stok barang uji menjadi 10. Di Barang, nilai stok = 10 x 1000. | | |
| SAL-05 | Buat Saldo Awal baru untuk **ATK-0001** (sudah punya pergerakan). Simpan, lalu Konfirmasi. | Ditolak dengan pesan bahwa ATK-0001 sudah punya pergerakan stok; pakai Penyesuaian Stok, bukan Saldo Awal. Stok ATK-0001 tidak berubah. | | |
| SAL-06 | Buat Saldo Awal Draf baru (barang lain yang belum bergerak, mis. barang dari `BRG-05`). Simpan. Lalu tekan Hapus. | Draf dibuang. Nomornya tidak ada lagi di grid. | | |
| SAL-07 | Buka Saldo Awal yang sudah Terkonfirmasi (SAL-04). | Tidak ada tombol Hapus. Isian terkunci. | | |
| SAL-08 | Sebagai `entry`, cari menu Saldo Awal. | Menu tidak ada (lihat `LGN-08`). | | |

## 5. Penerimaan Barang (PNM)

Peran: Data Entry atau Supervisor. Pakai **barang uji** (stok 10 dari `SAL-04`; bila belum, anggap awalnya 0).

| No | Langkah | Hasil yang diharapkan | Lulus/Gagal | Catatan |
|---|---|---|---|---|
| PNM-01 | Sebagai `entry`, buka Penerimaan Barang, + Data Baru. Pilih Supplier `SUP-001`. Tambah Barang: barang uji, Jml `10`, Harga Beli `2000`. Catat stok barang uji (A). Simpan. | Tersimpan sebagai Draf dengan nomor `RCV-YYMM-NNNN`. Stok barang uji masih A (tidak berubah). | | |
| PNM-02 | Tekan Konfirmasi. | Status Terkonfirmasi. Stok barang uji = A + 10. Dokumen terkunci. Tanggal/jam dokumen = saat Konfirmasi. | | |
| PNM-03 | Sebagai `supervisor`, buat Penerimaan lain: barang uji Jml `10`, Harga Beli `4000`. Konfirmasi. Lalu lihat Nilai Stok barang di grid Barang. | Stok bertambah 10 lagi. Biaya rata-rata bergerak dihitung ulang dari harga beli (rata-rata tertimbang stok lama dan baru). Bila Anda mengikuti SAL-04 (10 pcs @1.000), PNM-01 sampai PNM-03 berurutan: stok 30, Nilai Stok 70.000 (rata-rata sekitar 2.333). | | |
| PNM-04 | Buat Penerimaan baru tanpa memilih Supplier. Simpan. | Ditolak, ada pesan merah di bawah isian Supplier. Tidak tersimpan. | | |
| PNM-05 | Buat Penerimaan dengan Jml `0`, lalu dengan Jml `-5`. Simpan. | Ditolak: "Jumlah harus lebih dari 0." | | |
| PNM-06 | Buat Penerimaan **Lem Kayu Fox 100 ml** tanpa Tanggal Kedaluwarsa. Simpan atau Konfirmasi. | Ditolak: "Isi tanggal kedaluwarsa untuk Lem Kayu Fox 100 ml." | | |
| PNM-07 | Terima Lem Kayu Fox 100 ml Jml `5` dengan tanggal kedaluwarsa baru (mis. 1 tahun dari sekarang). Konfirmasi. Buka barang itu. | Muncul batch baru berkode `KODE-YYMMDD` sesuai tanggal itu, stok 5. Batch lama tidak berubah. | | |
| PNM-08 | Terima lagi Lem Kayu Fox 100 ml Jml `3` dengan **tanggal yang sama** persis dengan PNM-07. Konfirmasi. | Tidak ada batch baru. Stok batch yang sama menjadi 8. | | |
| PNM-09 | Buat Penerimaan dengan satuan `box` untuk barang `pcs` (barang uji) saat barang itu **belum punya faktor** untuk `box` (bagian Satuan masuk di form Barang kosong) dan konversi umum box-pcs belum ada. Simpan atau Konfirmasi. | Muncul jendela "Faktor satuan belum ada". Isi "1 box = ... pcs" dengan `12`, tekan Simpan Faktor, lalu Simpan/Konfirmasi lagi. Faktor tersimpan sebagai Satuan masuk milik barang uji itu. | | |
| PNM-10 | Lanjutan PNM-09: setelah konversi tersimpan, Konfirmasi penerimaan 2 box. | Stok barang uji bertambah 24 pcs (2 x 12). Kolom "Dalam satuan stok" menunjukkan 24. | | |
| PNM-11 | Isi form Penerimaan, tekan Simpan **dua kali cepat** (klik ganda). | Hanya satu dokumen yang dibuat. Tombol hanya bereaksi sekali. | | |
| PNM-12 | Buka Penerimaan Terkonfirmasi, tekan tombol Kembali. | Kembali ke grid Penerimaan Barang (bukan layar sebelumnya). | | |
| PNM-13 | Pada Penerimaan Terkonfirmasi, tekan Cetak. | Halaman cetak memuat tulisan mode `CONFIRMED`. Pada Draf, tulisan `DRAFT` dan ada tanda air. | | |
| PNM-14 | Di Penerimaan baru, buka pilihan Supplier. Tekan tombol **+** (Tambah baru) di sebelahnya. Isi Nama Supplier `Supplier Cepat QC`, Simpan. | Kontan ada paling atas di pilihan. Kotak kecil "Tambah Supplier" tertutup dan supplier baru langsung terpilih. Di menu Suppliers kodenya otomatis (SUP-00N). Nama yang sama ditolak. Pada Penerimaan Terkonfirmasi tombol + tidak ada. | | |
| PNM-15 | Lanjutan PNM-10: buka barang uji dan lihat bagian Satuan masuk. Sebagai `entry` coba memperbaiki faktor `box`, lalu sebagai `supervisor`. | Tabel menunjukkan `box`, faktor 12, Dipakai "1 dokumen", dan Diubah oleh. Data Entry melihat tulisan "Hubungi Supervisor" (bukan tombol Ubah). Supervisor boleh mengubah. Penerimaan lama tidak berubah. | | |

## 6. Pengeluaran Barang, FEFO, dan peringatan batch (PNG)

Peran: Data Entry atau Supervisor. FEFO = barang yang paling cepat kedaluwarsa keluar lebih dulu.

| No | Langkah | Hasil yang diharapkan | Lulus/Gagal | Catatan |
|---|---|---|---|---|
| PNG-01 | Sebagai `entry`, buka Pengeluaran Barang, + Data Baru. Jangan pilih Penerima. Tambah barang uji Jml `1`. Simpan. | Ditolak: Penerima wajib. | | |
| PNG-02 | Pilih Penerima `Budi (Teknisi)`, Keperluan `uji QC`. Tambah barang uji Jml `5`. Catat stok (A). Simpan. | Draf `ISS-YYMM-NNNN`. Stok masih A. | | |
| PNG-03 | Tekan Konfirmasi. | Terkonfirmasi. Stok = A - 5. Biaya rata-rata barang **tidak berubah** (stok keluar pada biaya rata-rata). | | |
| PNG-04 | Buat Pengeluaran barang uji dengan Jml lebih besar dari stok (mis. stok + 100). Konfirmasi. | Ditolak: "Stok ... tidak cukup: tersedia X, dibutuhkan Y." Stok tidak berubah. Stok tidak pernah menjadi minus. | | |
| PNG-05 | Tambah baris barang **Lem Kayu Fox 100 ml**. Lihat daftar batch di kolom Barang. | Daftar batch urut kedaluwarsa paling cepat di atas. Kolom Tersedia = stok batch itu. | | |
| PNG-06 | Cari **Cairan Pembersih Mesin 5L** (batch sudah kedaluwarsa) di baris barang. | Batch yang sudah kedaluwarsa tidak ditawarkan di pilihan. | | |
| PNG-07 | Buat Pengeluaran baru. Tambah **Lem Kayu Fox 100 ml**, lalu pilih batch yang **lebih lambat** kedaluwarsa, padahal batch yang lebih cepat masih ada stok. | Form menampilkan peringatan kuning: batch yang dipilih kedaluwarsa lebih jauh padahal batch yang lebih cepat masih ada stok. Peringatan ini **tidak memblokir**: Konfirmasi tetap bisa. Bila ingin, Konfirmasi saja. | | |
| PNG-08 | Catat stok kedua batch Lem Kayu Fox sekarang (B1 = batch cepat, B2 = batch lambat). Buat Pengeluaran baru, pilih batch yang **paling cepat** kedaluwarsa, isi Jml = B1 + 5. Konfirmasi. | Sisa 5 otomatis diambil dari batch berikutnya. Setelahnya: batch cepat = 0, batch lambat = B2 - 5. Kartu Stok menunjukkan dua pergerakan keluar. | | |
| PNG-09 | Keluarkan barang sampai stok sama dengan atau di bawah Titik Pesan Ulang barang itu (mis. barang uji dengan titik 5). Konfirmasi. | Muncul peringatan titik pesan ulang. Barang muncul di tabel peringatan di Beranda. | | |
| PNG-10 | Pilih Penerima `Sari`, kosongkan Departemen. Konfirmasi. Buka dokumennya. | Departemen terisi dari departemen bawaan penerima (Admin). | | |
| PNG-11 | Di isian Penerima, tekan tombol **+** (Tambah baru) di sebelahnya. Isi nama, Simpan di kotak kecil. | Penerima baru langsung terpilih tanpa meninggalkan dokumen. Penerima itu juga muncul di master Penerima. | | |
| PNG-12 | Tambah barang uji dua kali dalam satu Pengeluaran. Simpan. | Ditolak: "Barang yang sama muncul di lebih dari satu baris." | | |
| PNG-13 | Buka Pengeluaran baru, tambah barang uji (faktor `box` sudah ada dari PNM-09). Cari pilihan satuan di baris itu. | Tidak ada pilihan satuan. Kolom Satuan hanya menampilkan satuan stok (`pcs`) dan tidak bisa diubah. Pengeluaran selalu dalam satuan stok, jadi Jml `1` mengurangi stok 1 pcs. | | |
| PNG-14 | Isi form dengan baris tak lengkap (barang kosong). Simpan. | Pesan "Pilih barang di setiap baris." Baris yang sudah diketik tetap tampil (tidak hilang). | | |

## 7. Penyesuaian Stok dan persetujuan Supervisor (ADJ)

Peran: Supervisor yang membuat dan menyetujui. Data Entry tidak punya menu ini.

| No | Langkah | Hasil yang diharapkan | Lulus/Gagal | Catatan |
|---|---|---|---|---|
| ADJ-01 | Sebagai `supervisor`, buka Penyesuaian Stok, + Data Baru. Kosongkan Alasan. Tambah barang uji. Simpan. | Ditolak: Alasan wajib. | | |
| ADJ-02 | Isi Alasan `Opname QC`. Pilih barang uji. Lihat Jml Sistem. Isi Jml Hitung = Jml Sistem + 3. | Jml Sistem terisi otomatis sama dengan stok saat ini. Selisih terhitung otomatis = +3. | | |
| ADJ-03 | Simpan, lalu Konfirmasi. | Status Menunggu Persetujuan. Stok **belum berubah**. | | |
| ADJ-04 | Tekan Setujui. | Status Disetujui. Stok barang uji bertambah 3 (sama dengan Jml Hitung). Kotak hitung "Menunggu persetujuan" di grid berkurang. | | |
| ADJ-05 | Buat penyesuaian baru dengan selisih minus (Jml Hitung lebih kecil). Konfirmasi lalu tekan Tolak. | Status Ditolak. Stok **tidak berubah**. | | |
| ADJ-06 | Buat penyesuaian barang uji, Konfirmasi (menunggu persetujuan). **Sebelum** menyetujui, buat dan Konfirmasi satu Pengeluaran barang uji Jml 1. Lalu kembali dan tekan Setujui. | Setujui ditolak: stok barang berubah sejak penghitungan. Pesan menyuruh Tolak lalu hitung ulang. Stok tidak berubah oleh penyesuaian itu. | | |
| ADJ-07 | Buka grid Penyesuaian Stok, pakai kotak hitung Menunggu persetujuan dan Draf. | Grid tersaring menurut kotak yang diklik. | | |
| ADJ-08 | Buka Laporan Opname & Penyesuaian. | Memuat penyesuaian tadi: stok sistem vs fisik, selisih, alasan, status, dampak nilai. | | |
| ADJ-09 | Sebagai `entry`, ketik alamat `/docs/adjustment` langsung. | Ditolak. | | |

## 8. Pembatalan dokumen (BTL)

Yang dibatalkan hanya dokumen yang sudah Terkonfirmasi/Disetujui. Draf tidak dibatalkan, tetapi dihapus.

| No | Langkah | Hasil yang diharapkan | Lulus/Gagal | Catatan |
|---|---|---|---|---|
| BTL-01 | Sebagai `entry`, buat dan Konfirmasi Penerimaan barang uji Jml 10 (catat stok sebelum = A). Buka dokumennya, tekan Batalkan. | Muncul kotak yang meminta Alasan. | | |
| BTL-02 | Isi alasan satu atau dua huruf saja (mis. `a`). Lanjutkan. | Ditolak: alasan minimal 3 huruf. | | |
| BTL-03 | Isi alasan `salah input QC`. Tekan Ya, lanjutkan. | Status menjadi Pembatalan Menunggu Persetujuan. Stok **belum berubah** (A + 10). | | |
| BTL-04 | Sebagai `supervisor`, buka dokumen itu (atau lihat kotak Dokumen menunggu persetujuan di Beranda). Tekan Setujui. | Status Dibatalkan. Stok kembali ke A. Di Riwayat dokumen ada nomor dokumen Penyesuaian Stok pembalik. | | |
| BTL-05 | Buka dokumen Penyesuaian pembalik tadi. Coba membatalkannya. | Alasan dokumen: "Pembalik otomatis pembatalan ..." dengan status Disetujui. Dokumen pembalik tidak bisa dibatalkan lagi. | | |
| BTL-06 | Sebagai `entry`, ajukan pembatalan dokumen lain. Sebagai `supervisor`, tekan Tolak pada pengajuan itu. | Dokumen kembali Terkonfirmasi. Stok tidak berubah. | | |
| BTL-07 | Sebagai `entry`, ajukan pembatalan, lalu buka dokumennya lagi dan tekan Batalkan sekali lagi. | Pengajuan ditarik; dokumen kembali Terkonfirmasi. Stok tidak berubah. | | |
| BTL-08 | Sebagai `supervisor`, tekan Batalkan pada dokumen Terkonfirmasi dan isi alasan. | Langsung Dibatalkan, tanpa persetujuan orang lain. Stok dikembalikan. | | |
| BTL-09 | Pakai **Barang Entry QC** dari BRG-05 (stok 0). Terima 10 (Terkonfirmasi). Keluarkan 8 (Terkonfirmasi), sehingga tersisa 2. Buka Penerimaan tadi. | Form menampilkan peringatan sebelum tombol ditekan bahwa stok tidak cukup untuk menarik penerimaan itu. | | |
| BTL-10 | Lanjutan BTL-09: tetap tekan Batalkan pada Penerimaan itu (sebagai Supervisor) dan isi alasan. | Ditolak: "Pembatalan tidak bisa dilakukan: stok ... tinggal ..., tidak cukup untuk menarik kembali ...". Disarankan Penyesuaian Stok. Stok tidak berubah. | | |
| BTL-11 | Buat Draf dan buka. Cari tombol. | Tombol Hapus ada, tombol Batalkan tidak. Setelah Konfirmasi: Hapus hilang, Batalkan muncul. | | |
| BTL-12 | Buka Kartu Stok (bagian 9) untuk barang yang dokumennya dibatalkan. | Baris pembalik ditandai "(BATAL)". Baris asal tetap ada (riwayat tidak dihapus). | | |
| BTL-13 | Catat satu Pemesanan Ulang dengan Jumlah Dipesan `10` untuk barang X (lihat `ROP-03`). Terima barang X Jml `10` lewat Penerimaan dan Konfirmasi (pesanan tertutup). Batalkan Penerimaan itu. | Barang itu kembali berstatus "Dipesan" (hijau) di tabel peringatan Beranda, bila stoknya masih di/bawah titik pesan ulang. | | |
| BTL-14 | Batalkan Penerimaan, lalu cek Laporan Penerimaan di periode itu. | Dokumen yang dibatalkan tidak dihitung di laporan. | | |
| BTL-15 | Buka form Penyesuaian yang masih Menunggu Persetujuan, tekan Batalkan. | Pengajuan ditarik tanpa pembalikan stok (stok memang belum berubah). | | |

## 9. Kartu stok dan laporan (LAP)

| No | Langkah | Hasil yang diharapkan | Lulus/Gagal | Catatan |
|---|---|---|---|---|
| LAP-01 | Buka Kartu Stok. Jangan pilih barang. Terapkan. | Barang wajib dipilih. | | |
| LAP-02 | Pilih barang uji, periode mencakup hari ini. Terapkan. | Daftar pergerakan: saldo awal, masuk, keluar, penyesuaian. Saldo berjalan di tiap baris. Saldo akhir sama dengan stok di grid Barang. Ada kolom dokumen dan user. | | |
| LAP-03 | Buka Ringkasan Stok untuk periode yang mencakup transaksi Anda. | Per barang: saldo awal + masuk - keluar +/- penyesuaian = saldo akhir. Cek satu barang dengan menghitung sendiri. | | |
| LAP-04 | Di Ringkasan Stok, centang "Hanya barang di / bawah titik pesan ulang". | Hanya barang yang di/bawah titik tampil. | | |
| LAP-05 | Sebagai `entry`, buka laporan di atas. Lalu sebagai `supervisor`. | Harga, biaya, dan nilai **hanya** tampil untuk Supervisor. | | |
| LAP-06 | Buka Laporan Penerimaan dan Laporan Pengeluaran, filter ke hari ini. | Memuat dokumen yang berlaku hari ini. Draf dan dokumen Dibatalkan **tidak** dihitung. | | |
| LAP-07 | Buka Laporan Kedaluwarsa (bawaan 30 hari). | Memuat batch yang masih ada stok dan sudah atau hampir kedaluwarsa, mulai yang paling dulu. Kolom Sisa Hari menunjukkan sisa hari tiap batch. Cairan Pembersih Mesin 5L ada di daftar. | | |
| LAP-08 | Ubah saringan "Kedaluwarsa dalam (hari)" menjadi `300` lalu Terapkan. | Daftar bertambah: batch yang kedaluwarsa dalam 300 hari ikut tampil (mis. batch Lem Kayu Fox dan Cat Semprot Pelindung yang tadinya tidak tampil). | | |
| LAP-09 | Tekan Cetak di salah satu laporan. | Muncul dialog cetak browser dengan isi laporan. | | |
| LAP-10 | Ubah Dari tanggal lebih besar dari Sampai tanggal. Terapkan. | Tidak ada error teknis. Hasil kosong atau pesan yang jelas. | | |
| LAP-11 | Pakai barang uji yang punya Saldo Awal (`SAL-04`). Buka Kartu Stok, isi Dari tanggal = tanggal Saldo Awal, Terapkan. Lalu ubah Dari tanggal menjadi sehari sesudahnya, Terapkan. | Pertama: baris bertipe AWAL tampil di daftar dan kotak Saldo Awal di atas bernilai 0. Kedua: baris AWAL tidak tampil, dan jumlah Saldo Awal sudah masuk ke kotak Saldo Awal di atas. Saldo akhir sama di keduanya. | | |

## 10. Titik pesan ulang (ROP)

| No | Langkah | Hasil yang diharapkan | Lulus/Gagal | Catatan |
|---|---|---|---|---|
| ROP-01 | Sebagai `supervisor`, lihat Beranda. | Kotak "Di / bawah titik pesan ulang" menunjukkan jumlah. Tabel peringatan menampilkan barang dengan stok tersedia <= titik, dengan kolom Status Pesanan. | | |
| ROP-02 | Klik kotak "Di / bawah titik pesan ulang". | Membuka tab menu terkait. | | |
| ROP-03 | Pada Beranda tekan Catat Pemesanan. Pilih barang yang ada di tabel peringatan, pilih Supplier `Kontan`, isi Jumlah Dipesan `10`, Tanggal Pesan dan No. Referensi `REF-QC-1`. Simpan. | Baris barang itu berubah dari merah "Belum dipesan" menjadi hijau "Dipesan tgl ... - REF-QC-1". | | |
| ROP-04 | Terima barang itu lewat Penerimaan Barang Jml `10` (sama dengan Jumlah Dipesan), Konfirmasi. Lihat Beranda. | Catatan pesanan tertutup otomatis karena jumlah diterima memenuhi jumlah dipesan. Bila stok masih di/bawah titik, barang tetap di tabel, dengan status kembali "Belum dipesan" (merah). | | |
| ROP-05 | Buat barang dengan Titik Pesan Ulang `0` dan stok 0. | Tidak ada peringatan untuk barang itu (0 = tanpa peringatan). | | |
| ROP-06 | Pada barang berkedaluwarsa yang punya batch sudah lewat (Cairan Pembersih Mesin 5L), bandingkan Stok dan Stok Tersedia. | Stok tersedia tidak menghitung batch yang sudah kedaluwarsa. Status PESAN ULANG dihitung dari stok tersedia, jadi barang bisa PESAN ULANG walau rak masih berisi stok kedaluwarsa. | | |
| ROP-07 | Ubah Titik Pesan Ulang barang uji menjadi lebih besar dari stoknya. Buka Beranda dan grid Barang. | Barang masuk daftar peringatan dan berstatus PESAN ULANG (merah muda). | | |
| ROP-08 | Sebagai `entry`, lihat Beranda. | Tabel peringatan tampil, kotak Nilai stok tidak tampil. | | |
| ROP-09 | Catat pesanan baru untuk barang yang menipis, Jumlah Dipesan `10`. Terima hanya `4` lewat Penerimaan Barang, Konfirmasi (pilih barang yang Titik Pesan Ulang-nya cukup tinggi supaya tetap tampil di tabel). Lihat Beranda. | Status menjadi kuning "Diterima sebagian tgl · ref". Keterangan memuat "Diterima sejak dipesan: 4 dari 10". Pesanan tetap terbuka. | | |
| ROP-10 | Pada pesanan ROP-09 tekan **Tutup Pesanan**, isi alasan `supplier tidak kirim sisanya`, lalu tekan Tutup Pesanan di jendela itu. | Pesanan tertutup (tidak bisa dibuka lagi). Status kembali "Belum dipesan" bila stok masih menipis. Penerimaan 4 tadi tetap tercatat. Anda bisa Catat Pemesanan lagi. | | |
| ROP-11 | Pada Catat Pemesanan isi Jumlah Dipesan `0`, lalu ulangi dengan Tanggal Pesan besok. Simpan. | Keduanya ditolak dengan pesan merah ("Jumlah dipesan harus lebih dari 0." untuk jumlah 0). Pesanan tidak tercatat. | | |

### Riwayat Pemesanan (ORH)

Menu Transaksi > Riwayat Pemesanan. Lanjutan dari ROP-03 sampai ROP-10 (ada pesanan Dipesan, Diterima sebagian, dan Ditutup manual).

| No | Langkah | Hasil yang diharapkan | Lulus/Gagal | Catatan |
|---|---|---|---|---|
| ORH-01 | Sebagai `entry`, buka Transaksi > Riwayat Pemesanan. | Grid memuat semua pesanan dengan kolom Tanggal Pesan, No. Referensi Pemesanan, Barang, Supplier, Jumlah Dipesan, Diterima, Sisa, Status, Pertama Diterima, Lead Time (hari), Dicatat oleh, Ditutup. Tidak ada harga atau nilai uang. | | |
| ORH-02 | Klik kotak **Terbuka**, **Selesai**, **Ditutup manual**, lalu **Semua**. | Grid tersaring menurut status. Terbuka = Dipesan dan Diterima sebagian. Pesanan Selesai dan Ditutup manual yang hilang dari Beranda ada di sini. | | |
| ORH-03 | Atur Dari tanggal / Sampai tanggal, Terapkan. Pakai Saring untuk satu Supplier dan kotak Cari. | Hanya pesanan dalam rentang tanggal pesan, supplier, dan kata cari itu yang tampil. Kosongkan tanggal: semua tampil. | | |
| ORH-04 | Klik 2x pesanan berstatus Diterima sebagian. | Detail: data pesanan, kotak Jumlah Dipesan / Diterima / Sisa, dan tabel Penerimaan yang dialokasikan (nomor RCV, tanggal, jumlah) yang jumlahnya sama dengan Diterima. Tombol Tutup Pesanan ada. | | |
| ORH-05 | Pada detail itu tekan Tutup Pesanan, isi alasan, tekan Tutup Pesanan di jendela. | Kembali ke detail yang sama. Status Ditutup manual, kolom Ditutup memuat tanggal dan alasan, tombol Tutup Pesanan hilang. | | |
| ORH-06 | Buka detail pesanan Ditutup manual dan pesanan Selesai. | Ditutup manual: ada keterangan bahwa penerimaan tidak dirinci. Selesai: tabel penerimaan terisi dan tidak ada tombol Tutup Pesanan. | | |
| ORH-07 | Beri Grup hak **Hanya baca (RO)** untuk Riwayat Pemesanan dan masuk sebagai anggotanya. Buka detail pesanan terbuka. | Grid dan detail terbuka, tetapi tombol Tutup Pesanan tidak tampil. | | |
| ORH-08 | Pakai Grup tanpa modul Riwayat Pemesanan (atau RO). Masuk sebagai anggotanya, buka Beranda dengan barang yang menipis. | Tombol Catat Pemesanan dan Tutup Pesanan tidak tampil. Bila alamat simpannya dipanggil langsung, Stoku menolak (403, tidak ada pesanan tercatat). Dengan hak RW, keduanya berfungsi. | | |

## 11. Satuan dan konversi (SAT)

Peran: Supervisor. Data Entry tidak punya menu Satuan, tetapi boleh mengisi faktor satuan lewat jendela di dokumen.

| No | Langkah | Hasil yang diharapkan | Lulus/Gagal | Catatan |
|---|---|---|---|---|
| SAT-01 | Sebagai `supervisor`, buka Satuan. | Satuan bawaan tampil (pcs, box, set, pack, roll, can, bottle, dst.) dengan jenis, jumlah konversi, dan jumlah barang pemakai. | | |
| SAT-02 | + Data Baru: Kode `lusin`, Nama `Lusin`. Simpan. Buka lagi dan coba ubah kode. | Tersimpan. Kode terkunci setelah disimpan, nama boleh diubah. | | |
| SAT-03 | Buat satuan dengan kode yang sudah ada (mis. `pcs`). | Ditolak: kode harus unik. Coba juga kode berisi spasi (mis. `ab cd`): ditolak. Huruf besar otomatis diubah menjadi huruf kecil. | | |
| SAT-04 | Buka `lusin`, tekan + Tambah Konversi: satuan tujuan `pcs`, Faktor `12`. Simpan. | Tersimpan. Arah sebaliknya (1 pcs = 1/12 lusin) dihitung otomatis. | | |
| SAT-05 | Tambah konversi ke satuan itu sendiri (lusin ke lusin). | Ditolak. | | |
| SAT-06 | Tambah konversi dengan Faktor `0`. | Ditolak. | | |
| SAT-07 | Terima barang uji (`pcs`) dengan satuan `lusin` Jml 2. Konfirmasi. | Stok barang uji bertambah 24 pcs. | | |
| SAT-08 | Nonaktifkan satuan `lusin`. Buat barang baru dan lihat pilihan Satuan. | `lusin` tidak muncul di pilihan untuk barang baru. Barang lama yang sudah memakainya tidak rusak. | | |
| SAT-09 | Sebagai `entry`, buka Penerimaan dengan satuan yang faktornya belum ada untuk barang itu, lalu isi di jendela "Faktor satuan belum ada". | Data Entry bisa menyimpan faktor dari jendela itu tanpa membuka master Satuan. Faktor menjadi Satuan masuk milik barang itu, bukan konversi umum. | | |

## 12. Pengguna dan grup (PGN)

Peran: Administrator.

| No | Langkah | Hasil yang diharapkan | Lulus/Gagal | Catatan |
|---|---|---|---|---|
| PGN-01 | Sebagai `admin`, buka Pengguna. | Tiga akun uji tampil dengan perannya. | | |
| PGN-02 | + Data Baru: Nama `Penguji QC`, Username `qc1`, Email `qc1@sstock.test`, Peran Data Entry, Password pilihan Anda (min. 8 karakter, catat sendiri). Simpan. | Tersimpan. Username terkunci setelahnya. | | |
| PGN-03 | Buat pengguna dengan password 5 karakter. | Ditolak: password minimal 8 karakter. | | |
| PGN-04 | Buat pengguna dengan username yang sudah ada (`entry`). | Ditolak: username harus unik. | | |
| PGN-05 | Keluar, masuk sebagai `qc1`. | Berhasil masuk dengan hak Data Entry. | | |
| PGN-06 | Sebagai `admin`, nonaktifkan `qc1`. Keluar, coba masuk sebagai `qc1`. | Tidak bisa masuk. Riwayat transaksinya tetap ada. Bila `qc1` sedang masuk di browser lain, klik berikutnya di sana membuatnya keluar. | | |
| PGN-07 | Sebagai `admin`, buka akun `admin` sendiri. Coba nonaktifkan dan ubah perannya. | Ditolak: akun sendiri tidak bisa dinonaktifkan atau diubah perannya. | | |
| PGN-08 | Buka Grup. + Data Baru: kode `QCRO`, nama `Uji Baca Saja`. Pada tabel Akses per modul pilih RO untuk Barang, dan pastikan modul lain Tidak ada. Simpan. | Grup tersimpan. | | |
| PGN-09 | Aktifkan `qc1` lagi, ubah Grup-nya ke `QCRO`. Masuk sebagai `qc1`. | Menu hanya Barang. Barang bisa dibuka tetapi menyimpan perubahan ditolak dengan pesan akses hanya-baca (RO). | | |
| PGN-10 | Sebagai `qc1`, ketik langsung alamat `/suppliers`. | Ditolak karena modul tidak diberikan grup. | | |
| PGN-11 | Sebagai `admin`, ubah Grup `QCRO` menjadi RW untuk Barang. Masuk lagi sebagai `qc1`. | Sekarang bisa menyimpan di Barang. | | |
| PGN-12 | Beri grup `QCRO` akses RW ke modul Satuan, lalu masuk sebagai `qc1` (peran Data Entry). | Modul Satuan tetap tertutup (peran Data Entry kurang tinggi, walau dicentang). | | |
| PGN-13 | Nonaktifkan Grup `QCRO`. Buka form Pengguna baru. | Grup nonaktif tidak muncul di pilihan Grup. Anggota yang ada tidak berubah. | | |
| PGN-14 | Buka Daftar Modul. | Daftar baca-saja semua modul, peran minimal, dan grup yang boleh membukanya. | | |

## 13. Umum dan tampilan (UMM)

| No | Langkah | Hasil yang diharapkan | Lulus/Gagal | Catatan |
|---|---|---|---|---|
| UMM-01 | Di layar mana pun, tekan F1. | Panduan Pengguna terbuka (Bahasa Indonesia). | | |
| UMM-02 | Sebagai `admin`, buka Bantuan lalu cari tab Panduan Teknis. Ulangi sebagai `supervisor`. | Admin melihat tab Panduan Teknis (Bahasa Inggris). Supervisor tidak melihat tab itu, dan alamat `/help/technical-guide` ditolak. | | |
| UMM-03 | Buka form apa pun, tekan Kembali. | Selalu menuju grid modul itu. | | |
| UMM-04 | Kecilkan jendela browser hingga selebar layar HP. | Halaman tidak melebar ke samping; menu tetap bisa dipakai. | | |
| UMM-05 | Buka beberapa menu sekaligus (sebagai tab kerja), pindah-pindah. | Isian tab yang belum disimpan tidak hilang saat berpindah tab. | | |
| UMM-06 | Pada grid mana pun: klik judul kolom, ubah Tampil 10/25/50, pakai Kolom untuk menyembunyikan kolom. | Pengurutan, jumlah baris, dan kolom tampil berubah sesuai pilihan. | | |
| UMM-07 | Ganti bahasa ke English lewat menu profil (Bahasa), lalu kembalikan ke Indonesia. | Tampilan berganti, lalu kembali. Tidak ada galat. | | |

## 13b. Beranda: sapaan, cuaca, dan baris jaringan (BRN)

Peran: semua. Uji di komputer dan di HP (sekitar 360 x 790). Cuaca butuh internet. Lokasi perangkat hanya bisa dipakai bila Stoku dibuka lewat HTTPS atau localhost; lewat `http://stoku.test` atau alamat IP yang dipakai adalah kota cadangan dari layar Perusahaan (CMP-12).

| No | Langkah | Hasil yang diharapkan | Lulus/Gagal | Catatan |
|---|---|---|---|---|
| BRN-01 | Masuk, lihat bagian atas Beranda komputer. | Satu baris sapaan sesuai jam (misalnya "Selamat siang, Budi": nama depan, tanpa emoji) dan di bawahnya tanggal dan jam kecil ("Jumat, 23 Oktober 2026 · 15:13"). Tidak memakan banyak tinggi. | | |
| BRN-02 | Kosongkan Kota, Lintang, Bujur di Perusahaan, lalu buka Beranda lewat `http://stoku.test` atau alamat IP. | Tidak ada kartu cuaca dan sapaan tetap rapi. | | |
| BRN-03 | Isi Kota `Malang`, Lintang `-7.98`, Bujur `112.63` di Perusahaan, buka Beranda lewat `http://stoku.test`. | Kartu biru tua kecil di kanan sapaan: ikon, suhu, "Gerimis · Malang" (kondisi mengikuti cuaca sesungguhnya). Browser tidak meminta izin lokasi (alamat tanpa HTTPS). | | |
| BRN-04 | Bila ada akses HTTPS: buka Beranda, tekan **Blokir** pada permintaan lokasi, lalu muat ulang beberapa kali. Lalu ubah izin menjadi Izinkan. | Saat diblokir: kartu memakai kota cadangan dan browser tidak menanyakan lagi. Saat diizinkan: kartu memakai kota posisi perangkat (nama kota dari posisi, bukan dari Perusahaan). | | |
| BRN-05 | Matikan internet sebentar lalu muat ulang Beranda (cache lokasi satu jam dapat tampil). | Kartu cuaca diam atau tidak tampil; tidak ada pesan galat, pekerjaan lain tidak terganggu. | | |
| BRN-06 | Di HP buka Beranda. | Sapaan satu baris; di bawahnya tanggal singkat ("Jum, 23 Okt 2026 · 15:13") dengan label kecil cuaca ("25°C · Malang") di sebelahnya bila ada cuaca; tidak tertutup tombol hamburger; tidak ada gulir ke samping. | | |
| BRN-07 | Di komputer lihat bawah nama pengguna di kiri bawah; ketuk nama untuk membuka panel profil. Di HP ketuk hamburger, lalu Menu. | Satu baris kecil dan pudar "IP perangkat → IP server" di bawah nama dan jabatan, juga terlihat di bawah panel profil. Di HP baris itu ada di bawah nama pada daftar Menu. Sentuh atau arahkan: nama komputer muncul sebagai tooltip. Semua pengguna melihatnya. | | |
| BRN-08 | Putuskan server (hentikan situs atau cabut kabel jaringan server) dan tunggu sekitar 15 detik. | IP server berkedip merah muda dan tertulis "Server tidak terjangkau". Hidupkan kembali: tulisan hilang sendiri dalam beberapa detik. Masa masuk tidak bertambah (keluar otomatis tetap 30 menit). | | |
| BRN-09 | Matikan Wi-Fi perangkat (mode pesawat). Lalu nyalakan lagi. | IP perangkat berkedip kuning dan tertulis "Perangkat offline"; kembali normal sendiri setelah tersambung. | | |
| BRN-10 | Nyalakan pengaturan sistem "kurangi gerakan", ulangi BRN-08. | Tidak ada kedipan; warna dan tulisan status tetap muncul. | | |
| BRN-11 | Lihat judul logo di panel kiri dan halaman masuk (komputer dan HP). Sentuh tulisan versinya. Lalu buat satu commit baru (atau minta pengembang) dan muat ulang. | Tulisan kecil dan pudar seperti `ver 0.133 A` di samping STOKU (panel kiri) dan di bawah formulir masuk; tooltip `alpha`. Angka naik satu setiap commit baru tanpa menyunting berkas. | | |

## 14. Pengaturan ulang data contoh (RST)

Kasus ini **menghapus semua data**. Kerjakan terakhir. Layar Data Contoh hanya berfungsi bila `.env` berisi `SSTOCK_SAMPLE_REFRESH=true` (lihat README Langkah 4).

| No | Langkah | Hasil yang diharapkan | Lulus/Gagal | Catatan |
|---|---|---|---|---|
| RST-01 | Sebagai `supervisor`, ketik alamat `/sample`. | Ditolak (hanya Administrator). | | |
| RST-02 | Sebagai `admin`, buka Keamanan, Data Contoh. Baca isinya. | Terlihat jumlah Barang, Batch, Dokumen stok, Pergerakan stok, Pengguna, dan waktu penyegaran terakhir. | | |
| RST-03 | Kosongkan kotak konfirmasi atau ketik `segarkan` (huruf kecil), lalu tekan Segarkan Data Contoh. | Ditolak: "Ketik SEGARKAN (huruf besar) untuk melanjutkan." Data tidak berubah. | | |
| RST-04 | Ketik `SEGARKAN`, tekan Segarkan Data Contoh. | Data diganti data contoh segar. Anda keluar otomatis dan harus masuk lagi. Data yang Anda buat selama uji hilang (barang uji, penerimaan, akun `qc1`). | | |

---

## 15. Stok Opname (OPS)

Peran: Supervisor memulai, meninjau, dan menyetujui. Data Entry mengisi hasil hitung dan mencetak lembar. Pakai barang uji dari `BRG-02` yang sudah punya stok (mis. dari `PNM`); catat **stok sebelum**.

| No | Langkah | Hasil yang diharapkan | Lulus/Gagal | Catatan |
|---|---|---|---|---|
| OPS-01 | Sebagai `entry`, buka menu Transaksi > Stok Opname. | Daftar tampil. Tombol **Mulai Opname** tidak ada. Mengetik alamat `/stock-counts/create` langsung: ditolak. | | |
| OPS-02 | Sebagai `supervisor`, tekan Mulai Opname. Biarkan Cakupan Semua barang, Hitung buta Ya, Kunci transaksi Ya, Ambang 5. Simpan. | Opname baru bernomor `SO-YYMM-0001`, status **Sedang dihitung**. Lembar berisi satu baris per batch yang punya stok. Stok barang **belum berubah**. | | |
| OPS-03 | Coba Mulai Opname lagi selagi opname tadi berjalan. | Ditolak dengan pesan: hanya satu opname boleh berjalan, sebut nomor yang masih berjalan. | | |
| OPS-04 | Tekan **Cetak Lembar Hitung**. | Lembar A4 terbuka: dikelompokkan per Lokasi lalu kode; kolom Hasil Hitung, Penghitung, Catatan kosong. **Tidak ada** kolom Stok Sistem (hitung buta). | | |
| OPS-05 | Sebagai `entry`, buka opname itu. Pada barang uji isi **Hasil Hitung** = 0, lalu isi Penghitung `Budi`, lalu pindah sel. | Tersimpan otomatis. Baris terhitung terisi (0 dianggap terisi). Kotak Terisi bertambah, Belum diisi berkurang. Layar tidak menampilkan stok sistem. | | |
| OPS-06 | Kosongkan lagi Hasil Hitung baris itu. | Baris kembali Belum diisi (kosong = belum dihitung, bukan nol). | | |
| OPS-07 | Pada satu baris lain, centang **Tidak dihitung** tanpa Catatan, lalu tekan **Selesai Hitung**. | Selesai Hitung ditolak; pesan menyebut kode barang yang belum terisi / belum lengkap. | | |
| OPS-08 | Isi Catatan baris itu. Isi semua baris lain (barang uji: isi Jml sistem dikurangi 2 bila Anda tahu stoknya). Tekan **Selesai Hitung**. | Status **Tinjau**. Isian terkunci. | | |
| OPS-09 | Selagi opname berjalan, di Penerimaan Barang buat draf lalu tekan Konfirmasi. | Draf tersimpan, tetapi Konfirmasi ditolak: "Stok Opname SO-... sedang berjalan, transaksi dikunci. Hubungi Supervisor." Stok tidak berubah. | | |
| OPS-10 | Sebagai `supervisor`, buka opname (Tinjau). | Layar tinjau: Stok Sistem, Fisik, Selisih, Persen, Nilai, kolom Besar. Ringkasan nilai lebih dan kurang. Sebagai `entry` layar ini tidak ada dan tidak ada nilai. | | |
| OPS-11 | Pada baris selisih di atas 5 persen, biarkan Alasan kosong. Tekan **Setujui Opname**. | Ditolak: Alasan wajib; pesan menyebut kode barangnya. Stok tidak berubah. | | |
| OPS-12 | Isi Alasan `Rusak`. Pada satu baris isi **Hitung Ulang** dengan angka lain. | Selisih baris itu dihitung ulang memakai angka hitung ulang; halaman dimuat ulang dan ringkasan ikut berubah. | | |
| OPS-13 | Tekan **Pratinjau Berita Acara**. | Terbuka dengan tulisan PRATINJAU, ringkasan, daftar penghitung, baris selisih beserta alasan, dan tiga kotak tanda tangan (Penghitung, Supervisor, Penanggung Jawab Gudang). | | |
| OPS-14 | Tekan **Setujui Opname** lalu OK. | Status **Selesai**. Muncul satu Penyesuaian Stok baru berstatus Disetujui dengan alasan "Stok Opname SO-...". Stok barang uji sama dengan hasil hitung fisik. Kunci transaksi lepas. | | |
| OPS-15 | Buka Kartu Stok barang uji. | Ada baris jenis Penyesuaian dengan keterangan "Stok Opname SO-...". | | |
| OPS-16 | Ulangi Mulai Opname, isi semua baris sama dengan stok sistem, Selesai Hitung, Setujui. | Selesai tanpa membuat Penyesuaian (tidak ada selisih). | | |
| OPS-17 | Mulai opname baru dengan Kunci transaksi **Tidak**. Sebelum Setujui, buat dan Konfirmasi satu Penerimaan barang uji. Selesai Hitung lalu coba Setujui. | Setujui ditolak: stok bergeser sejak cut-off, daftar barisnya disebut. Tombol **Perbarui Baris Bergeser** mengosongkan baris itu dan mengembalikan opname ke Sedang dihitung. | | |
| OPS-18 | Pada opname yang berjalan, tekan **Kembalikan ke Penghitungan** tanpa catatan, lalu dengan catatan. | Tanpa catatan ditolak. Dengan catatan: status Sedang dihitung dan catatan tampil. | | |
| OPS-19 | Pada opname yang berjalan dengan kunci aktif, tekan **Buka Kunci**, lalu **Kunci**. | Kunci terbuka (tercatat siapa dan kapan di kepala layar), Konfirmasi Penerimaan berhasil. Sesudah **Kunci**, Konfirmasi ditolak lagi dan kepala layar mencatat siapa yang mengunci. | | |
| OPS-20 | Tekan **Batalkan Opname**. | Status Dibatalkan. Kunci lepas. Stok tidak berubah. | | |
| OPS-21 | Sebagai `entry`, buka opname yang berstatus Sedang dihitung atau Tinjau. | Tombol Buka Kunci, Batalkan Opname, Setujui Opname, dan Kembalikan ke Penghitungan **tidak tampil**. Layar tinjau dan Berita Acara tidak bisa dibuka (ditolak bila alamatnya diketik). | | |
| OPS-22 | Sebagai `supervisor`, buka daftar Stok Opname. Ubah **Jadwal opname** menjadi Mingguan, hari Senin, Simpan. Lalu ubah lagi jadi Bulanan, akhir bulan. | Jadwal tersimpan dan tampil di atas daftar. Sebagai `entry`, jadwal tampil tetapi tidak bisa diubah (tidak ada tombol Simpan Jadwal). | | |
| OPS-23 | Buka Beranda sebagai `entry` dan `supervisor` (tidak ada opname berjalan). | Pengingat "Stok Opname terakhir ... Berikutnya ..." (atau "Belum pernah ada Stok Opname"). Warna biasa bila masih jauh, kuning bila H-3 atau lebih dekat, merah "Terlambat N hari". Tombol **Mulai Opname** hanya untuk `supervisor`. | | |
| OPS-24 | Mulai opname baru. Buka Beranda lagi. | Pengingat berubah menjadi "Stok Opname SO-... sedang berjalan (terisi N dari M)". Item menu Stok Opname memakai lencana kuning. | | |
| OPS-25 | Buka lembar input yang sama di dua jendela (dua akun, mis. `entry` dan `supervisor`). Di jendela 1 isi baris X. Di jendela 2 (belum dimuat ulang) isi baris X dengan angka lain. | Jendela 2 menampilkan peringatan "Baris ... sudah diubah oleh ... pada ...". Isi alasan lalu Simpan menimpa (angka jendela 2 menggantikan, tidak dijumlahkan); Batal membuang isiannya. | | |
| OPS-26 | Pada opname Tinjau, lihat kolom **Diinput oleh**, lalu buka Berita Acara. | Kolom menyebut akun penginput tiap baris. Berita Acara memuat baris "Diinput oleh (akun)". Bila Anda sendiri penginput sebagian besar baris, muncul peringatan di layar Tinjau (tetap bisa disetujui). | | |
| OPS-27 | Pada opname yang sudah Selesai, coba ubah isian, Hitung Ulang, Kembalikan, Batalkan. | Semua ditolak atau tidak tersedia: hasil Selesai permanen. Koreksi lewat Penyesuaian Stok. | | |

## 16. Bantuan di dalam aplikasi (HLP)

Bantuan adalah Panduan Pengguna (Bahasa Indonesia). Bagian per modulnya hanya tampil untuk yang boleh membuka modul itu.

| No | Langkah | Hasil yang diharapkan | Lulus/Gagal | Catatan |
|---|---|---|---|---|
| HLP-01 | Sebagai `entry`, tekan F1 (atau menu profil, Bantuan & dukungan). | Panduan Pengguna terbuka. Daftar isi ada di kiri. Bagian 1 "Mulai di sini" memuat peta menu dan urutan hari pertama. Tidak ada bagian Saldo Awal, Penyesuaian Stok, Satuan, Kategori, Lokasi Penyimpanan, atau Keamanan. | | |
| HLP-02 | Klik setiap tautan di daftar isi. | Halaman bergulir ke bagian yang dimaksud. Tidak ada tautan yang mati. | | |
| HLP-03 | Buka Bantuan sebagai `supervisor`, lalu sebagai `admin`. | Supervisor melihat juga Saldo Awal, Penyesuaian Stok, Satuan, Kategori, Lokasi Penyimpanan, dan Laporan Opname & Penyesuaian, tanpa bagian Keamanan. Administrator melihat juga Keamanan (Pengguna, Grup, Daftar Modul, Data Contoh). | | |
| HLP-04 | Buka bagian Mulai di sini, Catat Pemesanan, dan Stok Opname. Lihat gambar (diagram) di dalamnya. | Gambar tampil di dalam halaman, tidak terpotong, tulisannya terbaca. Gambar mengikuti tema terang atau gelap. | | |
| HLP-05 | Ikuti satu bagian panduan di layar nyata (mis. Catat Pemesanan atau Mulai Opname). Bandingkan nama tombol dan isian. | Nama tombol dan isian di panduan sama dengan di layar. Bila beda, laporkan nama bagian dan tulisan yang berbeda. | | |

## 17. Perusahaan dan kop cetak (CMP)

Peran: Supervisor mengisi. Kop (bagian atas kertas) semua cetakan mengikuti menu **Data Master > Perusahaan**. Nama aplikasi (halaman masuk, menu, judul) tetap Stoku. Siapkan satu berkas logo PNG (bukan SVG) di bawah 1 MB.

| No | Langkah | Hasil yang diharapkan | Lulus/Gagal | Catatan |
|---|---|---|---|---|
| CMP-01 | Sebagai `supervisor`, buka Data Master > Perusahaan. Lalu sebagai `entry`, cari menu itu dan ketik alamat `/company`. | Supervisor melihat satu formulir (Nama Perusahaan, Nama PT / CV, NPWP, Telepon, Email, Alamat, Logo, Tampilan kop) dan Pratinjau kop. Data Entry tidak punya menu itu dan alamat `/company` ditolak. | | |
| CMP-02 | Kosongkan Nama Perusahaan lalu Simpan. Lalu isi Nama `Maju Jaya`, Nama PT / CV `PT Maju Jaya Abadi`, NPWP, Telepon, Email, Alamat dua baris, Tampilan kop **Nama saja**. Simpan. | Tanpa nama: ditolak dengan pesan merah. Dengan nama: pesan hijau "Data perusahaan disimpan". Pratinjau kop berubah langsung saat Anda mengetik. | | |
| CMP-03 | Cetak satu Penerimaan Barang (tombol Cetak). Lihat kop. Lalu buka halaman masuk. | Kop memuat **Maju Jaya** tebal dan "PT Maju Jaya Abadi" kecil, tanpa logo dan tanpa alamat. NPWP tercetak. Halaman masuk dan menu tetap bernama Stoku. | | |
| CMP-04 | Unggah logo PNG, pilih **Logo + Nama + Alamat**, lihat pratinjau, Simpan. Cetak dokumen tadi lagi. | Pratinjau menampilkan logo sebelum disimpan. Cetakan: logo di kiri, nama, nama PT, dan alamat. | | |
| CMP-05 | Pilih **Logo + Nama**, Simpan, cetak. | Logo dan nama tampil, alamat tidak. | | |
| CMP-06 | Ubah Nama PT / CV menjadi `maju  JAYA` (huruf dan spasi berbeda), Simpan, cetak. | Nama PT tidak dicetak, karena sama dengan nama perusahaan (nama tidak ganda). | | |
| CMP-07 | Coba unggah: berkas `.svg`, berkas `.gif`, berkas teks yang diberi nama `logo.png`, dan gambar lebih dari 1 MB. | Semuanya ditolak dengan pesan merah di isian Logo. Logo lama dan data lain tidak berubah. | | |
| CMP-08 | Centang **Hapus logo**, Simpan (gaya masih Logo + Nama + Alamat). Buka formulir lagi dan cetak. | Logo hilang. Pratinjau memberi keterangan bahwa logo belum ada dan kop memakai Nama saja. Cetakan memuat nama saja. Pilihan gaya tetap tersimpan. | | |
| CMP-09 | Cetak Ringkasan Stok, Lembar Hitung dan Berita Acara Stok Opname (bagian 15). | Kop sama dengan cetakan dokumen: nama, nama PT, dan (bila dipilih gaya itu) logo dan alamat. NPWP hanya ada di cetakan dokumen stok. | | |
| CMP-10 | Saat logo ada, buka alamat logo (`/company/logo`) di jendela privat tanpa masuk. Hapus logo lalu buka alamat itu lagi. | Gambar logo tampil tanpa login. Setelah logo dihapus: halaman tidak ditemukan (404). | | |
| CMP-11 | Beri Grup hak **Hanya baca (RO)** untuk Perusahaan, masuk sebagai anggotanya (peran Supervisor), buka layar dan coba Simpan. | Layar terbuka, tetapi Simpan ditolak dengan pesan akses hanya-baca (RO). | | |
| CMP-12 | Di layar Perusahaan isi **Kota untuk cuaca** `Malang`, **Lintang** `-7,98`, **Bujur** `112.63`, Simpan. Lalu coba Lintang `95`, Bujur kosong (dengan lintang terisi), dan huruf `abc`. | Nilai sah tersimpan (koma desimal diterima) dan terbaca lagi di formulir. Lintang di luar -90..90, bujur kosong saat lintang terisi, dan huruf ditolak dengan pesan merah. | | |

## 18. Tampilan HP (HPV)

Peran: semua. Uji di HP sungguhan (Chrome Android, potret, sekitar 360 x 800) dan sekali di komputer. Siapkan satu opname berstatus Sedang dihitung (bagian 15). Urutan uji yang ringkas dengan kolom Android / iPhone: `docs/LEMBAR-UJI-HP.md`.

| No | Langkah | Hasil yang diharapkan | Lulus/Gagal | Catatan |
|---|---|---|---|---|
| HPV-01 | Di HP, buka halaman Masuk. Lalu di komputer, kecilkan jendela browser sampai lebarnya kurang dari 600 px dan muat ulang. | HP: halaman Masuk khusus HP (satu kolom, isian dan tombol besar, tanpa tombol Tampilan Desktop). Komputer sempit (belum memilih tampilan): langsung berpindah ke Tampilan HP tanpa bertanya, juga bila jendela dipersempit setelah halaman terbuka; popup **Ingin tampilan yang lebih nyaman di smartphone?** (Ketuk di sini / Tetap desktop) hanya cadangan. Sudah memilih: tidak diubah otomatis. Komputer lebar: tanpa popup. | | |
| HPV-02 | Di HP, masuk dengan Tampilan HP. | Beranda HP: kartu ringkasan besar dan satu ikon hamburger melayang di kiri atas (agak transparan); tidak ada bilah di atas maupun di bawah layar. Tidak ada yang terpotong; tidak ada gulir ke samping pada halaman. | | |
| HPV-03 | Di Beranda ketuk tombol hamburger. Lalu ketuk **Menu** di kanan. | Tombol hilang; bilah selebar layar muncul di atas: Beranda (aktif), Stok, Transaksi, Opname, Menu (≡ paling kanan, dipisah garis tipis). **Menu** membuka daftar semua modul yang boleh dibuka akun itu (sebagai `entry` tidak ada Keamanan) dan Keluar. Bilah menutup sendiri setelah memilih layar, gulir, ketuk di luar, atau diam sekitar 6 detik, lalu tombol melayang muncul lagi. | | |
| HPV-03b | Buka Barang > Data Baru, isi Nama Barang (form kotor). Ketuk tombol hamburger, lalu Menu, lalu Stok. | Titik kuning di tombol dan 'belum disimpan' di judul. Bilah aksi (Simpan biru, Urungkan, Kembali, Baru, Menu) tidak menutup sendiri selagi kotor. Pindah layar lewat menu meminta konfirmasi. Di daftar Barang: bilah berisi Cari, Data Baru, Saring, Ekspor, Menu; Saring membuka panel dari atas (Kategori, Satuan, Atur kolom, Terapkan) dan Ekspor membuka daftar format dari atas; ketuk area gelap atau Esc menutupnya. Di laporan: Cetak, Kertas, Grup, Hemat tinta, Menu; Kertas membuka panel A4 potret / A4 lanskap / A5 lanskap dan lembar cetak pas lebar layar. | | |
| HPV-04 | Pilih menu **Barang**. | Daftar Barang tampil sebagai kartu (nama + kode, lencana Level, stok, lokasi) di dalam kerangka HP; ketuk kartu membuka barangnya. Buka juga Penerimaan, Pengeluaran, Supplier, Riwayat Pemesanan: semuanya kartu, tanpa gulir ke samping untuk membaca daftar. Tombol kembali di sebelah hamburger ke Beranda. | | |
| HPV-05 | Di ponsel buka panel Menu sampai ujung bawah, lalu tutup browser dan buka lagi. | Tidak ada tombol Tampilan Desktop; tetap Tampilan HP (ponsel selalu Tampilan HP). | | |
| HPV-06 | Keluar. Di komputer sempit, tekan **Ketuk di sini** pada popup; lalu di halaman Masuk HP tekan **Tampilan Desktop**; lalu ulangi dan pilih **Tetap desktop**. | Ketuk di sini: halaman Masuk berubah menjadi versi HP. Tampilan Desktop: kembali ke login desktop tanpa popup lagi. Tetap desktop: popup tertutup dan tidak muncul lagi pada kunjungan berikutnya. | | |
| HPV-07 | Buka menu **Opname**, tekan **Hitung sekarang**. | Satu barang per kartu: nama, kode, rak; kolom jumlah besar dengan satuan; **Barang 1 / N**; tombol Sebelumnya dan Barang berikutnya menempel di bawah. Keyboard HP angka dengan koma desimal. Opname hitung buta: tidak ada stok sistem. | | |
| HPV-08 | Ketik 12,5 lalu Enter. | Pindah ke barang 2; tertulis Tersimpan; terisi bertambah. Di komputer, baris itu berisi 12,5. | | |
| HPV-09 | Pada barang 3 kosongkan jumlah dan tekan Barang berikutnya; pada barang 4 isi 0. | Barang 3 tetap Belum dihitung. Barang 4 dihitung nol (terisi). | | |
| HPV-10 | Pada barang 5 centang **Tidak dihitung** tanpa catatan. | Tertulis perlu catatan; baris belum dihitung terisi sampai catatan diisi. | | |
| HPV-11 | Ketik sebagian kode barang di kolom Cari lalu pilih hasilnya. Lalu ketik kode lengkap dan Enter (atau pindai barcode bila ada pemindai). | Melompat ke barang itu dan kolom jumlah siap diisi. Kode yang tidak ada: tertulis Tidak ketemu. | | |
| HPV-12 | Nyalakan mode pesawat, isi satu barang lalu pindah. Matikan mode pesawat. | Status merah **Gagal menyimpan**, isian tetap tampil. Sinyal kembali: terkirim sendiri atau lewat Coba lagi; berubah Tersimpan. | | |
| HPV-13 | Buka opname yang sama di komputer (akun lain), isi barang X. Di HP (belum dimuat ulang) isi barang X angka lain. | Peringatan: baris sudah diubah akun itu; wajib alasan untuk Timpa, atau Buang isian saya. | | |
| HPV-14 | Buka barang yang sudah diisi akun lain (muat ulang HP). | Peringatan kuning di kartu: siapa dan jam berapa mengisinya. | | |
| HPV-15 | Isi semua barang sampai barang terakhir lalu Barang berikutnya. | Ringkasan: N dari N terisi dan tombol **Selesai Hitung**. Bila ada yang belum: daftarnya, bisa disentuh. Selesai Hitung (OK): kembali ke daftar Opname HP; status Tinjau. | | |
| HPV-16 | Di komputer pastikan tampilan desktop (menu kiri, Beranda, Stok Opname) sama seperti sebelumnya. | Tidak ada perubahan, kecuali satu item **Tampilan HP** di menu profil. | | |
| HPV-17 | Di HP buka alamat server diikuti **/ukur** (tanpa masuk). Putar layar ke mendatar lalu kembali tegak. Ketuk **Salin hasil**, tempel ke chat. | Kotak ringkasan di atas (mis. `360 x 791 - DPR 3 - potret - phone`); angkanya berubah saat layar diputar. Tombol menyalin satu blok teks yang baris pertamanya sama dengan ringkasan. Pada alamat http tanpa https: teks hasil terbuka dan terpilih bila penyalinan otomatis tidak bisa. Tidak ada menu dan tidak ada data pengguna di halaman. | | |
| HPV-18 | Di HP buka Penerimaan > Data Baru (atau Pengeluaran), isi Penerima / Supplier, lalu lihat bagian Barang. Ketuk **+ Tambah Barang** sekali. | Tiap barang berupa satu kartu: nomor di kiri atas dengan tombol Simpan / Urungkan di kanan atas; Barang selebar layar; Tanggal Kedaluwarsa dan Jml sebaris, Satuan dan Harga Beli sebaris, Catatan Baris di bawahnya. Tidak ada gulir ke samping, huruf isian tidak membuat layar membesar (iOS), tombol mudah diketuk. Tampilan komputer tetap berupa tabel. | | |
| HPV-18b | Di HP buka Laporan > Ringkasan Stok, lalu Laporan Kedaluwarsa. Ketuk Terapkan, lalu pilih Grup (Per Kategori / Per Status) lewat bilah atau panel Grup. | Isinya daftar kartu yang terbaca (kode + nama di atas, kolom sebagai pasangan label / nilai), kotak kriteria dan ringkasan di atas, judul kelompok dengan jumlah baris; barang kedaluwarsa bermerah. Tidak ada lembar kecil yang tak terbaca dan tidak ada gulir ke samping. Ketuk Cetak: dialog cetak browser membuka lembar kertas (bukan kartu). Komputer tetap menampilkan lembar. | | |
| HPV-18c | Di HP (Administrator) buka Pengguna dan Grup, lalu Penerimaan > Data Baru dan ketuk **+ Tambah Barang**. Coba ketuk tombol Simpan / Urungkan di kartu barang, tombol kalender, dan tombol **aa** pada Catatan Baris. | Pengguna dan Grup berupa kartu (tidak ada ikon kecil di baris tabel). Tombol Simpan / Urungkan baris dan tombol kalender mudah diketuk dengan jempol (sekitar 44 px) tanpa salah ketuk tombol di sebelahnya; tombol **aa** bisa diketuk. Segmen Kertas / Grup di laporan juga mudah diketuk. | | |
| HPV-19 | Di HP buka Beranda lalu ketuk **Cari Barang**; periksa juga daftar **Menu** (hamburger > Menu). Ketik sebagian nama (mis. `baut`) lalu Enter atau Cari. | Kolom besar sudah aktif tanpa diketuk. Hasil berupa kartu: nama, kode, lokasi, lencana AMAN / PESAN ULANG, stok tersedia + satuan. Tidak ada gulir ke samping. Akun tanpa modul Barang tidak melihat tautannya; alamat `/m/find` ditolak (403). | | |
| HPV-19b | Ketik atau pindai **kode barang lengkap** (huruf besar-kecil bebas), lalu Enter. Ulangi dengan **kode batch** (`KODE-YYMMDD`) barang berkedaluwarsa. | Langsung membuka barangnya tanpa daftar hasil. Stok per batch berurutan kedaluwarsa paling awal dulu, batch tanpa tanggal paling bawah; tiap batch: jumlah, tanggal kedaluwarsa, sisa hari, lencana HAMPIR (30 hari atau kurang) / KEDALUWARSA. Pada kode batch, batch itu berbingkai biru. Kolom cari kosong dan aktif lagi untuk pindai berikutnya; mengetuk kolom memilih seluruh isinya. | | |
| HPV-19c | Cari kode yang tidak ada (mis. `TIDAK-ADA-9999`), lalu cari memakai kode lokasi (mis. `B1-02`). | Kode tidak ada: pesan Tidak ada barang yang cocok. Kode lokasi: barang di lokasi itu. Barang nonaktif tidak muncul. Masuk sebagai Data Entry: tidak ada Nilai stok; sebagai Supervisor: ada baris Nilai stok IDR pada barang. | | |
| HPV-30 | Di HP (lebar 360 sampai 430 px) masuk sebagai Administrator, buka Barang > Data Baru. Ketuk Kategori lalu pilih satu, buka Satuan, lalu ketuk tombol **huruf aa** di Nama Barang. | Satu kolom, nama isian di atas isiannya. Daftar pilihan: tiap baris setinggi jari (tidak sempit), kolom cari di dalamnya tidak memperbesar layar. Tombol aa mudah diketuk walau kecil. Tidak ada gulir ke samping. | | |
| HPV-30b | Di formulir Barang ketuk tombol **+** di samping Kategori. Ketuk tanda silang (X) di pojok dialog, buka lagi, kosongkan isian lalu ketuk Simpan. | Dialog muat di layar; X mudah diketuk; Batalkan / Simpan selebar separuh dialog dan mudah diketuk. Kosong lalu Simpan: pesan galat terbaca (huruf tidak terlalu kecil). | | |
| HPV-30c | Di formulir Barang yang sudah ada (Supervisor) lihat bagian Batch dan Satuan masuk, serta kotak **Saran pesan ulang**. Ketuk Tambah Satuan Masuk, isi, Simpan. | Batch dan Satuan masuk berupa kartu (judul kolom di kiri, nilai di kanan, tautan Ubah mudah diketuk), tanpa menggulir ke samping. Kotak saran: dua isian Hari pengaman / Hari cakupan selebar kotak dan mudah diketuk; tombol Pakai saran selebar kotak. | | |
| HPV-30d | Di HP buka Supplier > Data Baru, ketuk Simpan dengan isian kosong. | Isian wajib bertanda merah dengan pesan Wajib diisi yang terbaca; tab Data menunjukkan jumlah galat; bisa digulir ke isian pertama yang salah. | | |
| HPV-30e | Di HP buka Master Data > Perusahaan. Isi alamat beberapa baris, ganti Gaya Kop (pilihan radio), pilih logo. | Kotak alamat cukup tinggi (beberapa baris); pilihan Gaya Kop mudah diketuk satu per satu; pratinjau kop muat selebar layar. | | |
| HPV-30f | Masuk sebagai Administrator, buka Keamanan > Pengguna > Data Baru, Grup, dan Daftar Modul. | Form Pengguna dan Grup satu kolom, isian Password ada tombol tampil / sembunyi yang mudah diketuk; Grup berisi satu kartu per modul. Daftar Modul berupa kartu (nama + kode, Menu, Peran Minimal, Grup yang Punya Akses). | | |
| HPV-30g | Di HP (belum masuk) buka Lupa password dari halaman Masuk. | Tampil seperti halaman Masuk HP (satu kolom, tanpa jam dan kutipan): isian dan tombol besar selebar layar, tautan Kembali ke halaman masuk mudah diketuk. Di komputer tampilan lama tidak berubah. | | |

---

## Hal yang sengaja belum ada

Fitur berikut **memang belum dibuat** (mungkin ditambah nanti). Jangan dilaporkan sebagai bug.

- **Transfer antar lokasi/gudang.** Lokasi hanya keterangan rak di barang. Memindahkan stok antar rak belum ada.
- **Retur** (retur ke supplier atau retur dari penerima). Koreksi dilakukan lewat Penyesuaian Stok atau pembatalan dokumen.
- **Nomor lot/batch milik pemasok.** Batch di Stoku hanya menurut tanggal kedaluwarsa (kode `KODEBARANG-YYMMDD`).
- **Ekspor dan impor data** selain yang ada: tombol Export CSV pada grid dan Cetak pada laporan dan dokumen. Belum ada impor barang/transaksi dari Excel atau CSV.
- **Barcode** (mencetak barcode/QR; kamera HP sebagai pemindai). Pemindai Bluetooth/USB yang bertingkah seperti keyboard sudah bisa dipakai di kolom Cari layar hitung HP.
- **Tampilan HP tahap 1 hanya Beranda, Menu, dan Hitung Stok Opname.** Layar lain (master, transaksi, laporan) di HP masih tampilan komputer di dalam kerangka HP; perapiannya menyusul. "Tambah barang" (barang ketemu fisik tapi tak ada di lembar) di layar hitung HP belum ada: pakai Tampilan Desktop.
- **Notifikasi e-mail / WhatsApp untuk jadwal Stok Opname.** Pengingat hanya tampil di Beranda dan lencana menu. Baris selisih besar ditandai kolom Besar dan kotak penyaring, belum diberi warna baris.
- **Mengubah atau menghapus catatan pesanan.** Riwayat Pemesanan hanya membaca dan punya Tutup Pesanan. Salah catat: tutup pesanan, lalu catat yang baru.
- **Logo hitam-putih khusus Hemat Tinta** dan kop kedua untuk dokumen ber-PPN. Satu logo dan satu kop dipakai di semua cetakan.
- **Notifikasi otomatis** lewat e-mail, WhatsApp, atau push. Peringatan hanya tampil di Beranda dan di form. E-mail (mis. lupa password) hanya ditulis ke `storage/logs/laravel.log` di mesin uji.

## Di luar cakupan Stoku

Stoku hanya mencatat **pemasukan dan pengeluaran stok**. Hal berikut bukan bagian Stoku dan tidak perlu dilaporkan:

- Pembelian: tidak ada dokumen pesanan pembelian (PO) dan tidak ada satuan beli. Penerimaan Barang adalah pemasukan stok tanpa dokumen pembelian. "Catat Pemesanan" di Beranda hanya catatan sederhana (supplier, jumlah, tanggal) bahwa barang sudah dipesan di luar Stoku. Ia tidak punya harga, persetujuan, atau cetakan.

## Kondisi yang sudah diketahui

Ini keadaan saat ini, bukan temuan baru:

- Persetujuan di modul stok hanya satu tingkat (Supervisor), dan pembuat Penyesuaian Stok masih boleh menyetujuinya sendiri.
- Daftar wilayah tidak ikut aplikasi dan bisa kosong (lihat README). Wilayah supplier wajib hanya bila daftar wilayah berisi data.
- Password akun contoh sengaja sederhana untuk uji; itu bukan temuan keamanan untuk laporan ini.
