# Stoku

Stoku adalah aplikasi stok barang untuk gudang. Setiap barang punya batch (satu batch = satu tanggal kedaluwarsa). Barang yang paling cepat kedaluwarsa dikeluarkan lebih dulu (FEFO). Aplikasi memberi peringatan saat stok sampai titik pesan ulang dan saat batch hampir kedaluwarsa. Semua perubahan stok dicatat lewat dokumen: Saldo Awal, Penerimaan Barang, Pengeluaran Barang, dan Penyesuaian Stok. Penghitungan fisik rutin dilakukan lewat **Stok Opname** (banyak barang sekaligus; selisihnya menjadi satu Penyesuaian otomatis). Hak akses dibagi tiga peran (Data Entry, Supervisor, Administrator) dan bisa diatur lagi per grup.

Fitur utama:

- Master: Barang (produk dan batch per tanggal kedaluwarsa), Supplier (termasuk Kontan), Satuan dan satuan masuk per barang, Kategori, Lokasi, Departemen, Penerima, dan Perusahaan (nama, alamat, logo untuk kop semua cetakan). Tombol plus untuk menambah master langsung dari dropdown.
- Transaksi: Saldo Awal, Penerimaan Barang, Pengeluaran Barang (FEFO), Penyesuaian Stok (persetujuan Supervisor), pembatalan dengan dokumen pembalik otomatis.
- **Stok Opname**: mulai (snapshot stok per batch, per cakupan Semua / Kategori / Lokasi), cetak Lembar Hitung, input hasil hitung massal dengan simpan otomatis, hitung buta, kunci transaksi selama opname, tinjau selisih (jumlah, persen, nilai, alasan wajib di atas ambang), setujui (satu Penyesuaian otomatis), cetak Berita Acara, serta jadwal opname dengan pengingat di Beranda.
- Pesan ulang: titik pesan ulang per produk, Catat Pemesanan, Riwayat Pemesanan (semua status, penerimaan yang memenuhi pesanan), lead time.
- Laporan: Kartu Stok, Ringkasan Stok, Penerimaan, Pengeluaran, Kedaluwarsa, Opname & Penyesuaian. Harga dan nilai hanya untuk Supervisor.
- Keamanan: peran, grup dengan akses RW / RO per modul, tanpa "ingat saya", keluar otomatis setelah 30 menit tanpa aktivitas (dengan peringatan 2 menit sebelumnya), dan akun nonaktif langsung keluar.
- Bantuan di dalam aplikasi: Panduan Pengguna (Indonesia, mulai dari "Mulai di sini") dan Panduan Teknis (English, Administrator).

Dokumen ini ditulis untuk penguji (QC) pemula. Ikuti langkahnya satu per satu, dari atas ke bawah.

Isi:

1. [Prasyarat](#1-prasyarat)
2. [Memasang dari nol](#2-memasang-dari-nol)
3. [Akun uji](#3-akun-uji)
4. [Wilayah (opsional)](#4-wilayah-opsional)
5. [Mengatur ulang data](#5-mengatur-ulang-data)
6. [Masalah umum](#6-masalah-umum)
7. [Menjalankan tes otomatis](#7-menjalankan-tes-otomatis)
8. [Skenario uji dan bantuan di dalam aplikasi](#8-skenario-uji-dan-bantuan-di-dalam-aplikasi)

## 1. Prasyarat

Pasang semua ini lebih dulu:

| Perangkat | Versi | Keterangan |
|---|---|---|
| PHP | 8.3 atau lebih baru | Syarat dari `composer.json` (`"php": "^8.3"`). Ekstensi `pdo_mysql` harus aktif. Ekstensi `gd` hanya dipakai bila Anda menambah gambar latar login (opsional). |
| Composer | 2.x | Mengunduh paket PHP. |
| MySQL | 8 | Database aplikasi. Aplikasi ini hanya diuji di MySQL. |
| Node.js dan npm | Node 20.19 atau lebih baru, atau 22.12 atau lebih baru | Syarat dari Vite 8 (lihat `package.json`). Wajib, karena tampilan (CSS/JS) harus dibangun dulu. |
| Git | versi terbaru | Mengunduh kode dari GitHub. |
| Herd atau cara lain | - | Untuk menjalankan aplikasi: Laravel Herd (alamat `stoku.test`) atau perintah `php artisan serve`. |

Cek versi di terminal:

```powershell
php -v
```

```powershell
composer -V
```

```powershell
node -v
```

```powershell
git --version
```

## 2. Memasang dari nol

Perintah di bawah memakai PowerShell (Windows). Di macOS atau Linux, perintahnya sama kecuali yang disebut khusus.

### Langkah 1. Ambil kode

Stoku memakai pustaka tampilan bernama DevLib. Pustaka ini ada di repositori terpisah. `composer.json` memanggilnya dari folder `../devlib`. Artinya folder `devlib` dan folder `sstock` harus **berdampingan** (satu folder induk yang sama).

Kedua repositori di GitHub bersifat **privat**. Anda harus punya akun GitHub, dan pemilik (Jo) harus memberi Anda akses ke `275iwan92/stoku` dan `275iwan92/devlib`. Tanpa akses, perintah `git clone` akan gagal.

Pindah ke folder induk yang Anda pilih. Contoh (ganti dengan folder Anda):

```powershell
cd C:\Users\NAMA_ANDA\Herd
```

Unduh DevLib:

```powershell
git clone https://github.com/275iwan92/devlib.git
```

Unduh Stoku:

```powershell
git clone https://github.com/275iwan92/stoku.git
```

Hasilnya dua folder berdampingan: `...\Herd\devlib` dan `...\Herd\stoku`. Jangan ganti nama kedua folder itu.

Masuk ke folder Stoku. Semua langkah berikut dijalankan dari sini:

```powershell
cd stoku
```

### Langkah 2. Pasang paket PHP

```powershell
composer install
```

### Langkah 3. Salin berkas pengaturan

Berkas `.env` berisi pengaturan mesin Anda. Salin dari contoh.

Windows (PowerShell):

```powershell
Copy-Item .env.example .env
```

macOS atau Linux:

```bash
cp .env.example .env
```

### Langkah 4. Isi pengaturan database

Database `sstock` dan user MySQL-nya **tidak perlu dibuat sendiri**. Perintah pemasangan di Langkah 6 yang membuatnya, asal Anda memberi akun admin MySQL.

Buka `.env` dengan editor teks, lalu cek bagian ini:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sstock
DB_USERNAME=sstock_usr
DB_PASSWORD=isi_sandi_mysql_anda
DB_ADMIN_USERNAME=root
DB_ADMIN_PASSWORD=isi_sandi_admin_mysql_anda
```

Artinya:

- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`: database dan user yang dipakai aplikasi. Hanya huruf, angka, dan garis bawah (maksimal 32 karakter). Ganti `isi_sandi_mysql_anda` dengan sandi pilihan Anda. Pemasang membuat user itu dengan sandi tersebut.
- `DB_ADMIN_USERNAME` dan `DB_ADMIN_PASSWORD`: akun admin MySQL, hanya dipakai pemasang untuk membuat database dan user. Aplikasi tidak memakainya. Bila `root` di mesin Anda tanpa sandi, kosongkan `DB_ADMIN_PASSWORD`. Bila dikosongkan, pemasang akan menanyakan sandinya.
- Nilai `DB_PASSWORD` di `.env.example` hanya contoh untuk mesin lokal. Jangan dipakai di server sungguhan.
- Jangan menyimpan atau mengirim `.env` Anda ke siapa pun. Berkas ini tidak ikut Git.

Bawaan `.env.example` aman untuk server sungguhan (`APP_ENV=production`, `APP_DEBUG=false`, tombol Segarkan data contoh mati). Untuk mesin uji Anda, ubah tiga baris ini di `.env`:

```dotenv
APP_ENV=local
APP_DEBUG=true
SSTOCK_SAMPLE_REFRESH=true
```

`APP_DEBUG=true` membuat halaman galat menampilkan penjelasan teknis (berguna untuk laporan). `SSTOCK_SAMPLE_REFRESH=true` menyalakan layar Data Contoh ([bagian 5](#5-mengatur-ulang-data)). Jangan memakai ketiganya di server sungguhan.

Bila Anda memakai `php artisan serve` (bukan Herd), ubah juga `APP_URL`:

```dotenv
APP_URL=http://127.0.0.1:8000
```

### Langkah 5. Buat kunci aplikasi

```powershell
php artisan key:generate
```

### Langkah 6. Pasang database

```powershell
php artisan sstock:install
```

Perintah ini aman dijalankan ulang. Ia tidak menimpa data yang sudah ada. Urutan kerjanya:

1. Membuat database `sstock` dan user MySQL (bila belum ada). Bila sandi admin tidak ada di `.env`, ia menanyakannya (tekan Enter bila `root` tanpa sandi). Di langkah 3 Anda mungkin ditanya sekali lagi.
2. Menjalankan migrasi (membuat semua tabel).
3. Mencoba menyalin data wilayah dari database proyek lain. **Di mesin penguji ini hampir pasti gagal, dan itu tidak apa-apa.** Anda akan melihat peringatan "Data wilayah belum disalin". Lihat [bagian 4](#4-wilayah-opsional).
4. Mengisi data contoh, tetapi hanya bila belum ada satu pun pengguna. Yang dibuat: 3 akun uji ([bagian 3](#3-akun-uji)), 3 kategori (ATK, SPR, TLS), 3 supplier contoh (ditambah supplier sistem **Kontan** untuk pembelian tunai, yang selalu ada dari migrasi dan tidak bisa dinonaktifkan, diganti nama, atau dihapus), puluhan barang beserta lokasi rak, 5 departemen, 6 penerima, dan riwayat transaksi contoh (saldo awal, penerimaan, pengeluaran, penyesuaian). Sebagian barang punya batch kedaluwarsa.

Pesan terakhir yang benar: `Instalasi selesai.`

### Langkah 7. Bangun tampilan

Folder `public/build` tidak ikut Git, jadi harus dibangun di mesin Anda.

```powershell
npm install --ignore-scripts
```

```powershell
npm run build
```

### Langkah 8. Jalankan aplikasi

**Dengan Herd:** daftarkan folder induk (tempat `sstock` berada) di Herd sebagai folder terdaftar (parked). Lalu buka:

```
http://stoku.test
```

**Tanpa Herd:** jalankan server bawaan PHP, dan biarkan terminal ini terbuka:

```powershell
php artisan serve
```

Lalu buka:

```
http://127.0.0.1:8000
```

Anda akan melihat halaman masuk. Masuk dengan salah satu akun di bagian berikut.

### Jalan pintas

Ada satu perintah yang menjalankan langkah 2, 3 (hanya bila `.env` belum ada), 5, 6, dan 7 sekaligus:

```powershell
composer setup
```

Perintah ini tidak bertanya apa pun. Ia memakai `DB_ADMIN_USERNAME` dan `DB_ADMIN_PASSWORD` dari `.env`. Jadi ia cocok hanya bila `root` MySQL Anda tanpa sandi, atau bila Anda sudah mengisi `.env` sebelum menjalankannya. Untuk pemula, ikuti langkah satu per satu di atas agar pesan galat mudah dibaca. Setelah `composer setup`, tetap ubah `.env` seperti di Langkah 4 (tiga baris `APP_ENV`, `APP_DEBUG`, `SSTOCK_SAMPLE_REFRESH`).

## 3. Akun uji

Seeder (`database/seeders/UserSeeder.php`) membuat tiga akun ini. Masuk dengan username (atau e-mail) dan password:

| Peran | Username | E-mail | Password |
|---|---|---|---|
| Administrator | `admin` | `admin@sstock.test` | `123456788` |
| Supervisor | `spv` | `spv@sstock.test` | `12345678` |
| Admin Data | `adm` | `adm@sstock.test` | `12345677` |

Yang boleh dilakukan tiap peran (**Admin Data**: membuka semua layar seperti Supervisor tetapi tidak menyetujui apa pun dan tidak mengelola pengguna):

- **Data Entry**: Penerimaan dan Pengeluaran Barang, mengisi hasil hitung Stok Opname, menambah barang baru dan Satuan masuk-nya (kemasan saat diterima, mis. 1 dus = 12 pcs; memperbaiki faktor yang belum pernah dipakai di dokumen), mengelola Supplier, Departemen, Penerima, dan melihat laporan (tanpa harga/nilai).
- **Supervisor**: semua milik Data Entry, ditambah mengubah Barang, Satuan (termasuk faktor Satuan masuk yang sudah dipakai di dokumen), Kategori, Lokasi, data Perusahaan (kop cetak), Saldo Awal, Penyesuaian Stok, memulai, meninjau, dan menyetujui Stok Opname, menyetujui atau menolak, dan melihat harga serta nilai. Pengeluaran dan Penyesuaian selalu dalam satuan stok barang; hanya Penerimaan yang memakai satuan masuk.
- **Administrator**: semua milik Supervisor (termasuk menu Perusahaan), ditambah menu Keamanan (Pengguna, Grup, Data Contoh, Daftar Modul).

> **PERINGATAN.** Ketiga akun ini hanya untuk pengembangan dan pengujian di mesin Anda sendiri. Passwordnya lemah dan tertulis di kode. **Wajib diganti atau dihapus (dinonaktifkan) sebelum Stoku dipakai di server sungguhan.** Jangan pernah membuka Stoku ke internet dengan akun ini.

## 4. Wilayah (opsional)

Supplier punya isian Wilayah (kecamatan, kota, provinsi, negara, kode pos). Daftar wilayah ada di tabel `geo_country`, `geo_region`, `geo_city`, `geo_district`, `geo_village`. Isinya tidak ikut kode. Isinya disalin dari database proyek lain (`tm2mst`) milik Jo.

Aturannya:

- **Tabel wilayah kosong** (keadaan umum di mesin penguji): isian Wilayah di form Supplier tidak wajib. Supplier boleh disimpan tanpa wilayah. Kotak cari wilayah tidak menemukan apa pun, dan itu normal. Supplier contoh dibuat tanpa wilayah.
- **Tabel wilayah berisi data**: Wilayah **wajib** dipilih (minimal sampai kota).

Bila Anda punya akses ke database `tm2mst` di server MySQL yang sama, impor dengan:

```powershell
php artisan sstock:import-geo
```

Perintah ini membutuhkan akun admin MySQL dan database sumber bernama `tm2mst`. Nama sumber lain bisa diberikan lewat opsi `--source=nama_database` atau variabel `GEO_SOURCE_DB` di `.env`. Bila tabel sudah terisi, perintah dilewati. Untuk mengisi ulang dari awal, tambahkan `--fresh`. Penguji biasa **tidak perlu** melakukan ini.

## 5. Mengatur ulang data

> **PERINGATAN.** Semua cara di bawah **MENGHAPUS data** yang ada (barang, transaksi, supplier, akun). Datanya tidak bisa dikembalikan. Jalankan hanya di mesin uji Anda.

**Cara 1 (disarankan): lewat layar Data Contoh.**

1. Masuk sebagai `admin`.
2. Buka menu Keamanan, lalu Data Contoh.
3. Baca daftar isi database yang akan dihapus.
4. Ketik `SEGARKAN` (huruf besar) di kotak konfirmasi, lalu tekan tombol Segarkan Data Contoh.
5. Anda otomatis keluar. Masuk lagi dengan akun di bagian 3.

Layar ini mengganti semua data dengan data contoh yang segar (tanggal kedaluwarsa dihitung ulang dari hari ini). Data wilayah dipertahankan.

Layar ini hanya bisa dipakai bila `.env` Anda berisi baris ini (lihat Langkah 4):

```dotenv
SSTOCK_SAMPLE_REFRESH=true
```

Bila baris itu `false` (bawaan `.env.example`), tombolnya ditolak. Setelah mengubah `.env`, jalankan `php artisan config:clear` bila perubahan tidak terasa.

**Cara 2: lewat terminal.**

```powershell
composer sample
```

Ini sama dengan `php artisan migrate:fresh --seed --force`. Ia menghapus **seluruh isi database `sstock`, termasuk tabel wilayah**, lalu mengisi ulang data contoh.

## 6. Masalah umum

| Yang terjadi | Penyebab | Solusi |
|---|---|---|
| `composer install` gagal karena paket `jo/devlib` atau folder `../devlib` tidak ditemukan | Folder `devlib` tidak berdampingan dengan `sstock`, atau belum di-clone | Pastikan ada `...\devlib` di sebelah `...\sstock`. Ulangi Langkah 1, lalu `composer install` lagi. |
| `git clone` gagal atau meminta login, atau menolak akses | Repositori privat | Masuk dengan akun GitHub Anda. Minta Jo memberi akses ke kedua repositori. |
| Halaman error "Vite manifest not found" atau tampilan polos tanpa gaya | Tampilan belum dibangun | Jalankan `npm install --ignore-scripts`, lalu `npm run build` di folder `sstock`. |
| Error "No application encryption key has been generated" | Kunci belum dibuat | Jalankan `php artisan key:generate`. |
| Pemasang menulis "Tidak bisa masuk ke MySQL sebagai 'root'" | MySQL belum jalan, atau akun/sandi admin salah | Pastikan layanan MySQL berjalan. Periksa `DB_HOST`, `DB_PORT`, `DB_ADMIN_USERNAME`, `DB_ADMIN_PASSWORD` di `.env`. Atau beri lewat opsi: `php artisan sstock:install --admin-user=root --admin-password=SANDI_ANDA`. |
| Pemasang menulis "Set DB_CONNECTION=mysql di .env" atau "hanya boleh huruf, angka, dan garis bawah" | `.env` salah | Pastikan `DB_CONNECTION=mysql`. Nama database dan user hanya berisi huruf, angka, garis bawah. |
| Pemasang menulis "Data wilayah belum disalin" | Database `tm2mst` tidak ada di mesin Anda | Normal. Wilayah opsional. Lihat [bagian 4](#4-wilayah-opsional). |
| Setelah mengubah `.env`, perubahan tidak terasa | Pengaturan tersimpan di cache | Jalankan `php artisan config:clear`. |
| Masuk gagal dengan pesan "Terlalu banyak percobaan. Coba lagi dalam ... detik." | Lebih dari 5 kali salah password | Tunggu sejumlah detik yang tertulis, lalu coba lagi dengan password yang benar. |
| E-mail "lupa password" tidak sampai | `MAIL_MAILER=log`: e-mail tidak dikirim, hanya ditulis ke berkas log | Buka `storage/logs/laravel.log` dan cari tautan reset di sana. Untuk pengiriman sungguhan, atur SMTP: lihat [docs/SMTP.md](docs/SMTP.md). |
| Layar Data Contoh menolak dengan pesan "dimatikan di lingkungan ini" (403) | `SSTOCK_SAMPLE_REFRESH` bukan `true` | Isi `SSTOCK_SAMPLE_REFRESH=true` di `.env`, lalu `php artisan config:clear`. |
| Tiba-tiba keluar sendiri ke halaman masuk | Keluar otomatis setelah 30 menit tanpa aktivitas, dengan peringatan 2 menit sebelumnya (`SESSION_LIFETIME=30` di `.env.example`) | Masuk lagi. Untuk uji panjang, naikkan `SESSION_LIFETIME` di `.env`. |
| Port 8000 dipakai program lain saat `php artisan serve` | Bentrok port | Pakai port lain: `php artisan serve --port=8001`, lalu sesuaikan `APP_URL`. |

## 7. Menjalankan tes otomatis

Tes otomatis untuk pengembang, bukan untuk penguji. Tes memakai SQLite di memori, jadi tidak menyentuh database MySQL Anda.

```powershell
php artisan test
```

## 8. Skenario uji dan bantuan di dalam aplikasi

- **Skenario uji QC** (tabel kasus uji, cara melapor temuan, dan hal yang sengaja belum ada): [docs/QC-SKENARIO.md](docs/QC-SKENARIO.md).
- **Bantuan di dalam aplikasi:** klik nama Anda di pojok layar untuk membuka menu profil, lalu pilih Bantuan & dukungan. Atau tekan F1 di layar mana pun. Ini membuka Panduan Pengguna (User's Guide, Bahasa Indonesia, untuk semua peran). Pemula: mulai dari bagian **Mulai di sini** (peta menu dan urutan hari pertama). Setiap modul punya bagian dengan pola tetap: Untuk apa, Siapa yang bisa, Langkah, Yang perlu diperhatikan, Masalah umum.
- **Panduan Teknis** (Technical Guide, Bahasa Inggris): hanya untuk Administrator, ada sebagai tab di halaman Bantuan yang sama.
