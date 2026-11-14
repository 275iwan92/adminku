<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/* REMARK
 * Tujuan       : Gambar apa pun yang ditaruh di public/media/bg/sstock otomatis jadi wallpaper login: PNG / WebP / BMP / GIF / AVIF diubah ke JPG, gambar terlalu besar (> 2560 px lebar atau > 1,5 MB) dikecilkan,
 *                rasio ganjil (mis. potret) dipotong tengah ke 16:9. File asli dipindah ke storage/app/wallpaper-source (tidak ikut git). Login memilih acak dari semua .jpg di folder itu (DevLib Frame).
 *                Layar HP: tiap foto punya kembaran tegak <nama>-portrait.jpg (9:16, lebar <= 1080 px). Foto tegak yang ditaruh sendiri (<nama>-portrait.png dst.) diolah seperti foto lanskap; bila belum ada, kembaran dibuat
 *                otomatis dari foto lanskapnya (potongan 9:16 di bagian paling rinci, bukan tengah buta) supaya HP tidak mengunduh foto 2560 px yang dipotong. Tanpa kembaran, layar HP memakai foto lanskap.
 * Sumber hulu  : folder wallpaper, ekstensi GD (PNG, JPEG, WebP, BMP, GIF, AVIF).
 * Output       : file .jpg siap pakai di folder wallpaper (+ kembaran -portrait.jpg); jumlah gambar yang diproses; random(): alamat satu foto untuk layar HP. Gagal pada satu file tidak pernah menggagalkan halaman login (dicatat ke log).
 * Dipakai oleh : AuthController@show dan PasswordResetController (lewat defer(): jalan SESUDAH halaman terkirim, jadi login tidak menunggu; gambar baru tampil di pembukaan login berikutnya), perintah sstock:wallpapers, partials/hp-wallpaper (login HP).
 * Status       : Aktif.
 */
final class Wallpapers
{
    private const EXT = ['png', 'webp', 'bmp', 'gif', 'avif', 'jpg', 'jpeg'];

    private const MAX_WIDTH = 2560;

    private const PORTRAIT_WIDTH = 1080;

    private const MAX_BYTES = 1_500_000;

    private const MAX_PIXELS = 40_000_000;  // shortcut: batas memori GD (256 MB), naikkan memory_limit bila perlu foto lebih besar

    public static function dir(): string
    {
        return public_path('media/bg/sstock');
    }

    public static function backupDir(): string
    {
        return storage_path('app/wallpaper-source');
    }

    /** @return int jumlah gambar yang diproses (konversi + kembaran tegak yang dibuat) */
    public static function sync(?string $dir = null, ?string $backup = null): int
    {
        $dir ??= self::dir();
        $backup ??= self::backupDir();
        $done = 0;

        foreach (glob($dir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (! is_file($file) || ! in_array($ext, self::EXT, true) || time() - filemtime($file) < 3) {
                continue;  // bukan gambar, atau file masih disalin
            }
            if (in_array($ext, ['jpg', 'jpeg'], true) && self::ready($file)) {
                continue;
            }
            try {
                self::convert($file, $ext, $dir, $backup);
                $done++;
            } catch (\Throwable $e) {
                Log::warning("Wallpaper {$file} dilewati: ".$e->getMessage());
            }
        }

        // Kembaran tegak dibuat sekali; ganti foto lanskap = hapus kembarannya agar dibuat ulang.
        foreach (glob($dir.DIRECTORY_SEPARATOR.'*.jpg') ?: [] as $file) {
            $twin = substr($file, 0, -4).'-portrait.jpg';
            if (self::isPortrait($file) || file_exists($twin) || time() - filemtime($file) < 3) {
                continue;
            }
            try {
                self::twin($file, $twin, $dir);
                $done++;
            } catch (\Throwable $e) {
                Log::warning("Wallpaper tegak {$file} dilewati: ".$e->getMessage());
            }
        }

        return $done;
    }

    /** Semua foto lanskap beserta kembaran tegaknya untuk imageList Frame DevLib 2 (peramban tidak membaca folder). @return list<array{src: string, portrait: ?string}> */
    public static function list(?string $dir = null): array
    {
        $dir ??= self::dir();
        $out = [];
        foreach (glob($dir.DIRECTORY_SEPARATOR.'*.jpg') ?: [] as $file) {
            if (self::isPortrait($file)) {
                continue;
            }
            $twin = substr($file, 0, -4).'-portrait.jpg';
            $out[] = ['src' => asset('media/bg/'.basename($dir).'/'.basename($file)), 'portrait' => file_exists($twin) ? asset('media/bg/'.basename($dir).'/'.basename($twin)) : null];
        }

        return $out;
    }

    /** Kutipan motivasi (public/media/text/motivasi.txt, satu per baris "teks | penulis", baris # diabaikan) untuk quoteList Frame. @return list<string> */
    public static function quotes(?string $file = null): array
    {
        $file ??= public_path('media/text/motivasi.txt');

        return is_file($file) ? array_values(array_filter(array_map('trim', file($file, FILE_IGNORE_NEW_LINES) ?: []), fn ($l) => $l !== '' && $l[0] !== '#')) : [];
    }
    /** Alamat satu foto acak untuk layar HP: kembaran tegak bila ada, kalau tidak foto lanskapnya; null bila folder kosong. */
    public static function random(?string $dir = null): ?string
    {
        $dir ??= self::dir();
        $files = array_values(array_filter(glob($dir.DIRECTORY_SEPARATOR.'*.jpg') ?: [], fn ($f) => ! self::isPortrait($f)));
        if (! $files) {
            return null;
        }
        $file = $files[array_rand($files)];
        $twin = substr($file, 0, -4).'-portrait.jpg';

        return asset('media/bg/'.basename($dir).'/'.basename(file_exists($twin) ? $twin : $file));
    }

    private static function isPortrait(string $file): bool
    {
        return str_ends_with(strtolower(pathinfo($file, PATHINFO_FILENAME)), '-portrait');
    }

    private static function ready(string $file): bool
    {
        $size = @getimagesize($file);

        return $size && $size[0] <= (self::isPortrait($file) ? self::PORTRAIT_WIDTH : self::MAX_WIDTH) && filesize($file) <= self::MAX_BYTES;
    }

    /**
     * Potongan 9:16 yang digeser ke kolom paling rinci (selisih terang tetangga pada gambar mini 160 px), sedikit condong ke tengah.
     *
     * @return array{int, int, int, int} sx, sy, sw, sh
     */
    private static function portraitCrop(\GdImage $src, int $w, int $h): array
    {
        $sw = (int) round($h * 9 / 16);
        if ($sw >= $w) {   // sudah tegak / lebih sempit dari 9:16: potong tinggi saja, tengah
            $sh = (int) round($w * 16 / 9);

            return [0, (int) (max(0, $h - $sh) / 2), $w, min($h, $sh)];
        }

        $tw = 160;
        $th = max(2, (int) round($h * $tw / $w));
        $thumb = imagescale($src, $tw, $th);
        $col = array_fill(0, $tw, 0);
        for ($x = 0; $x < $tw - 1; $x++) {
            for ($y = 0; $y < $th; $y++) {
                $col[$x] += abs((imagecolorat($thumb, $x, $y) & 0xFF) - (imagecolorat($thumb, $x + 1, $y) & 0xFF));
            }
        }
        $win = max(1, (int) round($sw * $tw / $w));
        $best = $bestAt = 0;
        for ($x = 0; $x + $win <= $tw; $x++) {
            $score = array_sum(array_slice($col, $x, $win)) * (1 - 0.3 * abs($x + $win / 2 - $tw / 2) / ($tw / 2));
            if ($score > $best) {
                [$best, $bestAt] = [$score, $x];
            }
        }

        return [min($w - $sw, (int) round($bestAt * $w / $tw)), 0, $sw, $h];
    }

    private static function twin(string $file, string $twin, string $dir): void
    {
        $info = @getimagesize($file);
        if (! $info || $info[0] * $info[1] > self::MAX_PIXELS || ! ($src = @imagecreatefromstring((string) file_get_contents($file)))) {
            throw new \RuntimeException('bukan gambar yang bisa dibaca, atau terlalu besar');
        }
        rename(self::render($src, self::portraitCrop($src, $info[0], $info[1]), self::PORTRAIT_WIDTH, $dir, 82), $twin);
    }

    /** Potong + kecilkan + simpan ke berkas sementara; pemanggil memindahkannya ke nama akhir. */
    private static function render(\GdImage $src, array $crop, int $maxWidth, string $dir, int $quality = 88): string
    {
        [$sx, $sy, $sw, $sh] = $crop;
        $tw = min($sw, $maxWidth);
        $th = (int) round($sh * $tw / $sw);

        $dst = imagecreatetruecolor($tw, $th);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));  // PNG / GIF transparan jadi putih
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $tw, $th, $sw, $sh);
        imageinterlace($dst, true);

        $tmp = $dir.DIRECTORY_SEPARATOR.'.'.Str::random(8).'.tmp';
        if (! imagejpeg($dst, $tmp, $quality)) {
            @unlink($tmp);
            throw new \RuntimeException('gagal menulis JPG');
        }

        return $tmp;
    }

    private static function convert(string $file, string $ext, string $dir, string $backup): void
    {
        $info = @getimagesize($file);
        if (! $info || $info[0] * $info[1] > self::MAX_PIXELS) {
            throw new \RuntimeException('bukan gambar yang bisa dibaca, atau terlalu besar');
        }
        [$w, $h] = $info;
        $src = @imagecreatefromstring((string) file_get_contents($file));
        if (! $src) {
            throw new \RuntimeException('format tidak didukung');
        }

        // Lanskap: rasio di luar 1,4 - 2,4 (mis. potret) dipotong tengah ke 16:9. Foto -portrait: rasio di luar 0,5 - 0,7 dipotong ke 9:16.
        $portrait = self::isPortrait($file);
        [$sx, $sy, $sw, $sh] = [0, 0, $w, $h];
        $ratio = $w / $h;
        if ($portrait) {
            if ($ratio < 0.5 || $ratio > 0.7) {
                [$sx, $sy, $sw, $sh] = self::portraitCrop($src, $w, $h);
            }
        } elseif ($ratio < 1.4 || $ratio > 2.4) {
            if ($ratio < 16 / 9) {
                $sh = (int) round($w * 9 / 16);
                $sy = (int) (($h - $sh) / 2);
            } else {
                $sw = (int) round($h * 16 / 9);
                $sx = (int) (($w - $sw) / 2);
            }
        }

        $isJpg = in_array($ext, ['jpg', 'jpeg'], true);
        $name = $isJpg ? basename($file) : (Str::slug(pathinfo($file, PATHINFO_FILENAME)) ?: 'wallpaper').'.jpg';
        $target = $dir.DIRECTORY_SEPARATOR.$name;
        for ($i = 2; ! $isJpg && ! $portrait && file_exists($target); $i++) {   // foto -portrait menimpa kembaran otomatis
            $target = $dir.DIRECTORY_SEPARATOR.pathinfo($name, PATHINFO_FILENAME)."-{$i}.jpg";
        }

        // Simpan dulu ke berkas sementara, baru asli dipindah ke cadangan, lalu hasil menggantikannya.
        $tmp = self::render($src, [$sx, $sy, $sw, $sh], $portrait ? self::PORTRAIT_WIDTH : self::MAX_WIDTH, $dir);
        @mkdir($backup, 0775, true);
        $keep = $backup.DIRECTORY_SEPARATOR.basename($file);
        if (file_exists($keep)) {
            $keep = $backup.DIRECTORY_SEPARATOR.date('YmdHis').'-'.basename($file);
        }
        rename($file, $keep);
        rename($tmp, $target);
    }
}
