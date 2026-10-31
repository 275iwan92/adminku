<?php

namespace App\Support;

/* REMARK
 * Tujuan       : Log aktivitas pengguna (siapa, dari IP mana, kapan, buka / input / ubah / hapus apa) sebagai berkas CSV per bulan di storage/app/activity/YYYY-MM.csv.
 *                Dipilih CSV, bukan tabel: menulis = menambah satu baris di akhir berkas (tercepat, satu kunci berkas), tanpa migrasi, bisa diunduh apa adanya, dan TIDAK ikut terhapus saat data contoh disegarkan.
 *                Isi permintaan (kata sandi, formulir) tidak pernah dicatat, hanya nama rute dan alamat tanpa query. Menulis gagal = diabaikan (log tidak boleh merusak permintaan).
 * Sumber hulu  : middleware LogActivity, AuthController (tidak langsung), config sstock.activity_dir.
 * Output       : write(), read() (terbaru dulu), months(), file().
 * Dipakai oleh : LogActivity, SuperAdminController (hanya super admin yang boleh membaca).
 * Status       : Aktif.
 */
final class ActivityLog
{
    // Perkiraan byte per baris untuk membaca ekor berkas tanpa memuat seluruhnya.
    private const ROW_BYTES = 400;

    public static function file(?string $month = null): string
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $month) ? $month : now()->format('Y-m');

        return rtrim(config('sstock.activity_dir'), '/\\').DIRECTORY_SEPARATOR.$month.'.csv';
    }

    public static function write(string $user, string $ip, string $action, string $detail): void
    {
        try {
            $file = self::file();
            if (! is_dir(dirname($file))) {
                @mkdir(dirname($file), 0775, true);
            }
            $new = ! is_file($file);
            $fh = fopen($file, 'ab');
            if (! $fh) {
                return;
            }
            flock($fh, LOCK_EX);
            fputcsv($fh, array_map([self::class, 'clean'], [now()->format('Y-m-d H:i:s'), $user, $ip, $action, $detail]), ',', '"', '', "\n");
            flock($fh, LOCK_UN);
            fclose($fh);
            if ($new) {
                @chmod($file, 0664);   // PHP-FPM dan CLI bergantian menulis berkas yang sama
            }
        } catch (\Throwable) {
            // sengaja diam
        }
    }

    /** @return array<int, array{at: string, user: string, ip: string, action: string, detail: string}> terbaru dulu */
    public static function read(?string $month = null, int $limit = 1000): array
    {
        $file = self::file($month);
        if (! is_file($file) || ($size = filesize($file)) === 0) {
            return [];
        }
        $fh = fopen($file, 'rb');
        $from = max(0, $size - $limit * self::ROW_BYTES);
        fseek($fh, $from);
        $text = (string) stream_get_contents($fh);
        fclose($fh);

        $lines = explode("\n", rtrim($text, "\n"));
        if ($from > 0) {
            array_shift($lines);   // baris pertama bisa terpotong
        }

        $rows = [];
        foreach (array_reverse(array_slice($lines, -$limit)) as $line) {
            [$at, $user, $ip, $action, $detail] = array_pad(str_getcsv($line, ',', '"', ''), 5, '');
            $rows[] = ['at' => $at, 'user' => $user, 'ip' => $ip, 'action' => $action, 'detail' => $detail];
        }

        return $rows;
    }

    /** @return string[] bulan yang punya berkas (YYYY-MM), terbaru dulu */
    public static function months(): array
    {
        $months = array_map(fn ($f) => basename($f, '.csv'), glob(dirname(self::file()).DIRECTORY_SEPARATOR.'*.csv') ?: []);
        rsort($months);

        return $months;
    }

    // Satu baris = satu rekaman (tanpa baris baru), terpotong, dan aman dibuka di Excel (awalan = + - @ diberi tanda kutip).
    private static function clean(string $v): string
    {
        $v = mb_substr((string) preg_replace('/[\r\n\t]+/', ' ', $v), 0, 300);

        return $v !== '' && str_contains('=+-@', $v[0]) ? "'".$v : $v;
    }
}
