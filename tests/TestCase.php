<?php

namespace Tests;

use App\Models\Item;
use App\Models\Product;
use App\Support\Constants;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    // Tes tidak boleh bergantung pada `npm run build` (public/build tidak ikut Git): Vite dimatikan agar penguji baru tidak melihat puluhan tes merah.
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Constants::forget();   // cache pengaturan statis tidak boleh bocor antar tes
        // Log aktivitas ditulis oleh setiap permintaan yang login: tes tidak boleh mengisi log asli (storage/app/activity).
        config(['sstock.activity_dir' => sys_get_temp_dir().'/sstock-activity-'.getmypid(), 'sstock.super_admin' => ['username' => null, 'hash' => null]]);   // super admin di .env pengembang tidak boleh mengubah hasil tes
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(config('sstock.activity_dir'));
        parent::tearDown();
    }

    // Migrasi hak akses lama menyebut user_group_modules; selama dijalankan, sys_user_group_module dinamai sementara itu.
    protected function runLegacyGroupMigration(string $file): void
    {
        Schema::rename('sys_user_group_module', 'user_group_modules');
        try {
            (require database_path('migrations/'.$file))->up();
        } finally {
            Schema::rename('user_group_modules', 'sys_user_group_module');
        }
    }

    // Barang uji: produk baru (kode tetap) beserta batch tunggalnya; mengembalikan batch (Item) yang dipakai dokumen.
    protected function makeItem(array $attrs): Item
    {
        return Product::register(Arr::except($attrs, ['code', 'expiry_date']), $attrs['code'] ?? null)->batchFor($attrs['expiry_date'] ?? null);
    }
}
