# Pemisahan AdminKU (produk demo) dan Jays (aplikasi nyata)

## Peran
| | **adminku** | **jays** |
|---|---|---|
| Apa | Produk AdminKU: sumber kode, data dummy, dijual ke perusahaan lain | Aplikasi nyata perusahaan Jays, pemakai pertama |
| Laptop | `C:\Users\iwanp\Herd\adminku`, `http://adminku.test`, DB `adminku` (SQLite: `database/adminku.sqlite`), git sendiri | `C:\Users\iwanp\Herd\jays`, `http://jays.test`, DB `jays`, git sendiri |
| Biznet | `/var/www/adminku`, `http://103.127.135.74/adminku/`, DB `adminku` | `/var/www/jays`, `/jays/`, DB `jays` |
| Data | Demo, bisa direset kapan saja (`SSTOCK_DEMO=true`) | Nyata; tidak pernah di-reset otomatis (`SSTOCK_DEMO` tidak ada) |
| Kode | **Sumber kebenaran**: modul baru dikerjakan di sini | Menarik rilis `adminku` saat Iwan memutuskan |

Tidak ada yang dipakai bersama: database, `.env`, kunci aplikasi, cookie sesi (`adminku_session` / `jays_session`), berkas `storage`, DB/prefix Redis di laptop (adminku: DB 5 dan 6, awalan `adminku-`; jays: DB 3 dan 4, awalan `jays-`), pengguna MySQL, dan folder Nginx. Data wilayah `geo_*` disalin sekali ke tiap database (tidak dibagi). Satu-satunya yang dipakai bersama adalah pustaka DevLib (lihat di bawah).

## Database laptop: SQLite, bisa pindah ke MySQL
Di laptop adminku memakai berkas SQLite sendiri, tanpa akun admin MySQL (`.env`: `DB_CONNECTION=sqlite`, `DB_DATABASE=C:\Users\iwanp\Herd\adminku\database\adminku.sqlite`; berkas ini tidak masuk git). Semua migrasi dan tes teruji di SQLite. Data wilayah `geo_*` disalin sekali dari DB `jays` laptop (hanya dibaca). Biznet memakai MySQL.

Pindah ke MySQL kapan saja, hanya dengan mengubah `.env` laptop:
1. Ganti `DB_CONNECTION=mysql` dan isi `DB_HOST`, `DB_PORT`, `DB_DATABASE=adminku`, `DB_USERNAME=adminku_usr`, `DB_PASSWORD`, `DB_ADMIN_USERNAME`, `DB_ADMIN_PASSWORD` (contohnya sudah ada, dikomentari, di `.env`).
2. `php artisan sstock:install --no-seed` (membuat DB + user MySQL dengan akun admin, migrate, menyalin `geo_*` dari sumber), lalu `php artisan adminku:reset-demo --force`.
3. `php artisan optimize:clear`. Berkas SQLite boleh dihapus.

## Akun demo (hanya di adminku; bukan akun nyata)
Tiga akun yang SAMA di AdminKU, Jays, dan Stoku (perintah pemilik 2026-11-03; sandi sengaja sederhana). Super admin Jays (`johiwan`) tidak ada di adminku.

| Username | Peran | Hak |
|---|---|---|
| `admin` / `123456788` | Administrator | Semua modul (Stok, GA, HR, Purchase, Keamanan, Data Contoh), menyetujui, kelola pengguna dan Grup |
| `spv` / `12345678` | Supervisor | Membuka semua modul selain Keamanan, menyetujui (PP, PO, Penyesuaian, Opname, pembatalan); tidak kelola pengguna |
| `adm` / `12345677` | Admin Data | Membuka semua modul selain Keamanan; TIDAK menyetujui (tombol Setujui/Tolak tidak ada, server 403) dan tidak kelola pengguna |

Peran `Admin Data` (`users.role = admin_data`, Grup `ADMINDATA`) setara Supervisor dalam membuka layar (`isSupervisor()`), tetapi `User::canApprove()` false; aksi setujui/tolak/kembalikan dijaga `canApprove()` dan middleware `approver`. Admin Data yang membatalkan dokumen terproses hanya mengajukan pembatalan; Supervisor yang menyetujui.

Perusahaan demo: **PT Contoh Sejahtera** (diubah lewat menu Data Master > Perusahaan, disimpan di data, bukan di kode). Merek AdminKU tetap.
Penanda demo: judul tab berakhiran `(DEMO)`, lencana `DEMO` kecil di pojok kanan bawah, dan teks "Data demo, bisa direset" di layar masuk. Semuanya muncul hanya bila `SSTOCK_DEMO=true`.

Isi data demo: master Stok + barang/batch contoh dan riwayat transaksi; GA (4 kendaraan, 9 AC, vendor, ruang); HR (David, Detta, kandidat Maya Denada, Dedi Sandiaga, Lucy Sempi, foto dan KTP tiruan dari `database/sample/hr`); Purchase (3 pemasok fiktif dengan riwayat beli 6 bulan, satu rantai PP, Keputusan Pesanan, PO, PV yang menambah stok, dan dua PP menunggu persetujuan).

> Foto di `database/sample/hr` adalah foto asli (kata sesi HR). Ganti dengan foto bebas hak sebelum adminku diperlihatkan atau dijual ke pihak lain: timpa berkas JPG di folder itu dengan nama sama lalu `php artisan adminku:reset-demo`.

## Reset demo
```
php artisan adminku:reset-demo --force
```
Membuang semua tabel (kecuali `geo_*`), migrasi ulang, mengisi seeder demo, menghapus berkas unggahan (foto, KTP, lampiran, logo, log aktivitas). Idempoten. Menolak berjalan bila `SSTOCK_DEMO` bukan `true` (jadi aman di Jays). Tombol setaranya: menu Keamanan > Data Contoh > Segarkan (ketik SEGARKAN; butuh `SSTOCK_SAMPLE_REFRESH=true` di lingkungan produksi). "Reset data contoh HR" di layar yang sama hanya mengembalikan karyawan dan kandidat contoh.

## Jays menarik rilis adminku
Riwayat git kedua repo sama sampai commit `ef186fb` (adminku dikloning dari jays), jadi penggabungan memakai git biasa. Tidak ada remote tersimpan (supaya tidak saling dorong); tarik dari folder:
```
cd C:\Users\iwanp\Herd\jays
git status                          # harus bersih
git fetch ..\adminku tag v1.0.0     # atau: git fetch ..\adminku main
git merge --no-ff v1.0.0            # selesaikan konflik bila Jays sempat mengubah kode
composer install ; npm install ; npm run build
php artisan migrate                 # pada Jays: SELALU cadangkan DB jays dulu
php artisan test                    # harus hijau sebelum dipakai
```
Syarat: sejak pemisahan, **fitur baru hanya dikerjakan di adminku**; di Jays hanya `.env`, data, dan perbaikan darurat yang segera dibawa balik ke adminku.

Yang **tidak boleh** ikut ke Jays (dijaga oleh kode, tetap periksa):
- `SSTOCK_DEMO` jangan pernah diisi di `.env` Jays. Berkas `DemoSeeder`, `DemoPurchaseSeeder`, `adminku:reset-demo` ikut ke kode tetapi mati dan menolak berjalan tanpa flag itu.
- Jangan menjalankan `php artisan db:seed` penuh di Jays (UserSeeder menimpa akun). Seeder yang aman: `VehicleSeeder`, `AcSeeder`, dan seeder master yang dipilih sengaja dengan `--class`.
- Jangan menyalin `.env`, `storage/`, atau `public/build` dari adminku ke Jays; `public/build` dibuild ulang dari kode Jays.
- Nama perusahaan, logo kop, dan hak akses Grup Jays ada di datanya sendiri; rilis tidak menimpanya.

Nomor versi: tag git `vMAJOR.MINOR.PATCH` di adminku (awal: `v1.0.0`). Buat rilis dengan `git tag -a v1.1.0 -m "..."` setelah `php artisan test` hijau.

## Deploy adminku ke Biznet
Cadangkan DB Biznet ke laptop dulu (prosedur `mysqldump` ke `C:\Users\iwanp\Backups\stoku-biznet`), lalu:
1. Laptop: `npm run build`; `git bundle create adminku.bundle main`; `pscp` bundle ke `/tmp` dan `public/build/*` ke `/tmp/adminku-build`.
2. Server: `cd /var/www/adminku && git fetch /tmp/adminku.bundle main && git reset --hard FETCH_HEAD`; ganti `public/build`; `sudo chown -R www-data:www-data storage bootstrap/cache`.
3. `sudo -u www-data php artisan migrate --force` (data demo tidak berubah) atau `... adminku:reset-demo --force` (kembali ke awal), lalu `optimize:clear && optimize`, `sudo systemctl reload php8.5-fpm`.
   Jangan memasang `php artisan down` selama `adminku:reset-demo`: seeder Purchase memakai rute aplikasi sendiri dan akan mendapat 503 ("No query results for model PurchaseRequest"). Bila sudah terlanjur: `php artisan up`, lalu ulangi reset.
4. Uji: masuk sebagai `admin`, buka beberapa layar; `/jays/`, `/stoku/`, `/devlib/` harus tetap 200/302.

`.env` Biznet adminku: `APP_ENV=production`, `APP_URL=http://103.127.135.74/adminku`, `SESSION_DRIVER=database`, `SESSION_COOKIE=adminku_session`, `SESSION_PATH=/adminku`, `CACHE_STORE=file`, `SSTOCK_DEMO=true`, `SSTOCK_SAMPLE_REFRESH=true`, `SSTOCK_IP_LOOKUP=false`, `MAIL_MAILER=log`. Nginx: blok `/adminku/` di `/etc/nginx/sites-enabled/demo` (cadangan: `/etc/nginx/demo.bak-adminku-*`), ditambah `location = /media/brand/adminku.svg` agar logo merek ada (sebelumnya 404 di Biznet).

## DevLib di hosting terpisah
Di laptop DevLib dipasang lewat tautan folder (`composer.json`: repositori `path` `../devlib`, bergantung `@dev`). Hosting terpisah tidak punya `../devlib`, jadi tersedia jalur paket versi tetap:
- `composer.hosting.json` + `composer.hosting.lock`: DevLib diambil dari repo git `https://github.com/275iwan92/devlib.git`, dikunci ke commit `51a74b5` (versi saat v1.0.0). Repo privat butuh akses: token GitHub (`composer config --global github-oauth.github.com <token>`) atau kunci SSH, lalu ganti URL repositori ke `git@github.com:275iwan92/devlib.git`.
- Pasang: `COMPOSER=composer.hosting.json composer install --no-dev --optimize-autoloader` (Composer memakai `composer.hosting.lock` dan menulis `vendor/jo/devlib` sebagai salinan, bukan tautan).
- Naikkan versi DevLib: di laptop `COMPOSER=composer.hosting.json composer update jo/devlib` (atau ubah `dev-main#<commit>` / buat tag `vX.Y.Z` di repo DevLib dan minta `^X.Y`), lalu commit kedua berkas.
- Bila DevLib berubah, aplikasi harus memakai versi yang diuji bersama rilisnya: catat commit DevLib di catatan rilis.
Jalur ini sudah diuji memasang paket dari GitHub dari laptop; belum diuji di mesin hosting terpisah.

## Pemasangan baru di mesin lain
```
git clone <repo adminku> adminku && cd adminku
cp .env.example .env     # isi APP_URL, DB_*, SSTOCK_DEMO=true bila demo; kosongkan MAIL_*, SSTOCK_SUPER_ADMIN
COMPOSER=composer.hosting.json composer install --no-dev --optimize-autoloader
php artisan key:generate
npm install && npm run build
php artisan sstock:install --no-seed      # buat DB + user (butuh akun admin MySQL), migrate, salin geo dari sumber
php artisan adminku:reset-demo --force    # hanya demo
```
Untuk aplikasi nyata (bukan demo): jalankan `php artisan sstock:install` (tanpa `--no-seed`) hanya pada DB kosong; jangan set `SSTOCK_DEMO`.
