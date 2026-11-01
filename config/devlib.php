<?php

/* REMARK
 * Tujuan       : Konfigurasi DevLib untuk Stoku (stok sederhana) (identitas, bahasa, zona waktu, media login, hak akses komponen).
 * Dipakai oleh : Devlib\* (Brand, Frame, Locale, Access, Report/PrintOut).
 * Status       : Aktif.
 */
return [
    'brand' => ['name' => 'STOKU', 'tagline' => 'STOK SEDERHANA', 'logo' => '/media/brand/stoku.svg'],

    // Kop laporan cetak.
    'company' => ['name' => 'Stoku', 'tagline' => 'Gudang Maintenance & ATK'],

    'lang' => env('DEVLIB_LANG', 'id'),

    // Aplikasi satu bahasa: istilah tombol / status DevLib yang biasanya dipaksa English ikut kamus aplikasi (App\Support\Indonesian).
    'translate_fixed' => true,

    // Tombol Kembali selalu ke alamat grid-nya, bukan ke layar sebelumnya (setelah Simpan, "sebelumnya" = form isian: berisiko input ganda).
    'back_history' => false,
    'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),
    // Tahap aplikasi (Jo 2026-10-23): alpha | beta | rc | kosong (rilis). Tampil `ver 0.<jumlah commit> A` di judul logo (Devlib\AppVersion); nilai lain dianggap kosong.
    'stage' => env('APP_STAGE', 'alpha'),
    'timezone_label' => 'WIB',

    'media_path' => public_path('media'),
    'media_url' => '/media',

    // Lookup remote (/_devlib-lookup/{name}) hanya untuk user yang login.
    'lookup_middleware' => ['web', 'auth'],

    // Hak akses atribut can="..." diatur lewat Access::resolveUsing di AppServiceProvider.
    'abilities' => ['*'],
];
