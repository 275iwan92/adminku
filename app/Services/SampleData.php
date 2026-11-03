<?php

namespace App\Services;

use App\Models\User;
use App\Support\Company;
use Database\Seeders\ItemSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/* REMARK
 * Tujuan       : Menyegarkan data contoh secara keseluruhan: semua tabel dikosongkan (dibuang lalu dibuat ulang lewat migrasi) dan diisi ulang oleh seeder, supaya tanggal contoh
 *                (kedaluwarsa, riwayat bulan lalu) kembali segar dan mengikuti perubahan program. Data wilayah (geo_*) dipertahankan karena salinan dari tmerp butuh akun admin MySQL.
 * Sumber hulu  : config sstock.sample_refresh (penjaga), DatabaseSeeder, storage/app/sample-refresh.json (catatan penyegaran terakhir, di luar database).
 * Output       : database terisi ulang; catatan waktu + pelaku.
 * Dipakai oleh : SampleDataController. Setara CLI: composer sample.
 * Status       : Aktif.
 */
final class SampleData
{
    // Tabel yang dihitung di layar (nama tabel => label).
    public const COUNTED = ['m_item' => 'Barang', 'i_batch' => 'Batch', 'rm_stock' => 'Dokumen stok', 'rm_stockcard' => 'Pergerakan stok', 'users' => 'Pengguna'];

    public static function enabled(): bool
    {
        return (bool) config('sstock.sample_refresh');
    }

    // Super admin terdaftar (SSTOCK_SUPER_ADMIN) = hanya dia yang boleh, di lingkungan mana pun; belum terdaftar = sakelar sample_refresh seperti semula.
    public static function allowed(?string $username): bool
    {
        return ($super = User::superAdminUsername()) !== null ? $username === $super : self::enabled();
    }

    public static function counts(): array
    {
        return array_map(fn ($t) => ! Schema::hasTable($t) ? 0 : ($t === 'users' ? User::visible()->count() : DB::table($t)->count()), array_keys(self::COUNTED));
    }

    /** @return array{at: string, by: string}|null */
    public static function last(): ?array
    {
        $file = self::file();

        return is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    }

    /** $force = dipanggil perintah adminku:reset-demo (penjaganya config sstock.demo di perintah itu, bukan akun). */
    public static function refresh(string $by, bool $force = false): void
    {
        abort_unless($force || self::allowed($by), 403, 'Penyegaran data contoh dimatikan di lingkungan ini.');
        set_time_limit(0);
        ignore_user_abort(true);

        if (config('sstock.demo')) {
            self::wipeFiles();   // berkas unggahan demo (foto, KTP, lampiran, logo, log aktivitas) ikut kembali ke awal
        }

        Schema::disableForeignKeyConstraints();
        foreach (array_column(Schema::getTables(), 'name') as $table) {
            if (! str_starts_with($table, 'geo_')) {
                Schema::drop($table);
            }
        }
        Schema::enableForeignKeyConstraints();

        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--force' => true]);

        file_put_contents(self::file(), json_encode(['at' => now()->format('Y-m-d H:i:s'), 'by' => $by]));
    }

    // Ringkasan isi data contoh untuk layar (dihitung dari seeder, bukan angka tetap).
    public static function summary(): array
    {
        return [
            config('sstock.demo') ? 'Akun demo: admin, spv, adm (sandi di docs/PEMISAHAN-ADMINKU-JAYS.md); perusahaan fiktif PT Contoh Sejahtera' : 'Akun uji: admin, spv, adm (kata sandi bawaan seeder)',
            'Kategori, lokasi penyimpanan, satuan, departemen, penerima, dan pemasok',
            (count(ItemSeeder::ITEMS) + count(array_unique(array_column(ItemSeeder::EXPIRING, 0)))).' barang, termasuk '.count(array_unique(array_column(ItemSeeder::EXPIRING, 0))).' barang berkedaluwarsa dengan '.count(ItemSeeder::EXPIRING).' batch (sudah lewat, hampir, dan masih lama; tanggalnya dihitung dari hari ini)',
            'GA Perawatan Kendaraan: 4 kendaraan fiktif (1 mobil penumpang, 3 small truck) dengan jenis dan rencana perawatan, dokumen, servis, dan odometer bertanggal relatif; status Aman, Segera, dan Terlambat',
            'GA Perawatan AC: 1 vendor, 4 area, 7 ruang dengan PIC, 9 unit AC fiktif (status Aman, Segera, dan Terlambat; tanggal cuci relatif terhadap hari ini) dan satu kunjungan Draf berisi 2 AC',
            'HR: 2 karyawan contoh (David, Detta) dan 2 kandidat (Maya Denada, Dedi Sandiaga) dengan foto dan KTP tiruan',
            'Purchase: satu rantai PP, PO, dan PV contoh (stok bertambah otomatis) serta satu PP menunggu persetujuan',
            'Riwayat transaksi: saldo awal bulan lalu, penerimaan, pengeluaran, dan opname, bertanggal relatif terhadap hari ini',
        ];
    }

    private static function wipeFiles(): void
    {
        $ga = Storage::disk('ga');
        foreach ($ga->directories() as $dir) {
            $ga->deleteDirectory($dir);
        }
        foreach ($ga->files() as $file) {
            $ga->delete($file);
        }
        File::deleteDirectory(Company::dir());
        File::deleteDirectory(config('sstock.activity_dir'));
    }

    private static function file(): string
    {
        return config('sstock.sample_log');
    }
}
