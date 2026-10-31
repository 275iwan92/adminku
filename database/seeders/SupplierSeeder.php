<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Support\GeoArea;
use Illuminate\Database\Seeder;

/* REMARK
 * Tujuan       : Supplier contoh untuk transaksi Receiving, lengkap dengan kontak dan wilayah (kota dicari di data wilayah; kosong bila data wilayah belum disalin).
 * Output       : tabel m_supplier.
 * Dipakai oleh : DatabaseSeeder.
 * Status       : Aktif (data contoh).
 */
class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        // [kode, nama, telepon kantor, kontak, HP kontak, jalan, kata cari kota]
        $rows = [
            ['SUP-001', 'CV Sinar Alat Tulis', '+62315550101', 'Bapak Hendra', '+6281234567890', 'Jl. Kembang Jepun 12', 'Surabaya'],
            ['SUP-002', 'PT Teknik Baut Mandiri', '+62315550202', 'Ibu Ratna', '+6281234567891', 'Jl. Rungkut Industri 8', 'Surabaya'],
            ['SUP-003', 'UD Perkakas Jaya', '+62315550303', 'Bapak Slamet', '+6281234567892', 'Jl. Pasar Turi 45', 'Surabaya'],
        ];

        // Supplier sistem: sudah dibuat migrasi; ini menjamin ada (dan bertanda sistem) bila seeder dijalankan di database yang dibuat dengan cara lain.
        Supplier::firstOrNew(['code' => 'KONTAN'])->forceFill(['name' => 'Kontan', 'is_active' => true, 'is_system' => true])->save();

        foreach ($rows as [$code, $name, $phone, $contact, $mobile, $address, $cityTerm]) {
            $city = collect(GeoArea::search($cityTerm))->firstWhere('level', 'CITY');
            Supplier::updateOrCreate(['code' => $code], [
                'name' => $name, 'phone' => $phone, 'contact_name' => $contact, 'contact_mobile' => $mobile, 'address' => $address,
                'area_level' => $city['level'] ?? null, 'area_id' => $city['id'] ?? null, 'postal_code' => $city['postal_code'] ?? null,
            ]);
        }
    }
}
