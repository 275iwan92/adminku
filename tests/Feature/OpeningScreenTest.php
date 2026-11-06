<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use App\Support\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga layar pembuka kelompok menu / modul (induk menu diklik): Data Master, Transaksi, Laporan, GA, HR, Purchase. Hanya yang berhak (403 untuk yang tidak), ubin menurut hak dengan lencana Tulis / Baca,
 *                HR berisi Karyawan dan Bagian nyata (dengan angka), Rekrutmen dan Kedisiplinan masih "segera"; Purchase hanya "Segera hadir" tanpa angka, Stok membuka Beranda Stok, butir Beranda Stok tidak ada di menu, dan daftar kelompok HP diganti layar pembuka.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class OpeningScreenTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = 'Mozilla/5.0 (Linux; Android 15; CPH2700) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36';

    private function group(array $access): User
    {
        $group = UserGroup::create(['code' => 'G'.random_int(100, 999), 'name' => 'Grup uji']);
        $group->syncAccess($access);

        return User::factory()->create(['group_id' => $group->id]);
    }

    private function tiles(User $user, string $section): array
    {
        return array_column($this->actingAs($user)->get(route('opening', $section))->assertOk()->viewData('page')['tiles'], 'label');
    }

    public function test_administrator_opens_every_section_on_desktop_and_phone(): void
    {
        $admin = User::factory()->administrator()->create();

        foreach (['master', 'transaksi', 'laporan', 'ga', 'hr', 'purchase'] as $section) {
            $this->actingAs($admin)->get(route('opening', $section))->assertOk()->assertSee('sstock-ohead', false);
            $this->actingAs($admin)->withHeader('User-Agent', self::PHONE)->get(route('opening', $section))->assertOk()->assertSee('sstock-otile', false);
        }
        $this->actingAs($admin)->get(route('opening', 'nope'))->assertNotFound();
    }

    public function test_tiles_follow_the_rights_and_show_write_or_read(): void
    {
        $entry = User::factory()->create();
        $this->assertSame(['Barang', 'Pemasok', 'Departemen', 'Penerima'], $this->tiles($entry, 'master'));   // Satuan, Kategori, Lokasi, Perusahaan hanya Supervisor
        $this->assertContains('Satuan', $this->tiles(User::factory()->supervisor()->create(), 'master'));
        $this->assertNotContains('Saldo Awal', $this->tiles($entry, 'transaksi'));
        $this->assertNotContains('Beranda Stok', $this->tiles($entry, 'laporan'));

        $ro = $this->group(['ITM' => 'ro', 'SUP' => 'rw']);
        $html = $this->actingAs($ro)->get(route('opening', 'master'))->assertOk()->getContent();
        $this->assertStringContainsString('Hak Anda: Tulis', $html);
        $this->assertSame(1, preg_match('/Barang<em class="">Baca<\/em>/', $html));
        $this->assertSame(1, preg_match('/Pemasok<em class="is-w">Tulis<\/em>/', $html));
    }

    public function test_sections_without_any_allowed_item_are_forbidden(): void
    {
        $entry = User::factory()->create();   // tanpa Grup: tidak ada modul GA, HR dan Purchase hanya Administrator
        foreach (['ga', 'hr', 'purchase'] as $section) {
            $this->actingAs($entry)->get(route('opening', $section))->assertForbidden();
        }
        $this->actingAs(User::factory()->supervisor()->create())->get(route('opening', 'hr'))->assertForbidden();
    }

    public function test_hr_has_real_tiles_with_numbers_and_purchase_is_coming_soon(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->assertSame(['Karyawan', 'Bagian', 'Rekrutmen', 'Kedisiplinan'], $this->tiles($admin, 'hr'));
        $this->assertSame(['Permintaan Pembelian', 'Keputusan Pesanan', 'Pesanan Pembelian (PO)', 'Penerimaan Pembelian (PV)'], $this->tiles($admin, 'purchase'));

        $page = $this->actingAs($admin)->get(route('opening', 'hr'))->assertOk()->assertDontSee('Hak Anda: Baca')->viewData('page');
        $this->assertFalse($page['soon']);
        $this->assertSame(['0', '0', '0', '0'], array_column($page['tiles'], 'n'));
        $this->assertSame([false, false, false, false], array_column($page['tiles'], 'soon'));

        $page = $this->get(route('opening', 'purchase'))->assertOk()->viewData('page');
        $this->assertFalse($page['soon']);
        $this->assertSame([], $page['stats']);
        $this->assertSame(['0', '0', '0', '0'], array_column($page['tiles'], 'n'));   // PP, Keputusan Pesanan, PO, PV nyata
        $this->assertSame([false, false, false, false], array_column($page['tiles'], 'soon'));
    }

    public function test_ga_summary_shows_only_the_panels_of_the_user(): void
    {
        $fleet = $this->group(['GVM' => 'ro']);
        $page = $this->actingAs($fleet)->get(route('opening', 'ga'))->assertOk()->viewData('page');
        $this->assertSame(['Kendaraan aktif', 'Kendaraan perlu tindakan'], array_column($page['stats'], 'label'));
        $this->assertSame(['Perawatan Kendaraan'], array_column($page['tiles'], 'label'));

        $driver = $this->group(['GVO' => 'rw']);
        $page = $this->actingAs($driver)->get(route('opening', 'ga'))->assertOk()->viewData('page');
        $this->assertSame([], $page['stats']);   // sopir: tidak ada angka palsu
        $this->assertSame(['Catat Odometer'], array_column($page['tiles'], 'label'));
        $this->assertSame(['Catat Odometer'], array_column($page['shortcuts'], 'label'));

        $admin = User::factory()->administrator()->create();
        $labels = array_column($this->actingAs($admin)->get(route('opening', 'ga'))->viewData('page')['stats'], 'label');
        $this->assertSame(['Kendaraan aktif', 'Kendaraan perlu tindakan', 'AC terlambat', 'AC terjadwal minggu ini', 'Aset perlu perawatan', 'Tiket perawatan terbuka'], $labels);
    }

    public function test_master_counts_active_and_inactive(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $tiles = collect($this->actingAs(User::where('username', 'admin')->first() ?? User::factory()->administrator()->create())->get(route('opening', 'master'))->viewData('page')['tiles'])->keyBy('code');

        $this->assertSame(\App\Models\Item::where('is_active', true)->count(), (int) $tiles['ITM']['n']);
        $this->assertNotSame('', $tiles['CMP']['note']);   // nama perusahaan
    }

    public function test_menu_roots_open_their_screens_and_stok_opens_the_stock_home(): void
    {
        $admin = User::factory()->administrator()->create();
        $html = $this->actingAs($admin)->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString(e(route('opening', 'transaksi')), str_replace(['\\/', '&quot;'], ['/', '"'], $html));
        $layers = collect(Menu::layers($admin));
        $this->assertSame(route('stock.home'), $layers->firstWhere('code', 'STK')['open']);
        $this->assertNull(collect(Menu::layers($this->group(['RCV' => 'rw'])))->firstWhere('code', 'STK')['open']);   // tanpa hak STB: induk Stok hanya turun satu layer
        $this->assertNotContains('STB', collect(Menu::modules($admin))->flatMap(fn ($p) => collect($p['children'])->pluck('code'))->all());   // hak STB tetap ada, tetapi bukan butir menu
    }

    public function test_the_layer_title_above_the_items_is_clickable_and_opens_the_same_screen(): void
    {
        $html = $this->actingAs(User::factory()->administrator()->create())->get('/')->assertOk()->getContent();
        $this->assertStringContainsString("closest('.dl-lm__title')", $html);
        $this->assertStringContainsString('.dl-lm__title[title]', file_get_contents(resource_path('css/app.css')));
    }

    public function test_phone_group_list_is_replaced_by_the_opening_screen(): void
    {
        $entry = User::factory()->create();
        foreach (['MST' => 'master', 'TRX' => 'transaksi', 'RPT' => 'laporan', 'GA' => 'ga'] as $parent => $section) {
            $this->actingAs($entry)->withHeader('User-Agent', self::PHONE)->get(route('hp.group', $parent))->assertRedirect(route('opening', $section));
        }
    }
}
