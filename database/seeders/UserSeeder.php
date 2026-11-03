<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Akun awal untuk dua peran: Supervisor dan Data Entry, plus akun admin dan (bila diatur di .env) super admin tersembunyi. Ganti password sebelum dipakai di luar mesin pengembangan.
 * Output       : tabel users (username, peran).
 * Dipakai oleh : DatabaseSeeder.
 * Status       : Aktif (data uji lokal).
 */
class UserSeeder extends Seeder
{
    private const PASSWORD = 'sstock-dev-2026';

    // Akun UAT bawaan. Layar super admin (Akun UAT) menampilkan kata sandi bawaan ini; berlaku selama belum diubah.
    public const ACCOUNTS = [
        // Password akun uji (permintaan pemilik untuk pengujian); ganti sebelum produksi.
        ['username' => 'supervisor', 'name' => 'Supervisor Gudang', 'email' => 'supervisor@sstock.test', 'role' => User::ROLE_SUPERVISOR, 'password' => '1qa2ws3ed'],
        ['username' => 'entry', 'name' => 'Staf Data Entry', 'email' => 'entry@sstock.test', 'role' => User::ROLE_DATA_ENTRY, 'password' => '12345678'],
        // Akun admin untuk pengembangan dan UAT nanti (permintaan pemilik): peran Administrator (hak Supervisor + menu Keamanan), password lemah sengaja. Hapus / ganti sebelum produksi.
        ['username' => 'admin', 'name' => 'Admin UAT', 'email' => 'admin@sstock.test', 'role' => User::ROLE_ADMINISTRATOR, 'password' => 'admin'],
    ];

    // Akun produk demo AdminKU (SSTOCK_DEMO=true): bukan akun nyata; sandi dan peran dicatat di docs/PEMISAHAN-ADMINKU-JAYS.md.
    public const DEMO_PASSWORD = 'Demo-AdminKU-2026';

    public const DEMO_ACCOUNTS = [
        ['username' => 'supervisor', 'name' => 'Supervisor Demo', 'email' => 'supervisor@adminku.test', 'role' => User::ROLE_SUPERVISOR, 'password' => self::DEMO_PASSWORD],
        ['username' => 'entry', 'name' => 'Staf Demo', 'email' => 'entry@adminku.test', 'role' => User::ROLE_DATA_ENTRY, 'password' => self::DEMO_PASSWORD],
        ['username' => 'admin', 'name' => 'Admin Demo', 'email' => 'admin@adminku.test', 'role' => User::ROLE_ADMINISTRATOR, 'password' => self::DEMO_PASSWORD],
    ];

    public static function accounts(): array
    {
        return config('sstock.demo') ? self::DEMO_ACCOUNTS : self::ACCOUNTS;
    }

    public function run(): void
    {
        $users = self::accounts();

        // Grup bawaan dibuat migrasi; akun contoh masuk grup sesuai perannya.
        $groups = UserGroup::query()->pluck('id', 'code');
        $groups = ['data_entry' => $groups['DATAENTRY'] ?? null, 'supervisor' => $groups['SUPERVISOR'] ?? null, 'administrator' => $groups['ADMIN'] ?? null];

        foreach ($users as $u) {
            User::updateOrCreate(['username' => $u['username']], $u + ['password' => self::PASSWORD, 'is_active' => true, 'group_id' => $groups[$u['role']] ?? null]);
        }

        // Super admin tersembunyi (SSTOCK_SUPER_ADMIN + SSTOCK_SUPER_ADMIN_HASH di .env; tidak ada kata sandi di kode). Dibuat ulang setiap penyegaran data contoh.
        if (($name = User::superAdminUsername()) && ($hash = config('sstock.super_admin.hash'))) {
            User::updateOrCreate(['username' => $name], ['name' => 'Super Admin', 'email' => $name.'@sstock.test', 'role' => User::ROLE_ADMINISTRATOR, 'is_active' => true, 'group_id' => $groups['administrator'] ?? null, 'password' => $hash]);
            DB::table('users')->where('username', $name)->update(['password' => $hash]);   // hash dari .env disimpan apa adanya (cast "hashed" tidak boleh mengulang)
        }
    }
}
