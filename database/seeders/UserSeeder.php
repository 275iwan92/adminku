<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Akun awal: adm (Admin Data), spv (Supervisor), admin (Administrator), plus (bila diatur di .env) super admin tersembunyi. Ganti password sebelum dipakai di luar mesin pengembangan.
 * Output       : tabel users (username, peran).
 * Dipakai oleh : DatabaseSeeder.
 * Status       : Aktif (data uji lokal).
 */
class UserSeeder extends Seeder
{
    private const PASSWORD = 'sstock-dev-2026';

    // Akun UAT bawaan. Layar super admin (Akun UAT) menampilkan kata sandi bawaan ini; berlaku selama belum diubah.
    // Tiga akun SAMA di AdminKU, Jays, dan Stoku (perintah pemilik 2026-11-03; sandi sengaja sederhana, data demo / UAT): adm = Admin Data (buka semua, tidak menyetujui),
    // spv = Supervisor (buka semua, menyetujui), admin = Administrator (semuanya + kelola pengguna).
    public const ACCOUNTS = [
        ['username' => 'adm', 'name' => 'Admin Data', 'email' => 'adm@sstock.test', 'role' => User::ROLE_ADMIN_DATA, 'password' => '12345677'],
        ['username' => 'spv', 'name' => 'Supervisor', 'email' => 'spv@sstock.test', 'role' => User::ROLE_SUPERVISOR, 'password' => '12345678'],
        ['username' => 'admin', 'name' => 'Administrator', 'email' => 'admin@sstock.test', 'role' => User::ROLE_ADMINISTRATOR, 'password' => '123456788'],
    ];

    // Akun produk demo AdminKU (SSTOCK_DEMO=true): bukan akun nyata; sandi dan peran dicatat di docs/PEMISAHAN-ADMINKU-JAYS.md.
    public const DEMO_PASSWORD = 'Demo-AdminKU-2026';

    public const DEMO_ACCOUNTS = [
        ['username' => 'adm', 'name' => 'Admin Data Demo', 'email' => 'adm@adminku.test', 'role' => User::ROLE_ADMIN_DATA, 'password' => '12345677'],
        ['username' => 'spv', 'name' => 'Supervisor Demo', 'email' => 'spv@adminku.test', 'role' => User::ROLE_SUPERVISOR, 'password' => '12345678'],
        ['username' => 'admin', 'name' => 'Administrator Demo', 'email' => 'admin@adminku.test', 'role' => User::ROLE_ADMINISTRATOR, 'password' => '123456788'],
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
        $groups = ['data_entry' => $groups['DATAENTRY'] ?? null, 'admin_data' => $groups['ADMINDATA'] ?? null, 'supervisor' => $groups['SUPERVISOR'] ?? null, 'administrator' => $groups['ADMIN'] ?? null];

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
