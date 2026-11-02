<?php

namespace App\Support;

/* REMARK
 * Tujuan       : Gambar contoh untuk data contoh (seeder): avatar ilustrasi netral (SVG buatan sendiri, bukan foto orang nyata) dan KTP tiruan bertuliskan CONTOH / DUMMY dengan data fiktif yang sama dengan orangnya. Tanpa pustaka gambar (SVG murni).
 * Output       : string SVG.
 * Dipakai oleh : EmployeeSampleSeeder, RecruitmentSeeder.
 * Status       : Aktif (data contoh).
 */
final class SampleImages
{
    // Avatar ilustrasi: lingkaran, kepala, bahu. $gender L | P hanya menentukan model rambut.
    public static function avatar(string $bg, string $skin, string $gender): string
    {
        $hair = $gender === 'P' ? '<path d="M70 118c-8-48 18-70 50-70s58 22 50 70c-6-26-22-34-50-34s-44 8-50 34z" fill="#2b2118"/><rect x="62" y="110" width="22" height="70" rx="10" fill="#2b2118"/><rect x="156" y="110" width="22" height="70" rx="10" fill="#2b2118"/>'
            : '<path d="M78 104c-4-38 20-52 42-52s46 14 42 52c-6-18-20-24-42-24s-36 6-42 24z" fill="#2b2118"/>';

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240" width="240" height="240" role="img" aria-label="Foto contoh"><rect width="240" height="240" fill="'.$bg.'"/>'
            .'<circle cx="120" cy="120" r="120" fill="'.$bg.'" opacity=".15"/><path d="M30 240c0-52 38-84 90-84s90 32 90 84z" fill="#e8eef3"/>'
            .'<rect x="104" y="128" width="32" height="36" rx="10" fill="'.$skin.'"/><ellipse cx="120" cy="106" rx="38" ry="44" fill="'.$skin.'"/>'.$hair
            .'<circle cx="106" cy="108" r="3.5" fill="#2b2118"/><circle cx="134" cy="108" r="3.5" fill="#2b2118"/><path d="M108 128q12 9 24 0" fill="none" stroke="#7a4a3a" stroke-width="3" stroke-linecap="round"/>'
            .'<text x="120" y="232" text-anchor="middle" font-family="Arial,sans-serif" font-size="12" fill="#ffffff" opacity=".9">ILUSTRASI CONTOH</text></svg>';
    }

    // KTP tiruan mengikuti tata letak KTP Indonesia (kepala provinsi dan kota, NIK, data diri, kotak foto di kanan) dengan watermark DUMMY besar, miring, semi-transparan melintang di atas kartu: jelas bukan dokumen asli.
    // $p: nik, name, birth_place, birth_date (d-m-Y), gender L|P, address, religion, marital (kawin|belum); opsional rt_rw, village, district, city.
    public static function ktp(array $p): string
    {
        $x = fn (string $t) => htmlspecialchars($t, ENT_XML1);
        $rows = ['NIK' => $p['nik'], 'Nama' => $p['name'], 'Tempat/Tgl Lahir' => $p['birth_place'].', '.$p['birth_date'], 'Jenis Kelamin' => ($p['gender'] === 'L' ? 'LAKI-LAKI' : 'PEREMPUAN'), 'Alamat' => $p['address'],
            'RT/RW' => $p['rt_rw'] ?? '001/002', 'Kel/Desa' => $p['village'] ?? 'CONTOH', 'Kecamatan' => $p['district'] ?? 'CONTOH', 'Agama' => strtoupper($p['religion']),
            'Status Perkawinan' => $p['marital'] === 'kawin' ? 'KAWIN' : 'BELUM KAWIN', 'Pekerjaan' => 'KARYAWAN SWASTA', 'Kewarganegaraan' => 'WNI', 'Berlaku Hingga' => 'SEUMUR HIDUP'];
        $y = 92;
        $lines = '';
        foreach ($rows as $k => $v) {
            $lines .= '<text x="26" y="'.$y.'" font-size="12.5" fill="#222">'.$x($k).'</text><text x="170" y="'.$y.'" font-size="12.5" font-weight="700" fill="#222">: '.$x((string) $v).'</text>';
            $y += 22.5;
        }
        $city = strtoupper($p['city'] ?? 'KOTA CONTOH');

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 400" width="640" height="400" font-family="Arial,sans-serif" role="img" aria-label="KTP contoh"><rect width="640" height="400" rx="18" fill="#dbe9f5" stroke="#6b8fb0" stroke-width="3"/>'
            .'<text x="320" y="30" text-anchor="middle" font-size="17" font-weight="700" fill="#1d3f66">PROVINSI CONTOH</text><text x="320" y="52" text-anchor="middle" font-size="15" font-weight="700" fill="#1d3f66">'.$x($city).'</text>'
            .$lines.'<rect x="470" y="88" width="140" height="180" fill="#b9cfe3" stroke="#6b8fb0"/><circle cx="540" cy="156" r="30" fill="#8fb0cf"/><path d="M490 268c0-40 22-56 50-56s50 16 50 56z" fill="#8fb0cf"/>'
            .'<text x="540" y="300" text-anchor="middle" font-size="11" fill="#333">'.$x($city).'</text><text x="540" y="316" text-anchor="middle" font-size="11" fill="#333">02-11-2026</text>'
            .'<g transform="rotate(-24 320 215)"><text x="320" y="262" text-anchor="middle" font-size="150" font-weight="700" fill="#c0392b" opacity=".30" letter-spacing="6">DUMMY</text><text x="320" y="300" text-anchor="middle" font-size="26" font-weight="700" fill="#c0392b" opacity=".38" letter-spacing="10">CONTOH</text></g>'
            .'<text x="320" y="390" text-anchor="middle" font-size="11" fill="#444">Data fiktif untuk uji aplikasi. Bukan dokumen asli.</text></svg>';
    }
}
