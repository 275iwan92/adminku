<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\SampleData;
use App\Support\Company;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoPurchaseHistorySeeder;
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
        $this->assertSame(['adm' => '12345677', 'spv' => '12345678', 'admin' => '123456788'], collect(UserSeeder::DEMO_ACCOUNTS)->pluck('password', 'username')->all());
        $this->assertSame('confirmed', PurchaseReceipt::query()->orderBy('id')->firstOrFail()->status);
        $this->assertSame('done', PurchaseOrder::query()->orderBy('id')->firstOrFail()->status);
        $this->assertSame(['done', 'approval', 'approved', 'partial'], PurchaseRequest::orderBy('id')->pluck('status')->all());   // dua PP rantai dasar + dua PP riwayat (menunggu keputusan, sebagian dipesan)
        $this->assertDatabaseHas('m_employee', ['code' => 'E-C001']);

        // Riwayat beli: tiap barang contoh dibeli dari 3 pemasok; Keputusan Pesanan menunjukkan terakhir, harga terakhir, dan jumlah kali per pemasok.
        $tinta = Item::where('code', 'DMO-0001')->firstOrFail();
        $filter = Item::where('code', 'DMO-0002')->firstOrFail();
        $h = PurchaseOrder::history($tinta->id);
        $this->assertSame(['PT Karya Teknik Nusantara', 'CV Sinar Mandiri Abadi', 'Toko Maju Bersama'], $h->pluck('supplier')->all());
        $this->assertSame([2, 2, 1], $h->pluck('times')->all());
        $this->assertSame([87500.0, 90500.0, 96000.0], $h->pluck('last_price')->all());
        $h = PurchaseOrder::history($filter->id);
        $this->assertSame([2, 2, 1], $h->pluck('times')->all());
        $this->assertSame(164000.0, $h->first()->last_price);   // termurah dan terbaru: Toko Maju Bersama
        $this->assertTrue($h->first()->last_at > now()->subDays(30));
        $this->assertSame(['done', 'done', 'done', 'done', 'done', 'done', 'done', 'done', 'done', 'done', 'done', 'approval'], PurchaseOrder::orderBy('id')->pluck('status')->all());   // 1 PO dasar + 10 riwayat selesai, lalu PO dari PP B (Diajukan)
        $this->assertSame(1, PurchaseOrder::where('status', 'approval')->first()->lines()->whereNotNull('request_line_id')->count() >= 2 ? 1 : 0);   // tampil di kolom Dari PP
        $this->assertDatabaseHas('m_supplier', ['code' => 'DMO-S3', 'payment_term_days' => 0]);

        $first = SampleData::counts();
        $this->artisan('adminku:reset-demo', ['--force' => true])->assertSuccessful();
        $this->assertSame($first, SampleData::counts());
        $this->assertSame(1 + count(DemoPurchaseHistorySeeder::PURCHASES), PurchaseReceipt::count());
    }
}
