<?php

/*
 * REMARK
 * Tujuan       : Daftar trek musik latar AdminKU (berkas di public/audio) beserta kredit pencipta dan lisensinya.
 * Input        : -
 * Output       : config('music.tracks'); dibaca partial resources/views/partials/music.blade.php (footer kredit tampil untuk SEMUA trek).
 * Dipakai oleh : partials.music (Beranda). Menambah trek = taruh MP3 di public/audio + satu baris di sini.
 * Aturan       : hanya musik berizin/berlisensi. Kredit (judul + pencipta) wajib tampil selama trek diputar.
 * Status       : Aktif.
 */
return [
    'tracks' => [
        ['file' => 'heartwarming.mp3', 'title' => 'Heartwarming', 'author' => 'Kevin MacLeod', 'url' => 'https://incompetech.com', 'license' => 'CC BY 4.0', 'license_url' => 'https://creativecommons.org/licenses/by/4.0/'],
        ['file' => 'meditation-impromptu-01.mp3', 'title' => 'Meditation Impromptu 01', 'author' => 'Kevin MacLeod', 'url' => 'https://incompetech.com', 'license' => 'CC BY 4.0', 'license_url' => 'https://creativecommons.org/licenses/by/4.0/'],
        ['file' => 'gymnopedie-no-1.mp3', 'title' => 'Gymnopedie No. 1 (Erik Satie)', 'author' => 'Kevin MacLeod', 'url' => 'https://incompetech.com', 'license' => 'CC BY 4.0', 'license_url' => 'https://creativecommons.org/licenses/by/4.0/'],
        ['file' => 'meditation-impromptu-02.mp3', 'title' => 'Meditation Impromptu 02', 'author' => 'Kevin MacLeod', 'url' => 'https://incompetech.com', 'license' => 'CC BY 4.0', 'license_url' => 'https://creativecommons.org/licenses/by/4.0/'],
        // Menunggu berkas MP3 dari Iwan (izin pencipta: wajib mencantumkan judul dan nama pencipta):
        // ['file' => 'river-flows-in-you-frame.mp3', 'title' => "River Flows In You ('f r a m e' ver.)", 'author' => 'Yiruma', 'url' => '', 'license' => 'izin pencipta', 'license_url' => ''],
    ],
];
