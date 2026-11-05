<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/* REMARK
 * Tujuan       : Data perusahaan untuk KOP SEMUA cetakan (dokumen stok, Lembar Hitung, Berita Acara, enam laporan): nama, nama PT / CV, alamat, telepon, e-mail, NPWP, logo, dan gaya kop.
 *                Satu-satunya sumber kop: kop() dipasang ke DevLib (Document::companyUsing) dan report() dikirim ke x-dl::report. Aplikasi tetap bernama ADMINKU (login, menu, judul); hanya kop cetakan yang berubah.
 *                Data ada di tabel sys_constant (company.name, legal_name, address, phone, email, npwp, logo, kop_style). Belum pernah diisi: nama = config devlib.company.name, gaya = logo + nama bila ada logo, selain itu nama saja.
 *                Logo: PNG / JPG / WebP (bukan SVG), diperiksa menurut ISI berkas, diubah ke PNG selebar paling 600 px dan disimpan sebagai storage/app/company/logo.png (nama tidak pernah dari pengguna); disajikan rute company.logo.
 *                Cuaca Beranda: Kota / Lintang / Bujur (company.weather_city, weather_lat, weather_lon) = tempat CADANGAN cuaca, dipakai bila lokasi perangkat tidak bisa dipakai (izin ditolak, situs belum HTTPS, timeout). Kosong = tanpa cadangan.
 * Output       : get(), save(), kop(), report(), logoPath(), logoUrl(), style(), weather().
 * Dipakai oleh : AppServiceProvider (Document::companyUsing), CompanyController, view laporan (x-dl::report), StockCountPrint dan StockDocumentPrint (lewat DevLib).
 * Status       : Aktif.
 */
final class Company
{
    public const STYLES = ['logo_name_address', 'logo_name', 'name'];

    public const FIELDS = ['name', 'legal_name', 'address', 'phone', 'email', 'npwp', 'weather_city', 'weather_lat', 'weather_lon'];

    public const LOGO_FILE = 'logo.png';

    private const MAX_WIDTH = 600;

    private const MAX_PIXELS = 16_000_000;   // shortcut: batas memori GD, naikkan memory_limit bila perlu logo lebih besar

    public static function dir(): string
    {
        return storage_path('app/company');
    }

    public static function logoPath(): ?string
    {
        $path = self::dir().DIRECTORY_SEPARATOR.self::LOGO_FILE;

        return Constants::get('company.logo') === self::LOGO_FILE && is_file($path) ? $path : null;
    }

    /** Alamat relatif (tanpa host) supaya tetap jalan di halaman tautan publik; ?v= = waktu ubah berkas agar cache browser ikut berganti. */
    public static function logoUrl(): ?string
    {
        $path = self::logoPath();

        return $path ? parse_url(route('company.logo', ['v' => filemtime($path)]), PHP_URL_PATH).'?v='.filemtime($path) : null;
    }

    /** @return array{name: string, legal_name: string, address: string, phone: string, email: string, npwp: string, kop_style: string, configured: bool} */
    public static function get(): array
    {
        $c = [];
        foreach (self::FIELDS as $field) {
            $c[$field] = trim((string) Constants::get('company.'.$field, ''));
        }
        $c['configured'] = $c['name'] !== '';
        $c['name'] = $c['name'] !== '' ? $c['name'] : (string) config('devlib.company.name', config('app.name'));
        $c['kop_style'] = self::style();

        return $c;
    }

    /** Tempat cadangan cuaca Beranda ['city' => ?string, 'lat' => float, 'lon' => float]; null bila Lintang / Bujur belum diisi. */
    public static function weather(): ?array
    {
        $lat = trim((string) Constants::get('company.weather_lat', ''));
        $lon = trim((string) Constants::get('company.weather_lon', ''));
        if (! is_numeric($lat) || ! is_numeric($lon)) {
            return null;
        }
        $city = trim((string) Constants::get('company.weather_city', ''));

        return ['city' => $city !== '' ? $city : null, 'lat' => (float) $lat, 'lon' => (float) $lon];
    }

    /** Gaya tersimpan; belum ada / tidak sah = logo + nama bila ada logo, selain itu nama saja. */
    public static function style(): string
    {
        $stored = (string) Constants::get('company.kop_style', '');

        return in_array($stored, self::STYLES, true) ? $stored : (self::logoPath() ? 'logo_name' : 'name');
    }

    /** @param array<string, mixed> $data name, legal_name, address, phone, email, npwp, kop_style */
    public static function save(array $data, ?UploadedFile $logo = null, bool $removeLogo = false): void
    {
        foreach (self::FIELDS as $field) {
            Constants::set('company.'.$field, trim((string) ($data[$field] ?? '')), 'company');
        }
        Constants::set('company.kop_style', in_array($data['kop_style'] ?? null, self::STYLES, true) ? $data['kop_style'] : 'name', 'company');

        if ($logo) {
            self::storeLogo($logo);
        } elseif ($removeLogo) {
            @unlink(self::dir().DIRECTORY_SEPARATOR.self::LOGO_FILE);
            Constants::set('company.logo', null, 'company');
        }
    }

    /** Baris alamat (tiap baris teks alamat) dan baris kontak. @return array{0: list<string>, 1: string} */
    public static function lines(?array $c = null): array
    {
        $c ??= self::get();
        $address = array_values(array_filter(array_map('trim', preg_split('/\R/u', $c['address']) ?: [])));
        $contact = implode(' · ', array_filter([$c['phone'] !== '' ? 'Telp. '.$c['phone'] : '', $c['email']]));

        return [$address, $contact];
    }

    /** Kop untuk Devlib\PrintDoc\Document::companyUsing (print-doc, print-sheet). */
    public static function kop(): array
    {
        $c = self::get();
        [$address, $contact] = self::lines($c);
        $logo = self::logoUrl();

        return [
            'name' => $c['name'], 'legal_name' => $c['legal_name'], 'style' => $c['kop_style'], 'initials' => self::initials($c['name']),
            'logo' => $logo, 'logoBw' => $logo, 'lines' => array_slice(array_values(array_filter([...$address, $contact])), 0, 3), 'npwp' => $c['npwp'] !== '' ? $c['npwp'] : null,
        ];
    }

    /** Kop untuk x-dl::report / print-out: membaca address dan contact (bukan lines). Tagline bawaan hanya selama perusahaan belum diisi. */
    public static function report(): array
    {
        $c = self::get();
        [$address, $contact] = self::lines($c);
        $logo = self::logoUrl();

        return [
            'name' => $c['name'], 'legal_name' => $c['legal_name'], 'style' => $c['kop_style'], 'initials' => self::initials($c['name']),
            'logo' => $logo, 'logoBw' => $logo, 'address' => implode(', ', $address), 'contact' => $contact, 'npwp' => $c['npwp'] !== '' ? $c['npwp'] : null,
            'tagline' => $c['configured'] ? null : config('devlib.company.tagline'),
        ];
    }

    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = count($words) > 1 ? mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1) : mb_substr($name, 0, 2);

        return mb_strtoupper($letters);
    }

    /** Logo masuk hanya bila ISInya PNG / JPEG / WebP yang bisa dibaca (SVG dan lainnya ditolak), lalu diubah ke PNG selebar paling 600 px. */
    private static function storeLogo(UploadedFile $file): void
    {
        $bytes = (string) file_get_contents($file->getRealPath());
        $info = @getimagesizefromstring($bytes);
        if (! $info || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true) || $info[0] * $info[1] > self::MAX_PIXELS) {
            throw ValidationException::withMessages(['logo' => 'Logo harus berupa gambar PNG, JPG, atau WebP yang bisa dibaca.']);
        }
        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            throw ValidationException::withMessages(['logo' => 'Logo tidak bisa dibaca. Pakai PNG, JPG, atau WebP lain.']);
        }
        [$w, $h] = $info;
        $tw = min($w, self::MAX_WIDTH);
        $th = max(1, (int) round($h * $tw / $w));

        $dst = imagecreatetruecolor($tw, $th);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $w, $h);

        @mkdir(self::dir(), 0775, true);
        $tmp = self::dir().DIRECTORY_SEPARATOR.'.'.bin2hex(random_bytes(4)).'.tmp';
        if (! imagepng($dst, $tmp, 6)) {
            @unlink($tmp);
            throw ValidationException::withMessages(['logo' => 'Logo gagal disimpan.']);
        }
        rename($tmp, self::dir().DIRECTORY_SEPARATOR.self::LOGO_FILE);
        Constants::set('company.logo', self::LOGO_FILE, 'company');
    }
}
