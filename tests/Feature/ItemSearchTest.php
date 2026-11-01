<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga pencarian barang yang toleran di lookup baris dokumen dan Cari Barang HP: beberapa kata urut bebas (AND), kode tanpa strip / spasi / nol di depan, % dan _ dicari apa adanya.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class ItemSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $cat = Category::create(['code' => 'ATK', 'name' => 'ATK']);
        $spr = Category::create(['code' => 'SPR', 'name' => 'Sparepart']);
        foreach ([['Pulpen Hitam 0.5', $cat], ['Pensil 2B', $cat], ['Mata Bor HSS 6 mm', $spr], ['Diskon 100%', $cat]] as [$name, $c]) {
            Item::register(['name' => $name, 'category_id' => $c->id, 'unit' => 'pcs', 'location' => 'Rak A1', 'unit_price' => 1]);
        }
    }

    private function lookup(string $name, string $q): array
    {
        return collect($this->actingAs($this->user)->getJson(route('devlib.lookup', $name).'?q='.urlencode($q))->assertOk()->json())->pluck('name')->all();
    }

    public function test_several_words_match_in_any_order(): void
    {
        $this->assertSame(['Pulpen Hitam 0.5'], $this->lookup('product_in', 'hitam pulpen'));
        $this->assertSame(['Pulpen Hitam 0.5'], $this->lookup('item_adj', 'hitam pulpen'));
        $this->assertSame([], $this->lookup('product_in', 'hitam pensil'));
    }

    public function test_a_code_matches_without_dash_or_leading_zeros(): void
    {
        $this->assertSame(['Pulpen Hitam 0.5'], $this->lookup('product_in', 'atk0001'));
        $this->assertSame(['Pulpen Hitam 0.5'], $this->lookup('product_in', 'atk-1'));
        $this->assertSame(['Pulpen Hitam 0.5'], $this->lookup('item_adj', 'atk1'));
        $this->assertSame(['Pensil 2B'], $this->lookup('product_in', 'atk 2'));
        $this->assertSame(['Mata Bor HSS 6 mm'], $this->lookup('product_in', 'spr0001'));
    }

    public function test_a_size_written_without_the_space_still_matches(): void
    {
        $this->assertSame(['Mata Bor HSS 6 mm'], $this->lookup('product_in', 'bor 6mm'));
    }

    public function test_the_location_is_searched_too(): void
    {
        $this->assertCount(4, $this->lookup('item_adj', 'rak a1'));
    }

    public function test_percent_and_underscore_are_searched_literally(): void
    {
        $this->assertSame(['Diskon 100%'], $this->lookup('product_in', '100%'));
        $this->assertSame([], $this->lookup('product_in', '%%'));
        $this->assertSame([], $this->lookup('product_in', 'pu_pen'));
    }

    public function test_phone_search_matches_several_words_in_any_order_and_a_literal_percent(): void
    {
        $this->actingAs($this->user)->get(route('hp.find', ['q' => 'hitam pulpen']))->assertOk()->assertSee('Pulpen Hitam 0.5')->assertDontSee('Pensil 2B');
        $this->actingAs($this->user)->get(route('hp.find', ['q' => 'atk 2']))->assertOk()->assertSee('Pensil 2B')->assertDontSee('Pulpen Hitam');
        $this->actingAs($this->user)->get(route('hp.find', ['q' => '%']))->assertOk()->assertSee('Diskon 100%')->assertDontSee('Pensil 2B');
    }
}
