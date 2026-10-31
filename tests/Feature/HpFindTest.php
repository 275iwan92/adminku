<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga layar Cari Barang tampilan HP: akses modul ITM di server, cari menurut kode produk / kode batch / nama / kode lokasi, kode lengkap langsung membuka barang, kartu hasil (level, stok tersedia, lokasi), stok per batch berurutan FEFO dengan lencana kedaluwarsa, kode tidak ketemu, nilai uang hanya Supervisor, pintu masuk di Beranda HP dan menu.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class HpFindTest extends TestCase
{
    use RefreshDatabase;

    private Product $pensil;

    private Product $susu;

    protected function setUp(): void
    {
        parent::setUp();
        $cat = Category::create(['code' => 'ATK', 'name' => 'ATK']);
        $this->pensil = Product::register(['name' => 'Pensil 2B', 'category_id' => $cat->id, 'unit' => 'pcs', 'location' => 'RAK-A1', 'unit_price' => 100, 'reorder_point' => 50]);
        $this->pensil->batches()->first()->forceFill(['stock_qty' => 40, 'avg_cost' => 100])->save();
        // Susu: tiga batch, sengaja dibuat TIDAK berurutan; satu sudah kedaluwarsa, satu hampir, satu aman.
        $this->susu = Product::register(['name' => 'Susu UHT', 'category_id' => $cat->id, 'unit' => 'liter', 'location' => 'KULKAS-1', 'unit_price' => 10, 'reorder_point' => 5, 'tracks_expiry' => true]);
        foreach ([[today()->addDays(200), 7], [today()->subDays(3), 2], [today()->addDays(10), 4]] as [$date, $qty]) {
            $this->susu->batchFor($date)->forceFill(['stock_qty' => $qty, 'avg_cost' => 10])->save();
        }
    }

    public function test_search_by_name_shows_a_card_with_level_available_stock_and_location(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('hp.find', ['q' => 'pens']))->assertOk()
            ->assertSee('Pensil 2B')->assertSee($this->pensil->code)->assertSee('RAK-A1')->assertSee('PESAN ULANG')->assertSee('40,00 pcs')
            ->assertSee(route('hp.find.show', $this->pensil), false)->assertDontSee('Susu UHT');
        // stok tersedia tidak menghitung batch yang sudah kedaluwarsa (7 + 4 = 11); level AMAN karena di atas titik pesan ulang 5
        $this->actingAs($user)->get(route('hp.find', ['q' => 'susu']))->assertSee('11,00 liter')->assertSee('AMAN');
    }

    public function test_search_by_location_code_and_by_part_of_a_batch_code(): void
    {
        $user = User::factory()->create();
        $batch = $this->susu->batches()->whereDate('expiry_date', today()->addDays(10))->first();

        $this->actingAs($user)->get(route('hp.find', ['q' => 'kulkas']))->assertSee('Susu UHT')->assertDontSee('Pensil 2B');
        $this->actingAs($user)->get(route('hp.find', ['q' => substr($batch->code, -6)]))->assertSee('Susu UHT');
    }

    public function test_a_full_product_or_batch_code_goes_straight_to_the_item(): void
    {
        $user = User::factory()->create();
        $batch = $this->susu->batches()->whereDate('expiry_date', today()->addDays(10))->first();

        $this->actingAs($user)->get(route('hp.find', ['q' => '  '.strtolower($this->pensil->code).' ']))->assertRedirect(route('hp.find.show', $this->pensil));
        $this->actingAs($user)->get(route('hp.find', ['q' => $batch->code]))->assertRedirect(route('hp.find.show', ['product' => $this->susu, 'b' => $batch->code]));
    }

    public function test_the_item_page_lists_batches_in_fefo_order_with_expiry_badges(): void
    {
        $user = User::factory()->create();
        $codes = $this->susu->batches()->orderBy('expiry_date')->pluck('code')->all();   // kedaluwarsa -3 hari, +10, +200

        $page = $this->actingAs($user)->get(route('hp.find.show', $this->susu))->assertOk()
            ->assertSeeInOrder($codes)->assertSee('KEDALUWARSA')->assertSee('HAMPIR')->assertSee('lewat 3 hari')->assertSee('sisa 10 hari');
        $this->assertSame(1, substr_count($page->getContent(), 'KEDALUWARSA'));
        $this->assertSame(1, substr_count($page->getContent(), 'HAMPIR'));
    }

    public function test_batch_without_expiry_comes_last_and_a_scanned_batch_is_marked(): void
    {
        $user = User::factory()->create();
        $none = $this->susu->batchFor(null);
        $none->forceFill(['stock_qty' => 3])->save();

        $html = $this->actingAs($user)->get(route('hp.find.show', ['product' => $this->susu, 'b' => $none->code]))->assertOk()->assertSee('Tanpa kedaluwarsa')->getContent();
        $this->assertGreaterThan(strpos($html, 'sisa 200 hari'), strpos($html, 'Tanpa kedaluwarsa'));
        $this->assertSame(1, substr_count($html, 'data-scanned'));
    }

    public function test_unknown_code_says_nothing_matches_and_empty_query_shows_the_hint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('hp.find', ['q' => 'TIDAK-ADA-9999']))->assertOk()->assertSee('Tidak ada barang yang cocok')->assertDontSee('Pensil 2B');
        $this->actingAs($user)->get(route('hp.find'))->assertOk()->assertSee('Ketik atau pindai')->assertSee('autofocus', false)->assertSee('type="text"', false);
    }

    public function test_inactive_products_are_not_found(): void
    {
        $user = User::factory()->create();
        $this->pensil->update(['is_active' => false]);

        $this->actingAs($user)->get(route('hp.find', ['q' => $this->pensil->code]))->assertSee('Tidak ada barang yang cocok');
        $this->actingAs($user)->get(route('hp.find.show', $this->pensil))->assertNotFound();
    }

    public function test_money_is_for_supervisor_only(): void
    {
        $this->actingAs(User::factory()->create())->get(route('hp.find.show', $this->pensil))->assertOk()->assertDontSee('Nilai stok');
        $this->actingAs(User::factory()->supervisor()->create())->get(route('hp.find.show', $this->pensil))->assertSee('Nilai stok IDR 4.000');
    }

    public function test_the_itm_module_guards_both_screens_and_the_entry_points_hide(): void
    {
        $group = UserGroup::create(['code' => 'NOITM', 'name' => 'Tanpa barang']);
        $group->syncAccess(['RCV' => 'rw']);
        $user = User::factory()->create(['group_id' => $group->id]);

        $this->actingAs($user)->get(route('hp.find'))->assertForbidden();
        $this->actingAs($user)->get(route('hp.find.show', $this->pensil))->assertForbidden();
        $html = $this->actingAs($user)->withCookie('ui', 'hp')->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString(route('hp.find'), $html);
    }

    public function test_phone_home_and_menu_link_to_find(): void
    {
        $html = $this->actingAs(User::factory()->create())->withCookie('ui', 'hp')->get('/')->assertOk()->assertSee('Cari Barang')->getContent();
        $this->assertGreaterThanOrEqual(2, substr_count($html, route('hp.find')), 'tautan di Beranda dan di menu');
    }
}
