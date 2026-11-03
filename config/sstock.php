<?php

/* REMARK
 * Tujuan       : Pengaturan khas AdminKU. sample_refresh = layar Administrator "Data Contoh" boleh menghapus seluruh data lalu mengisi ulang data contoh.
 *                Bawaan: aktif selain di production; di production wajib dinyalakan sengaja lewat SSTOCK_SAMPLE_REFRESH=true.
 *                super_admin = satu akun (username + hash kata sandi, dari .env) yang SATU-SATUNYA boleh menyegarkan data contoh, di lingkungan mana pun termasuk production;
 *                dibuat ulang oleh UserSeeder setiap penyegaran. Kosong = aturan lama (sakelar sample_refresh, semua Administrator).
 *                ip_lookup = layar Log Aktivitas boleh mengirim IP publik ke ipwho.is untuk perkiraan lokasi (App\Support\IpLocation); SSTOCK_IP_LOOKUP=false mematikannya.
 * Dipakai oleh : App\Services\SampleData, SampleDataController, UserSeeder, UserController.
 * Status       : Aktif.
 */
return [
    'sample_log' => storage_path('app/sample-refresh.json'),  // catatan penyegaran terakhir, di luar database
    // Batch yang kedaluwarsa dalam N hari ke depan tidak dihitung sebagai stok tersedia untuk titik pesan ulang. 0 = hanya yang sudah lewat (bawaan).
    'reorder_exclude_days' => (int) env('SSTOCK_REORDER_EXCLUDE_DAYS', 0),
    'sample_refresh' => (bool) env('SSTOCK_SAMPLE_REFRESH', env('APP_ENV', 'production') !== 'production'),
    'demo' => (bool) env('SSTOCK_DEMO', false),  // produk demo AdminKU (data dummy, bisa direset): lencana DEMO, teks layar masuk, akun demo, adminku:reset-demo
    'ip_lookup' => (bool) env('SSTOCK_IP_LOOKUP', true),  // Log Aktivitas: cari perkiraan lokasi IP publik ke ipwho.is (false = tidak ada IP yang dikirim keluar)
    'activity_dir' => storage_path('app/activity'),  // log aktivitas (CSV bulanan), di luar database sehingga tidak ikut terhapus saat data contoh disegarkan
    // Hanya dipakai perintah pemasangan (sstock:install, sstock:import-geo); di config agar tetap terbaca saat config:cache dipakai.
    'geo_source_db' => env('GEO_SOURCE_DB', 'tm2mst'),
    'db_admin' => ['username' => env('DB_ADMIN_USERNAME', 'root'), 'password' => env('DB_ADMIN_PASSWORD')],
    'super_admin' => ['username' => env('SSTOCK_SUPER_ADMIN'), 'hash' => env('SSTOCK_SUPER_ADMIN_HASH')],
];
