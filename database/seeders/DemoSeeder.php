<?php

namespace Database\Seeders;

use App\Models\UserGroup;
use App\Support\Company;
use App\Support\Constants;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/* REMARK
 * Tujuan       : Identitas perusahaan fiktif untuk produk demo AdminKU. Disimpan di data (menu Perusahaan, tabel sys_constant), bukan di kode, sehingga bisa diubah dari layar.
 * Output       : sys_constant company.*.
 * Dipakai oleh : DatabaseSeeder hanya bila config sstock.demo.
 * Status       : Aktif (data demo).
 */
class DemoSeeder extends Seeder
{
    public const COMPANY = [
        'name' => 'PT XYZ',
        'legal_name' => 'PT XYZ',
        'address' => "Jl. Contoh Raya No. 1\nSurabaya, Jawa Timur",
        'phone' => '031-0000000',
        'email' => 'info@xyz.example',
        'npwp' => '00.000.000.0-000.000',
        'weather_city' => 'Surabaya',
        'weather_lat' => '-7.2575',
        'weather_lon' => '112.7521',
    ];

    public function run(): void
    {
        foreach (self::COMPANY as $key => $value) {
            Constants::set('company.'.$key, $value, 'company');
        }
        Constants::set('company.kop_style', 'logo_name_address', 'company');
        // Logo contoh: tabel reset menghapus storage/app/company, jadi dipasang ulang dari berkas seeder.
        File::ensureDirectoryExists(Company::dir());
        File::copy(__DIR__.'/assets/logo-xyz.png', Company::dir().DIRECTORY_SEPARATOR.Company::LOGO_FILE);
        Constants::set('company.logo', Company::LOGO_FILE, 'company');

        // Hak Purchase contoh: Supervisor mengelola seluruh alur dan menyetujui; Data Entry hanya membuat PP. Administrator sudah penuh.
        foreach (['SUPERVISOR' => ['PUP', 'PUK', 'PUO', 'PUV'], 'DATAENTRY' => ['PUP']] as $code => $modules) {
            if ($group = UserGroup::query()->where('code', $code)->first()) {
                $group->syncAccess($group->moduleAccess() + array_fill_keys($modules, 'rw'));
            }
        }
    }
}
