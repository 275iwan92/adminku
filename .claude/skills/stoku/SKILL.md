---
name: stoku
description: Aturan kerja proyek Stoku (sstock, Laravel + DevLib). Muat sebelum mengubah apa pun di repo ini: layar/form, dokumen stok, terjemahan, DevLib, commit.
---

# Stoku (sstock) — aturan kerja

Bahasa kerja dengan pemilik: Indonesia, ringkas. Daftar fitur, akun uji, dan hal yang belum dikerjakan ada di `HANDOVER.md`.

## Wajib
1. **Selalu commit dan push** setiap selesai perubahan, walau belum "ok" atau tes gagal (backup pemilik). Pesan commit bahasa Indonesia + baris atribusi dari sistem. Jangan force-push, jangan commit `.env`. Repo `../devlib` tidak punya remote: cukup commit. Push gagal "Connection reset": ulangi.
2. **Semua tampilan Bahasa Indonesia.** Label layar boleh English (kebiasaan DevLib), lalu tambahkan ke kamus `App\Support\Indonesian` dan cek di layar. Teks yang dirangkai di PHP ditulis langsung Indonesia. Pesan validasi: `lang/id/validation.php` (termasuk nama atribut). Mata uang tetap `IDR`.
3. **DevLib** (`C:\Users\iwanp\Herd\devlib`, `telasih/devlib`, `x-dl::*`): layar = deklarasi `x-dl::*`, tanpa HTML mentah atau warna literal; nilai data dibungkus `raw()`. Perubahan DevLib harus OPT-IN (tmerp memakai DevLib dengan perilaku lama), commit di repo devlib. Perbaiki akar masalah di DevLib, bukan akal-akalan di aplikasi.
4. **Gaya grid:** lihat bagian "Gaya grid" di bawah.
5. **Form dokumen:** wajib Confirm, Delete (selama draf), dan Cancel; aturan lengkap di bagian "Siklus dokumen". Form tidak boleh terkirim dua kali (penjaga di `layout.blade.php`).
6. **Stok** hanya diubah lewat `App\Services\StockLedger` (`post`, `reverse`, `assertReversible`); siklus dokumen di `App\Services\StockDocuments`. Pembalikan = baris baru `reversal_of`, baris asal tidak diubah.
7. **Verifikasi** tampilan di http://stoku.test. Setelah mengubah `resources/css/app.css` jalankan `npm run build` dan muat ulang keras. Kembalikan viewport emulasi ke `desktop`. Jangan tinggalkan data uji di database lokal.
8. Tanya pemilik hanya bila benar-benar buntu; selebihnya putuskan sendiri dan sebutkan asumsinya.
9. **Selalu MANDIRI tanpa prompting (perintah tegas pemilik, 2026-10-20):** pemilik sering tidak di depan laptop. Kerjakan semua sampai tuntas (kode, tes, HELP UG+TG, commit + push) tanpa menunggu jawaban dan tanpa menutup dengan pertanyaan "mau lanjut / mulai dari mana?"; daftar "belum dikerjakan" yang Anda sebut sendiri juga langsung dikerjakan. **Pengecualian satu-satunya: keputusan tampilan LAYAR** (tata letak, bentuk, apa yang terlihat pengguna) adalah hak pemilik: usulkan dan tanyakan dulu, jangan putuskan sendiri. Keputusan lain (logika, data, tes) putuskan sendiri dan sebut asumsinya di ringkasan akhir.

## Aturan data transaksional vs data master (dari pemilik, berlaku untuk SEMUA proyek Iwan)
**Data transaksional** (master-detail maupun single):
1. **DRAFT** = mode awal. Record yang baru dibuat SELALU `DRAFT`. Tombol wajib: **SAVE, DELETE, NEW, PRINT**. Semua isian diberi **nilai DEFAULT**, tidak dibiarkan kosong, kecuali field tertentu yang memang harus dipilih pengguna.
2. **CONFIRMED**: dicapai lewat tombol **CONFIRM** (atau **SUBMIT**). Setelah CONFIRMED record menjadi **read-only**.
3. **CANCELLED**: dari record read-only harus ada tombol **CANCEL**; setelah ditekan mode menjadi `CANCELLED`. Bila data tidak cocok (mis. stok sudah berubah), record **ADJUSTMENT pembalik dibuat OTOMATIS**. Record CANCELLED tetap tersimpan di tabel. Beda dengan **DELETE** (hanya draf): record benar-benar hilang dari sistem.
4. **PRINT** mencetak tulisan **MODE** dokumen di hasil cetak: `DRAFT` / `CONFIRMED` / `CANCELLED`.
5. **Authority**: permohonan persetujuan ke atasan, boleh **bertingkat** (lebih dari satu level).

**Data master**: TIDAK ada mode DRAFT/CONFIRM. Hanya **ACTIVATED / DEACTIVATED** (nama lain **ENABLE / DISABLE**); master tidak dihapus, hanya dinonaktifkan.

**Kondisi kode terhadap aturan di atas (2026-10-20):**
- DRAFT > CONFIRM > CANCEL (+ pengajuan batal), DELETE hanya draf, read-only setelah Confirm, pembalikan stok otomatis (baris `reversal_of`), semua master Activate/Deactivate (termasuk Grup).
- **PRINT** ada di semua mode form dokumen stok (`docs.print`, kelas `App\Support\StockDocumentPrint` di atas `x-dl::print-doc`); cetakan memuat MODE (stempel + tanda air). Dokumen jenis baru wajib memakai pola ini.
- **Authority bertingkat TIDAK dipakai di stok** (keputusan pemilik 2026-10-20: bertingkat hanya di pembelian dan penjualan; stok untuk sementara satu tingkat, Supervisor). Jangan menambahkannya lagi tanpa diminta.
- **Default data**: dokumen baru berisi baris pertama dengan jumlah 1 dan alasan penyesuaian bawaan; supplier / penerima sengaja dibiarkan dipilih pengguna.
- **Pembalik otomatis**: Cancel dokumen yang sudah diproses membuat dokumen ADJUSTMENT (approved, `reversal_of_document_id`) di `StockDocuments::recordReversal`; baris buku besar tetap `reversal_of` di dokumen asal.
- Keputusan: Pemesanan Ulang (`reorder_orders`) adalah catatan satu klik, bukan dokumen transaksi, jadi tidak memakai siklus DRAFT/CONFIRMED; ditutup otomatis oleh Penerimaan yang dikonfirmasi.

## Siklus dokumen: Confirm, Delete, Cancel
Berlaku untuk SEMUA form dokumen stok (Saldo Awal, Penerimaan, Pengeluaran, Penyesuaian) dan setiap jenis dokumen baru. Logika hanya di `App\Services\StockDocuments`; controller `StockDocumentController@update` membaca `_action` (save, confirm, delete, cancel, approve, reject). Form = `x-dl::form kind="document" :pipeline="true"` ([docs/form.blade.php](resources/views/docs/form.blade.php)); tombol Confirm/Delete/Cancel/Approve/Reject berasal dari status DevLib, jangan dibuat manual.

**Status:** `draft` > `confirmed` > `cancel_pending` (pengajuan batal) > `cancelled`. Penyesuaian: `draft` > `approval` (setelah Confirm) > `approved` | `rejected`, lalu bisa `cancel_pending` > `cancelled`.

**Draf**
- Dokumen baru selalu `draft`: stok TIDAK berubah, nomor dokumen otomatis (PREFIX-YYMM-NNNN).
- Hanya draf yang boleh diubah (Simpan) dan di-Delete (hapus permanen; draf dihapus, bukan dibatalkan).
- Tombol **Confirm** wajib ada. Syarat: minimal satu baris (jika kosong ditolak dengan pesan).

**Confirm** (Saldo Awal, Penerimaan, Pengeluaran)
- Diproses ke stok lewat `StockLedger::post` dalam satu transaksi; status `confirmed`, `confirmed_by/at` terisi.
- Tanggal/jam dokumen = saat Confirm (Saldo Awal: tanggal yang dipilih pengguna).
- Penerimaan Confirm menutup pemesanan ulang (`reorder_orders`) yang terbuka untuk item di dokumen itu.
- Dokumen yang sudah Confirm terkunci (tidak bisa diedit); koreksi lewat Stock Adjustment.

**Confirm Penyesuaian** = diajukan (`approval`), stok belum berubah. **Approve** (hanya Supervisor) baru memposting ke stok, dan ditolak bila stok item berubah sejak dihitung (suruh Reject lalu hitung ulang). **Reject** (hanya Supervisor) menutup dokumen tanpa stok berubah.

**Cancel dokumen yang sudah Confirm/Approve**
- Alasan WAJIB (minimal 3 huruf, maks 255).
- Pengguna biasa MENGAJUKAN: status `cancel_pending`, stok belum berubah, form menampilkan peringatan + tahap persetujuan DevLib. Supervisor/Administrator menekan Cancel = langsung dibatalkan, tanpa persetujuan lain.
- Supervisor menyetujui (`approveCancel`: stok dibalik, diperiksa ulang) atau menolak (`rejectCancel`: kembali ke `confirmed`/`approved`, alasan dikosongkan).
- Pengaju (atau Supervisor) boleh menarik pengajuan yang masih menunggu (`withdrawn`); orang lain tidak (403).
- Sebelum menerima pengajuan, `StockLedger::assertReversible` MENGHITUNG apakah stok cukup untuk menarik dokumen itu kembali. Tidak cukup: DITOLAK, pengguna membuat Stock Adjustment. Hasil hitungan (`cancelProblem`) tampil sebagai peringatan di form sebelum tombol ditekan dan dihitung ulang saat persetujuan.
- Pembalikan stok = baris baru di `stock_movements` dengan `reversal_of`; baris asal tidak diubah. Penerimaan yang dibatalkan membuka lagi pemesanan ulang yang tadinya ditutupnya.
- Penyesuaian yang baru diajukan (`approval`, belum disetujui) hanya ditarik tanpa pembalikan stok.

**Jejak:** setiap tahap (Dibuat, Dikonfirmasi, Mengajukan pembatalan, Disetujui, Dibatalkan + alasan) masuk `log` form.

**Hak:** Approve/Reject Supervisor saja (403 untuk lainnya). Belum ada pemisahan tugas: pembuat penyesuaian masih boleh menyetujui sendiri.

**Tes wajib** di `tests/Feature/DocumentLifecycleTest.php` (dan `StockFlowTest.php`) untuk setiap jenis dokumen (Confirm tidak mengubah stok sebelum waktunya, Cancel ditolak saat stok kurang, pengajuan lalu persetujuan).

## Selisih dengan aturan transaksional pemilik
Aturan umum mode DRAFT/CONFIRMED/CANCELLED, tombol, default data, print MODE, authority bertingkat, dan state baris detail ada di skill `devlib` (bagian "Data transaksional: mode dan tombol"). Di sstock tampilannya Indonesia (Draf, Terkonfirmasi, Dibatalkan). Status per 2026-10-20 (sudah dicek dari kode dan tes):
- **Sesuai:** Draf hanya untuk transaksi, master hanya Aktif/Nonaktif (termasuk Grup), record batal tetap tersimpan, Confirm membuat readonly, PRINT ada di semua mode dengan tulisan MODE (`docs.print`, `StockDocumentPrint`), pembalik ADJUSTMENT otomatis saat Cancel (`StockDocuments::recordReversal`), nilai bawaan di dokumen baru, state baris detail (`:row-state="true"` di form dokumen dan konversi Satuan).
- **Authority bertingkat TIDAK dipakai di stok** (keputusan pemilik: bertingkat hanya di pembelian dan penjualan; stok satu tingkat, Supervisor, `approval-levels` 1). Jangan menambahkannya tanpa diminta.
- **Pemesanan Ulang** (`reorder_orders`) adalah catatan satu klik, bukan dokumen transaksi: tanpa siklus DRAFT/CONFIRMED.
- Form dokumen yang belum pernah disimpan hanya punya Undo + Save (bawaan DevLib); Delete dan Print muncul setelah draf pertama tersimpan.

## Gaya grid
- **Grid transaksi** (dokumen stok, dan grid transaksi baru lainnya): POLOS. Kolom terdepan = nomor dokumen, hanya tebal, TANPA kotak/avatar warna (`'type' => 'identity', 'avatar' => false`, tanpa `box`/`hue`). WAJIB ada kolom Status bertipe `badge` dengan `tones` (hijau Terkonfirmasi/Disetujui, abu Draf, kuning menunggu, merah Dibatalkan/Ditolak); itu satu-satunya warna di baris. Contoh: `resources/views/docs/index.blade.php`.
- **Grid master** (barang, kategori, lokasi, supplier, satuan, departemen, penerima, pengguna, dst.): kolom terdepan memakai KOTAK warna per baris berisi inisial, dengan nama tebal dan kode kecil di bawahnya: `['key' => 'name', 'label' => '<Nama>', 'type' => 'identity', 'code' => '<kolom kode>', 'box' => true]`. Tambahkan `'hue' => 'hue'` bila warnanya bermakna dan controller mengirim kolom `hue` dari `App\Support\Palette` (barang menurut kategori, satuan menurut jenis); tanpa `hue` warna dipilih otomatis. Contoh: `resources/views/items/index.blade.php`, `suppliers/index.blade.php`. Grid master BARU wajib mengikuti pola ini, bukan `avatar => false`.
- Pengecualian yang ada: `groups/index` dan `modules/index` (menu Keamanan) memakai `avatar => false`, belum berkotak.

## HELP: User's Guide (UG) dan Technical Guide (TG)
HELP (profil > "Help & support", alamat lewat prop `help-url` pada `x-dl::layer-shell` di `home.blade.php`) SELALU terbagi dua, keduanya HTML. Setiap perubahan fitur memperbarui UG dan TG di perubahan yang sama (satu sumber: komentar `REMARK` di kode).

**UG, User's Guide: Bahasa Indonesia, untuk semua pengguna.** Tuntunan cara pakai, bukan teknis:
- cara memakai **grid** (cari, Filters, Columns, Export, klik 2x membuka, tombol Aksi, Aktif/Nonaktif, warna Status);
- cara memakai **form** (Simpan, Confirm, Delete, Cancel dan pengajuannya, Kembali ke grid, baris detail, kolom wajib);
- **daftar isi modul** (kelompok menu dan isi tiap modul) dan **fitur tiap modul** (apa yang bisa dilakukan, siapa yang boleh);
- bahasa sederhana, langkah bernomor, istilah sama persis dengan label di layar. Bagian yang butuh peran lain ditandai ("hanya Supervisor").

**TG, Technical Guide: English, HANYA Administrator.** Semua hal teknis ada di sini:
- data flow, system flow, workflow/state diagram (siklus dokumen, persetujuan, pembalikan stok);
- tabel dan database (skema, kolom, relasi, indeks, migrasi), buku besar stok dan perhitungan moving average;
- arsitektur (controller, service, middleware, Modules/akses), konfigurasi, perintah artisan, seeder, deployment;
- keputusan beserta alasannya. Ditulis setelah modulnya stabil.
- Ini satu-satunya pengecualian aturan "semua tampilan Indonesia": TG tidak lewat kamus `Indonesian` dan tidak punya versi Indonesia.

**Flow = GAMBAR.** Setiap flow (alur data, alur sistem, workflow/siklus status, alur pemakaian di UG) dibuat sebagai diagram bergambar, bukan daftar teks atau tabel:
- Format **SVG** (tajam di semua ukuran, tanpa pustaka/CDN). Sumber disimpan di `resources/help/diagrams/*.svg`, disisipkan ke halaman HELP lewat Blade (inline atau `<img src="data:image/svg+xml;base64,...">`), BUKAN berkas di `public/`, supaya diagram TG ikut terlindung hak akses Administrator.
- Bahasa label mengikuti dokumennya: diagram TG English, diagram UG Indonesia. Warna dari token tema (terang/gelap terbaca), teks tidak terpotong, lebar menyesuaikan layar sempit (`viewBox`, `max-width: 100%`).
- Tiap gambar punya judul/keterangan dan `alt` yang menjelaskan alurnya. Alur harus cocok dengan kode (status, tombol, aturan Cancel, nama tabel); periksa terhadap `StockDocuments`/migrasi, jangan menebak.
- Contoh wajib: siklus dokumen (Draf > Confirm > pengajuan batal > Batal, penyesuaian dengan Approve/Reject), alur stok (dokumen > StockLedger > stock_movements > items), hubungan tabel (ERD), alur login dan hak akses.
- Gambar diperbarui di perubahan yang sama dengan kodenya.

**Akses:** item Help tampil untuk semua peran; UG terbuka untuk semua yang sudah login, tab/tautan TG hanya untuk Administrator. TG dijaga di server (middleware `administrator` / `EnsureAdministrator`, 403 untuk URL langsung); menyembunyikan tautan saja TIDAK cukup. UG hanya menampilkan modul yang dapat diakses pengguna itu (asumsi, ikut `Menu::modules`).
**Penyimpanan:** jangan taruh berkas HELP di `public/` (terbuka tanpa login); simpan di `resources/` dan sajikan lewat controller dengan rute di dalam grup `auth`.
**Status (2026-10-20):** UG dan TG sudah lengkap dan terpisah. Menu profil "Bantuan & dukungan" (label kode "Help & support") tampil untuk SEMUA peran dan membuka UG (`resources/views/help/user-guide.blade.php`, rute `help.user-guide` di grup auth, bagian modul disaring `User::canAccess`); TG (`help/technical-guide.blade.php`, rute `help.technical-guide` di grup administrator) hanya lewat tab yang tautannya dirender untuk Administrator (`help/tabs.blade.php`). Tes: `tests/Feature/HelpTest.php`.
- Diagram: berkas SVG di `resources/help/diagrams/{ug|tg}-*.svg`, disisipkan inline lewat `@include('help.diagram', ['name' => ..., 'caption' => ...])`; warna lewat kelas `.g-svg` di `app.css`. Diagram awalnya dibangkitkan skrip sekali pakai (tidak disimpan); mengubah alur = edit SVG-nya langsung (rect/path/text, koordinat berpola) dan keterangannya, lalu periksa tampilannya di browser dan terhadap kode.
- Layar sempit: figur dan tabel HELP bergulir horizontal di dalam kotaknya (halaman tidak boleh melebar). Setelah mengubah `app.css` jalankan `npm run build`.
- Setiap fitur baru memperbarui UG (Indonesia, label persis seperti di layar, mis. lewat tangkapan layar) DAN TG (English: tabel, alur, keputusan) pada perubahan yang sama.

## Data contoh (seeder)
Data contoh hidup HANYA di `database/seeders` (dipanggil `DatabaseSeeder`), tidak pernah di migrasi atau di database lokal saja, supaya bisa dibuat ulang kapan pun.
- **Setiap perubahan skema (kolom/field ditambah, dihapus, diganti nama, jadi wajib) memperbarui seeder DALAM perubahan yang sama**, bersama form, request, kamus, dan tes. Cari pemakai kolom itu: `grep -rn "<kolom>" database/seeders app tests resources`. Kolom baru wajib diisi nilai yang masuk akal; kolom yang dihapus dibuang dari seeder.
- **Seeder master idempoten:** `updateOrCreate` / `firstOrCreate` berkunci kode alami (kode barang, kode supplier, username), jadi aman dijalankan ulang dan ikut memperbarui data lama.
- **Transaksi contoh lewat jalur resmi** (`StockDocuments` -> `StockLedger`, seperti `SampleTransactionSeeder`), JANGAN `insert` langsung ke `stock_documents`/`stock_movements`/`items.stock_qty`, karena stok dan rata-rata biaya harus cocok dengan buku besar. Dilewati bila sudah ada dokumen (`exists()`).
- **Cakupan:** setiap status penting punya contoh (Draf, Confirm, penyesuaian menunggu dan disetujui, barang di bawah Reorder Point), tiga akun uji tiap peran, dan data berwarna-warni agar grid/KPI/grafik terlihat hidup. Jumlah cukup kecil (puluhan barang), nama dan istilah Indonesia.
- **Data wilayah** (`geo_*`) BUKAN seeder: disalin dari tmerp lewat `php artisan sstock:import-geo`; setelah `migrate:fresh` jalankan lagi.
- **Memeriksa:** `tests/Feature/SeedersTest.php` menjalankan `DatabaseSeeder` dua kali (tidak boleh ganda, stok = jumlah buku besar). Wajib lulus setelah perubahan skema. Membuat ulang database lokal (`php artisan migrate:fresh --seed` lalu `sstock:import-geo`) MENGHAPUS semua isinya; lakukan hanya bila pemilik meminta atau datanya memang hanya contoh, dan sebut itu.
- Password akun uji hanya untuk pengembangan; ganti sebelum produksi (`UserSeeder`).

## Tombol grid
Toolbar grid (Cari, Filters, Columns, Export CSV, pilih kolom/kerapatan) datang otomatis dari `x-dl::grid`; jangan dibuat manual. Yang harus dideklarasikan layar:
- **`actions`**: `master` = kolom Aksi berisi **Edit** dan **Aktif/Nonaktif** (wajib `:toggle-url="url('<modul>/{id}/toggle')"`, kalau tidak hanya pratinjau); `worklist` = **Buka** saja (transaksi); `none` = tanpa aksi (daftar baca saja, mis. Daftar Modul).
- **`+ New Record`** lewat `:new-url="route('<modul>.create')"`. Sembunyikan (`:new-record="null"`) bagi yang tak berhak membuat (contoh barang: `$sup ? '+ New Record' : null`).
- **`:open-url="url('<modul>/{id}')"`**: klik 2x baris / Buka / Edit membuka form.
- **`search="..."`** placeholder yang menyebut kolom yang dicari; **`stats`** (kotak hitung = filter) bila ada status yang sering disaring (contoh Penyesuaian: Semua, Menunggu persetujuan, Draf).
- **Confirm, Approve, Cancel, Delete TIDAK ada di grid**, hanya di form dokumen (aturan DevLib untuk `worklist`).
- Aktif/Nonaktif hanya untuk master; transaksi tidak pernah dinonaktifkan.
- Bulk (centang + aksi massal) tidak dipakai di sini kecuali diminta pemilik.

## Tombol Kembali
Setiap form (dokumen maupun master) wajib punya tombol Kembali yang menuju GRID modulnya, bukan layar sebelumnya. Caranya: pada komponen toolbar form isi prop `:back="route('<modul>.index')"` (lihat `resources/views/items/form.blade.php`). `config/devlib.php` `back_history => false` (`DL_BACK_HISTORY=false`) membuat tombol memakai alamat itu, bukan `history.back()`. Jangan memakai `history.back()` atau `url()->previous()` di form baru.

## Perintah
`php artisan test` (SQLite in-memory), `vendor\bin\pint --dirty`, `npm run build`, `php artisan sstock:wallpapers`, `php artisan sstock:import-geo`.

## Jebakan alat
- Heredoc Python di bash gagal bila berisi `\` atau tanda kutip: pakai Write/Edit atau berkas skrip.
- Tinker satu baris berisi `$` gagal: pakai berkas skrip (`php artisan tinker <berkas.php>`).
- Widget DevLib: select = `[data-dl-select]` + input tersembunyi `[data-dl-value]`; modal dibuka dengan class `is-on` pada `[data-dl-modal-id]`; validasi form menghitung `.dl-field__req` yang kosong.
- Migrasi adalah sumber kebenaran skema (DDL referensi lama sudah dihapus).
