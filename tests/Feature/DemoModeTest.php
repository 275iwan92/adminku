<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\SampleData;
use App\Support\Company;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga produk demo AdminKU: lencana DEMO dan teks layar masuk hanya bila SSTOCK_DEMO, akun demo, perusahaan fiktif dari data, rantai Purchase contoh, reset berulang menghasilkan keadaan yang sama, dan perintah adminku:reset-demo menolak di aplikasi nyata.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class DemoModeTest extends TestCase
{
    use DatabaseMigrations;  // reset membuang tabel, jadi tidak bisa memakai transaksi RefreshDatabase

    protected function setUp(): void
    {
        parent::setUp();
        // Reset menghapus berkas demo di storage; arahkan ke folder sementara supaya storage asli tidak tersentuh.
        $this->app->useStoragePath(sys_get_temp_dir().'/adminku-test-storage');
        config(['sstock.activity_dir' => storage_path('app/activity'), 'sstock.sample_log' => storage_path('app/sample-refresh.json')]);
        @mkdir(storage_path('app'), 0777, true);
    }

    public function test_penanda_demo_hanya_muncul_bila_demo(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee('adminku-demo-badge')->assertDontSee('Data demo, bisa direset');

        config(['sstock.demo' => true]);
        $this->get(route('login'))->assertOk()->assertSee('adminku-demo-badge', false)->assertSee('Data demo, bisa direset')->assertSee('(DEMO)');
    }

    public function test_perintah_reset_menolak_di_aplikasi_nyata(): void
    {
        $this->artisan('adminku:reset-demo', ['--force' => true])->assertFailed();
    }

    public function test_seed_demo_lengkap_dan_reset_berulang_sama(): void
    {
        config(['sstock.demo' => true]);
        URL::forceRootUrl('http://localhost/adminku');   // seperti hosting dengan APP_URL berawalan
        $this->seed(DatabaseSeeder::class);

        $this->assertSame('PT Contoh Sejahtera', Company::get()['name']);
        $this->assertTrue(User::where('username', 'admin')->first()->is_active);
        $this->assertSame(UserSeeder::DEMO_PASSWORD, UserSeeder::DEMO_ACCOUNTS[0]['password']);
        $this->assertSame('confirmed', PurchaseReceipt::query()->firstOrFail()->status);
        $this->assertSame('done', PurchaseOrder::query()->firstOrFail()->status);
        $this->assertSame(['done', 'approval'], PurchaseRequest::orderBy('id')->pluck('status')->all());
        $this->assertDatabaseHas('m_employee', ['code' => 'E-C001']);

        $first = SampleData::counts();
        $this->artisan('adminku:reset-demo', ['--force' => true])->assertSuccessful();
        $this->assertSame($first, SampleData::counts());
        $this->assertSame(1, PurchaseReceipt::count());
    }
}
