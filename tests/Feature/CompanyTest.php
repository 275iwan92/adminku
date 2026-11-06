<?php

namespace Tests\Feature;

use App\Enums\StockDocumentType;
use App\Models\Category;
use App\Models\StockDocument;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\StockDocuments;
use App\Support\Company;
use App\Support\Constants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga modul Perusahaan (kop cetakan): hak akses (hanya Administrator), validasi, unggah logo (tipe palsu / SVG / terlalu besar / terlalu kecil ditolak, ukuran diubah), rute logo publik dengan header,
 *                kop tiga gaya pada cetak dokumen dan laporan, nama PT yang sama dengan nama tidak muncul dua kali, logo kosong jatuh ke nama saja, bawaan saat belum diisi, dan pemberian modul ke Grup.
 *                Berkas logo ditulis ke folder storage sementara, bukan storage kerja.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class CompanyTest extends TestCase
{
    use RefreshDatabase;

    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = sys_get_temp_dir().DIRECTORY_SEPARATOR.'adminku-test-'.bin2hex(random_bytes(4));
        mkdir($this->storage.DIRECTORY_SEPARATOR.'app', 0777, true);
        $this->app->useStoragePath($this->storage);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    private function sup(): User
    {
        return User::factory()->administrator()->create();
    }

    /** @param array<string, mixed> $extra */
    private function save(array $extra = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->sup())->put(route('company.update'), $extra + [
            'name' => 'Maju Jaya', 'legal_name' => 'PT Maju Jaya Abadi', 'npwp' => '01.234.567.8-901.000', 'phone' => '021-555', 'email' => 'info@maju.test',
            'address' => "Jl. Mawar 1\nJakarta", 'kop_style' => 'logo_name_address',
        ]);
    }

    private function doc(): StockDocument
    {
        $sup = $this->sup();
        $cat = Category::create(['code' => 'ATK', 'name' => 'ATK']);
        $item = $this->makeBatch(['code' => 'A1', 'name' => 'Kertas', 'category_id' => $cat->id, 'unit' => 'pcs', 'unit_price' => 1000]);
        $supplier = Supplier::create(['code' => 'S1', 'name' => 'S']);
        $docs = app(StockDocuments::class);

        return $docs->create(StockDocumentType::Receiving, ['supplier_id' => $supplier->id], [['batch_id' => $item->id, 'qty' => 2, 'unit_cost' => 1000]], $sup);
    }

    public function test_only_administrators_open_and_save_the_company_screen_and_it_is_in_the_menu(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('company.edit'))->assertForbidden();
        $this->save(as: User::factory()->create())->assertForbidden();

        $this->actingAs($this->sup())->get(route('company.edit'))->assertOk()->assertSee('Nama Perusahaan')->assertSee('Pratinjau kop')->assertSee('Logo + Nama + Alamat')->assertSee('multipart/form-data', false);
        $this->get(route('home'))->assertSee('Perusahaan');
        $this->actingAs(User::factory()->supervisor()->create())->get(route('company.edit'))->assertForbidden();
        $this->actingAs(User::factory()->administrator()->create())->get(route('company.edit'))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('home'))->assertDontSee('company.edit', false);
    }

    public function test_validation_and_saving_the_data(): void
    {
        $sup = $this->sup();
        $this->actingAs($sup)->put(route('company.update'), ['name' => '', 'kop_style' => 'rainbow', 'email' => 'bukan-email'])->assertSessionHasErrors(['name', 'kop_style', 'email']);
        $this->assertNull(Constants::get('company.name'));

        $this->save()->assertRedirect(route('company.edit'))->assertSessionHas('status');
        Constants::forget();
        $c = Company::get();
        $this->assertSame(['Maju Jaya', 'PT Maju Jaya Abadi', '01.234.567.8-901.000', 'logo_name_address'], [$c['name'], $c['legal_name'], $c['npwp'], $c['kop_style']]);
        $this->get(route('company.edit'))->assertOk()->assertSee('Maju Jaya')->assertSee('PT Maju Jaya Abadi');
    }

    public function test_defaults_before_anything_is_saved(): void
    {
        $c = Company::get();
        $this->assertSame(config('devlib.company.name'), $c['name']);
        $this->assertSame('name', $c['kop_style']);
        $this->assertNull(Company::logoUrl());
        $this->assertSame(config('devlib.company.tagline'), Company::report()['tagline']);

        $this->actingAs($this->sup())->get(route('docs.print', ['receiving', $this->doc()]))->assertOk()->assertSee(config('devlib.company.name'))->assertDontSee('dl-pdoc__brand', false);
    }

    public function test_logo_is_checked_by_content_resized_and_served_publicly_with_headers(): void
    {
        $sup = $this->sup();
        $this->save(['logo' => UploadedFile::fake()->image('anything.png', 1200, 400)], $sup)->assertSessionHasNoErrors();
        Constants::forget();

        $path = Company::logoPath();
        $this->assertNotNull($path);
        $this->assertSame(str_replace('\\', '/', storage_path('app/company/logo.png')), str_replace('\\', '/', $path));
        [$w, $h, $type] = getimagesize($path);
        $this->assertSame([600, 200, IMAGETYPE_PNG], [$w, $h, $type]);
        $this->assertSame('logo.png', Constants::get('company.logo'));

        auth()->logout();
        $res = $this->get(Company::logoUrl())->assertOk();   // tanpa login
        $this->assertStringContainsString('image/png', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('max-age', $res->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', $res->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $res->headers->get('X-Content-Type-Options'));
    }

    public function test_fake_svg_too_big_and_too_small_logos_are_rejected(): void
    {
        $sup = $this->sup();
        $this->save(['logo' => UploadedFile::fake()->createWithContent('logo.png', 'ini bukan gambar sama sekali')], $sup)->assertSessionHasErrors('logo');
        $this->save(['logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><script>alert(1)</script></svg>')], $sup)->assertSessionHasErrors('logo');
        $this->save(['logo' => UploadedFile::fake()->createWithContent('logo.png', '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"></svg>')], $sup)->assertSessionHasErrors('logo');
        $this->save(['logo' => UploadedFile::fake()->image('big.png', 200, 200)->size(2000)], $sup)->assertSessionHasErrors('logo');
        $this->save(['logo' => UploadedFile::fake()->image('tiny.png', 10, 10)], $sup)->assertSessionHasErrors('logo');
        $this->save(['logo' => UploadedFile::fake()->image('anim.gif', 100, 100)], $sup)->assertSessionHasErrors('logo');

        Constants::forget();
        $this->assertNull(Company::logoPath());
        $this->assertNull(Constants::get('company.name'));   // simpan yang gagal tidak menyimpan apa pun
        $this->get(route('company.logo'))->assertNotFound();
    }

    public function test_remove_logo_and_only_the_standard_file_name_is_used(): void
    {
        $sup = $this->sup();
        $this->save(['logo' => UploadedFile::fake()->image('../../evil name.jpg', 100, 100)], $sup)->assertSessionHasNoErrors();
        Constants::forget();
        $this->assertSame(['logo.png'], array_map('basename', glob(storage_path('app/company/*'))));

        $this->save(['remove_logo' => '1'], $sup);
        Constants::forget();
        $this->assertNull(Company::logoPath());
        $this->assertSame([], glob(storage_path('app/company/*')));
    }

    public function test_the_three_styles_on_a_document_print(): void
    {
        $sup = $this->sup();
        $doc = $this->doc();
        $url = route('docs.print', ['receiving', $doc]);
        $this->save(['logo' => UploadedFile::fake()->image('l.png', 200, 100)], $sup);

        $this->save(['kop_style' => 'logo_name_address'], $sup);
        $this->get($url)->assertOk()->assertSee('dl-pdoc__brand', false)->assertSee('Maju Jaya')->assertSee('Jl. Mawar 1')->assertSee('PT Maju Jaya Abadi')->assertSee('NPWP 01.234.567.8-901.000');

        $this->save(['kop_style' => 'logo_name'], $sup);
        $this->get($url)->assertOk()->assertSee('dl-pdoc__brand', false)->assertSee('Maju Jaya')->assertSee('PT Maju Jaya Abadi')->assertDontSee('Jl. Mawar 1');

        $this->save(['kop_style' => 'name'], $sup);
        $this->get($url)->assertOk()->assertDontSee('dl-pdoc__brand', false)->assertSee('Maju Jaya')->assertSee('PT Maju Jaya Abadi')->assertDontSee('Jl. Mawar 1');
    }

    public function test_legal_name_equal_to_the_name_is_not_printed_twice_and_empty_logo_falls_back_to_name_only(): void
    {
        $sup = $this->sup();
        $url = route('docs.print', ['receiving', $this->doc()]);

        $this->save(['legal_name' => ' maju  JAYA ', 'kop_style' => 'logo_name_address'], $sup);   // logo belum ada
        $this->get($url)->assertOk()->assertSee('Maju Jaya')->assertDontSee('dl-pdoc__legal', false)->assertDontSee('dl-pdoc__brand', false)->assertSee('Jl. Mawar 1');
        $this->assertSame('logo_name_address', Company::get()['kop_style']);   // pilihan tetap tersimpan apa adanya
    }

    public function test_reports_and_the_count_sheet_use_the_same_company_source(): void
    {
        $sup = $this->sup();
        $this->save(['logo' => UploadedFile::fake()->image('l.png', 200, 100), 'kop_style' => 'logo_name_address'], $sup);

        $docs = app(StockDocuments::class);
        $doc = $this->doc();
        $docs->confirm($doc, $sup);
        foreach (['stock-card', 'summary', 'expiry', 'receiving', 'issuance', 'adjustment'] as $report) {
            $this->get(route('reports.'.$report, ['batch_id' => $doc->lines()->value('batch_id')]))->assertOk()->assertSee('Maju Jaya')->assertSee('PT Maju Jaya Abadi')->assertSee('dl-print__brand', false)->assertSee('Jl. Mawar 1, Jakarta')->assertSee('Telp. 021-555');
        }

        $this->save(['kop_style' => 'name'], $sup);
        $this->get(route('reports.summary'))->assertOk()->assertSee('Maju Jaya')->assertDontSee('dl-print__brand', false)->assertDontSee('Jl. Mawar 1');
        $this->assertNull(Company::report()['tagline']);   // tagline bawaan hanya selama belum diisi

        $count = app(\App\Services\StockCounts::class)->start($sup, ['count_date' => today()->toDateString(), 'scope_type' => 'all', 'blind' => true, 'lock_transactions' => false, 'threshold_pct' => 5]);
        $this->get(route('stock-counts.sheet', $count))->assertOk()->assertSee('Maju Jaya')->assertSee('PT Maju Jaya Abadi');
    }

    public function test_the_application_name_stays_adminku_on_the_login_page(): void
    {
        $this->save(['name' => 'PT Lain Sekali']);
        auth()->logout();
        $this->get(route('login'))->assertOk()->assertSee('ADMINKU')->assertDontSee('PT Lain Sekali');
    }

    public function test_the_company_module_is_granted_to_supervisor_level_groups_only(): void
    {
        $sup = UserGroup::create(['code' => 'SPV', 'name' => 'Supervisor']);
        $sup->syncAccess(['ADJ' => 'rw']);
        $entry = UserGroup::create(['code' => 'DE', 'name' => 'Data Entry']);
        $entry->syncAccess(['RCV' => 'rw']);

        $this->runLegacyGroupMigration('2026_11_15_100000_give_company_to_groups.php');

        $this->assertSame('rw', $sup->fresh()->moduleAccess()['CMP'] ?? null);
        $this->assertArrayNotHasKey('CMP', $entry->fresh()->moduleAccess());
    }
}
