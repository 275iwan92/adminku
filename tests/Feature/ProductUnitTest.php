<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\StockDocument;
use App\Models\StockDocumentLine;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga satuan masuk per barang: konversi benar (5 dus x 12 = 60), faktor milik barang didahulukan dari konversi global, pilihan satuan hanya yang sah untuk barang itu,
 *                jendela "faktor belum ada" membuat faktor milik BARANG (bukan global), satuan stok tidak bisa diganti selama barang punya satuan masuk.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class ProductUnitTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $code = 'PU-1'): Item
    {
        Unit::firstOrCreate(['code' => 'dus'], ['name' => 'Dus']);
        Unit::firstOrCreate(['code' => 'lusin'], ['name' => 'Lusin']);

        return Item::register(['name' => 'Pensil '.$code, 'category_id' => Category::firstOrCreate(['code' => 'ATK'], ['name' => 'ATK'])->id, 'unit' => 'pcs', 'unit_price' => 100, 'reorder_point' => 1, 'reorder_qty' => 5], $code);
    }

    private function pack(Item $p, string $unit, float $factor): ItemUnit
    {
        return ItemUnit::create(['item_id' => $p->id, 'unit' => $unit, 'factor' => $factor]);
    }

    private function receive(Item $p, float $qty, string $unit, float $cost = 1200)
    {
        $supplier = Supplier::firstOrCreate(['code' => 'S1'], ['name' => 'S One']);

        return $this->post(route('docs.store', 'receiving'), ['supplier_id' => $supplier->id, 'lines' => [['item_id' => $p->id, 'qty' => $qty, 'unit' => $unit, 'unit_cost' => $cost]]]);
    }

    public function test_five_dus_of_twelve_become_sixty_pcs_in_stock(): void
    {
        $this->actingAs(User::factory()->create());
        $p = $this->product();
        $this->pack($p, 'dus', 12);

        $this->receive($p, 5, 'dus')->assertRedirect();
        $doc = StockDocument::latest('id')->firstOrFail();
        $this->put(route('docs.update', ['receiving', $doc]), ['supplier_id' => $doc->supplier_id, 'lines' => [['item_id' => $p->id, 'qty' => 5, 'unit' => 'dus', 'unit_cost' => 1200]], '_action' => 'confirm'])->assertRedirect();

        $line = StockDocumentLine::firstOrFail();
        $this->assertSame([60.0, 100.0], [$line->qty, $line->unit_cost]);   // 1200 per dus = 100 per pcs
        $this->assertSame([5.0, 'dus', 1200.0], [$line->entered_qty, $line->entered_unit, $line->entered_unit_cost]);
        $this->assertSame(60.0, $p->batches()->firstOrFail()->stock_qty);
    }

    public function test_the_product_factor_wins_over_the_global_pair_and_the_global_pair_is_the_fallback(): void
    {
        $this->actingAs(User::factory()->create());
        $a = $this->product('PU-A');
        $b = $this->product('PU-B');
        UnitConversion::create(['from_unit' => 'dus', 'to_unit' => 'pcs', 'factor' => 10]);
        $this->pack($a, 'dus', 12);

        $this->receive($a, 1, 'dus')->assertRedirect();
        $this->receive($b, 1, 'dus')->assertRedirect();   // B belum punya faktor sendiri: pakai pasangan global

        $this->assertSame([12.0, 10.0], StockDocumentLine::orderBy('id')->pluck('qty')->all());
    }

    public function test_row_options_list_only_the_stock_unit_and_that_products_own_units(): void
    {
        $this->actingAs(User::factory()->create());
        $a = $this->product('PU-A');
        $b = $this->product('PU-B');
        $this->pack($a, 'dus', 12);
        $this->pack($b, 'lusin', 12);
        UnitConversion::create(['from_unit' => 'kg', 'to_unit' => 'pcs', 'factor' => 4]);

        $rows = collect($this->getJson(route('devlib2.lookup', 'product_in').'?q=Pensil')->assertOk()->json())->keyBy('code');
        $packs = fn (string $code) => json_decode($rows[$code]['packs'], true);

        $this->assertSame(['stock' => 'pcs', 'own' => ['dus' => '12.000000'], 'global' => ['kg' => '4.0000000000']], $packs('PU-A'));
        $this->assertSame(['lusin' => '12.000000'], $packs('PU-B')['own']);   // dus milik A tidak ditawarkan ke B
        $this->assertArrayNotHasKey('packs', collect($this->getJson(route('devlib2.lookup', 'product_opn').'?q=Pensil')->json())->first());   // Saldo Awal selalu satuan stok
    }

    public function test_receiving_form_carries_the_options_of_each_saved_line(): void
    {
        $this->actingAs(User::factory()->create());
        $p = $this->product();
        $this->pack($p, 'dus', 12);
        $this->receive($p, 5, 'dus')->assertRedirect();

        $html = str_replace('\\', '', $this->get(route('docs.show', ['receiving', StockDocument::latest('id')->firstOrFail()]))->assertOk()->getContent());
        $this->assertStringContainsString('&quot;stock_unit&quot;:&quot;pcs&quot;', $html);
        $this->assertStringContainsString('{&quot;stock&quot;:&quot;pcs&quot;,&quot;own&quot;:{&quot;dus&quot;:&quot;12.000000&quot;}', $html);
        $this->assertStringContainsString('lines.cellRaw', $html);   // skrip pemilih satuan per baris terpasang
    }

    public function test_a_unit_without_any_factor_asks_for_it_and_the_popup_creates_the_product_factor_not_a_global_one(): void
    {
        $entry = User::factory()->create();
        $this->actingAs($entry);
        $p = $this->product();

        $this->receive($p, 2, 'dus')->assertSessionHasErrors('lines')->assertSessionHas('missing_conversions', [['item_id' => $p->id, 'from' => 'dus', 'to' => 'pcs', 'items' => 'PU-1']]);
        $this->assertSame(0, StockDocumentLine::count());

        $this->postJson(route('docs.conversion', 'receiving'), ['item_id' => $p->id, 'unit' => 'dus', 'factor' => 12])->assertOk();
        $this->assertSame(0, UnitConversion::count());   // bukan konversi global
        $unit = ItemUnit::firstOrFail();
        $this->assertSame([$p->id, 'dus', '12.000000', $entry->id, $entry->id], [$unit->item_id, $unit->unit, $unit->factor, $unit->created_by, $unit->updated_by]);

        $this->receive($p, 2, 'dus')->assertSessionHasNoErrors();
        $this->assertSame(24.0, StockDocumentLine::firstOrFail()->qty);
        $this->assertNull(ItemUnit::query()->where('item_id', $this->product('PU-2')->id)->first());   // barang lain tidak ikut
    }

    public function test_the_popup_rejects_the_stock_unit_a_zero_factor_and_issuance(): void
    {
        $this->actingAs(User::factory()->create());
        $p = $this->product();

        $this->postJson(route('docs.conversion', 'receiving'), ['item_id' => $p->id, 'unit' => 'pcs', 'factor' => 12])->assertStatus(422);
        $this->postJson(route('docs.conversion', 'receiving'), ['item_id' => $p->id, 'unit' => 'dus', 'factor' => 0])->assertStatus(422);
        $this->postJson(route('docs.conversion', 'issuance'), ['item_id' => $p->id, 'unit' => 'dus', 'factor' => 12])->assertNotFound();
        $this->assertSame(0, ItemUnit::count());
    }

    public function test_the_stock_unit_cannot_change_while_the_product_has_pack_units(): void
    {
        $sup = User::factory()->supervisor()->create();
        $this->actingAs($sup);
        $p = $this->product();
        $payload = ['_action' => 'save', 'name' => $p->name, 'category_id' => $p->category_id, 'unit' => 'dus', 'unit_price' => 1, 'reorder_point' => 1, 'reorder_qty' => 1, 'lead_time_days' => 1];

        $this->put(route('products.update', $p), $payload)->assertRedirect();   // belum punya satuan masuk: boleh
        $this->assertSame('dus', $p->fresh()->unit);

        $this->pack($p->fresh(), 'lusin', 12);
        $this->put(route('products.update', $p), ['unit' => 'pcs'] + $payload)->assertSessionHasErrors('unit');
        $this->assertSame('dus', $p->fresh()->unit);
    }
}
