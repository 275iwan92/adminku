<?php

namespace Database\Seeders;

use App\Services\SampleHr;
use Illuminate\Database\Seeder;

/* REMARK
 * Tujuan       : Data contoh Karyawan dari snapshot HR kanonik (database/sample/hr): David (E-C001, Maintenance) dan Detta (E-C002, bagian HR) dengan semua tab terisi, foto profil dan KTP tiruan DUMMY.
 *                Idempoten: karyawan yang sudah ada tidak diubah (untuk mengembalikan ke snapshot: php artisan sample:hr --reset). Tidak membuat akun Jays.
 * Sumber hulu  : App\Services\SampleHr.
 * Output       : m_employee (+ family, doc, contract), berkas di disk 'ga' folder employee/{id}.
 * Dipakai oleh : DatabaseSeeder; dijalankan tersendiri dengan --class=EmployeeSampleSeeder.
 * Status       : Aktif (data contoh).
 */
class EmployeeSampleSeeder extends Seeder
{
    public function run(): void
    {
        SampleHr::apply(false, 'employees');
    }
}
