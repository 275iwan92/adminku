<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Batch;
use App\Models\StockDocument;
use App\Models\StockDocumentLine;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Models\User;
use App\Support\Decimal;
use App\Support\UnitConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga master Satuan: hanya Supervisor, kode tetap setelah disimpan, item hanya boleh memakai satuan aktif dari master.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class UnitTest extends TestCase
{
    use RefreshDatabase;

    private function itemPayload(array $extra = []): array
    {
        $cat = Category::firstOrCreate(['code' => 'ATK'], ['name' => 'ATK']);

        return array_merge(['code' => 'U-1', 'name' => 'Unit test item', 'category_id' => $cat->id, 'unit' => 'pcs', 'unit_price' => 100, 'reorder_point' => 1, 'reorder_qty' => 5, 'lead_time_days' => 1], $extra);
    }

    public function test_default_units_exist_after_migration(): void
    {
        $this->assertTrue(Unit::whereIn('code', ['pcs', 'box', 'set', 'kg'])->count() === 4);
    }

    public function test_supervisor_creates_and_renames_a_unit_but_the_code_stays(): void
    {
        $this->actingAs(User::factory()->supervisor()->create())->post(route('units.store'), ['code' => 'LUSIN', 'name' => 'Dozen'])->assertRedirect();
        $unit = Unit::where('code', 'lusin')->firstOrFail();  // kode dipaksa huruf kecil

        $this->put(route('units.update', $unit), ['_action' => 'save', 'code' => 'berubah', 'name' => 'Dozen (12)'])->assertRedirect();
        $this->assertSame('lusin', $unit->fresh()->code);
        $this->assertSame('Dozen (12)', $unit->fresh()->name);
    }

    public function test_code_must_be_unique_and_clean(): void
    {
        $this->actingAs(User::factory()->supervisor()->create())->post(route('units.store'), ['code' => 'pcs', 'name' => 'Duplicate'])->assertSessionHasErrors('code');
    }

    public function test_data_entry_cannot_open_the_unit_master(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('units.index'))->assertForbidden();
        $this->post(route('units.store'), ['code' => 'x', 'name' => 'X'])->assertForbidden();
    }

    public function test_item_can_use_only_active_units_from_the_master(): void
    {
        $sup = User::factory()->supervisor()->create();
        Unit::create(['code' => 'old', 'name' => 'Old unit', 'is_active' => false]);

        $this->actingAs($sup)->post(route('products.store'), $this->itemPayload(['unit' => 'ghost']))->assertSessionHasErrors('unit');
        $this->assertSame(0, Batch::count());
    }

    public function test_inactive_unit_is_rejected_for_new_items_but_kept_for_existing_ones(): void
    {
        $sup = User::factory()->supervisor()->create();
        Unit::create(['code' => 'old', 'name' => 'Old unit', 'is_active' => false]);
        $this->actingAs($sup)->post(route('products.store'), $this->itemPayload(['unit' => 'old']))->assertSessionHasErrors('unit');

        $item = $this->makeBatch(array_merge($this->itemPayload(), ['code' => 'U-2', 'unit' => 'box']));
        Unit::where('code', 'box')->update(['is_active' => false]);
        $this->put(route('products.update', $item->item), array_merge($this->itemPayload(), ['_action' => 'save', 'code' => 'U-2', 'unit' => 'box', 'name' => 'Renamed']))->assertRedirect();
        $this->assertSame('Renamed', $item->fresh()->name);
    }

    public function test_conversions_are_saved_and_replaced_with_the_form(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());
        $box = Unit::where('code', 'box')->firstOrFail();

        $this->put(route('units.update', $box), ['_action' => 'save', 'code' => 'box', 'name' => 'Box', 'conversions' => [['to_unit' => 'pcs', 'factor' => 12], ['to_unit' => 'kg', 'factor' => 1000]]])->assertRedirect();
        $this->assertSame(['kg' => 1000.0, 'pcs' => 12.0], $box->conversions()->pluck('factor', 'to_unit')->all());

        $this->put(route('units.update', $box), ['_action' => 'save', 'code' => 'box', 'name' => 'Box', 'conversions' => [['to_unit' => 'pcs', 'factor' => 10]]])->assertRedirect();
        $this->assertSame(['pcs' => 10.0], $box->conversions()->pluck('factor', 'to_unit')->all());
    }

    public function test_conversion_to_itself_and_a_zero_factor_are_rejected(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());
        $box = Unit::where('code', 'box')->firstOrFail();
        $base = ['_action' => 'save', 'code' => 'box', 'name' => 'Box'];

        $this->put(route('units.update', $box), $base + ['conversions' => [['to_unit' => 'box', 'factor' => 2]]])->assertSessionHasErrors('conversions.0.to_unit');
        $this->assertSame(0, $box->conversions()->count());
    }

    public function test_zero_factor_is_rejected(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());
        $box = Unit::where('code', 'box')->firstOrFail();

        $this->put(route('units.update', $box), ['_action' => 'save', 'code' => 'box', 'name' => 'Box', 'conversions' => [['to_unit' => 'pcs', 'factor' => 0]]])->assertSessionHasErrors('conversions.0.factor');
        $this->assertSame(0, $box->conversions()->count());
    }

    public function test_receiving_in_another_unit_is_converted_into_the_stock_unit(): void
    {
        $entry = User::factory()->create();
        $supplier = Supplier::create(['code' => 'S1', 'name' => 'S One']);
        $item = $this->makeBatch(array_merge($this->itemPayload(), ['code' => 'C-1', 'unit' => 'pcs']));
        UnitConversion::create(['from_unit' => 'box', 'to_unit' => 'pcs', 'factor' => 12]);

        $this->receiveAs($entry, ['supplier_id' => $supplier->id, 'lines' => [['batch_id' => $item->id, 'qty' => 5, 'unit' => 'box', 'unit_cost' => 1200]]]);

        $line = StockDocumentLine::firstOrFail();
        $this->assertSame(60.0, $line->qty);              // masuk stok dalam pcs
        $this->assertSame(100.0, $line->unit_cost);       // biaya per pcs
        $this->assertSame([5.0, 'box', 1200.0], [$line->entered_qty, $line->entered_unit, $line->entered_unit_cost]);
        $this->assertSame(60.0, $item->fresh()->stock_qty);
        $this->assertEqualsWithDelta(100.0, $item->fresh()->avg_cost, 0.0001);

        $this->get(route('docs.show', ['receiving', $line->document]))->assertOk()->assertSee('stock_qty', false);
        $this->actingAs(User::factory()->supervisor()->create())->get(route('reports.receiving'))->assertOk()->assertSee('5 box', false);
    }

    public function test_receiving_without_a_conversion_is_rejected(): void
    {
        $supplier = Supplier::create(['code' => 'S1', 'name' => 'S One']);
        $item = $this->makeBatch(array_merge($this->itemPayload(), ['code' => 'C-2', 'unit' => 'pcs']));

        $this->actingAs(User::factory()->create())->post(route('docs.store', 'receiving'), ['supplier_id' => $supplier->id, 'lines' => [['batch_id' => $item->id, 'qty' => 1, 'unit' => 'kg', 'unit_cost' => 10]]])->assertSessionHasErrors('lines');
        $this->assertSame(0.0, $item->fresh()->stock_qty);
        $this->assertSame(0, StockDocumentLine::count());
    }

    public function test_missing_conversion_is_flashed_for_the_popup_and_can_be_saved_by_data_entry(): void
    {
        $supplier = Supplier::create(['code' => 'S1', 'name' => 'S One']);
        $item = $this->makeBatch(array_merge($this->itemPayload(), ['code' => 'C-4', 'unit' => 'pcs']));
        $payload = ['supplier_id' => $supplier->id, 'lines' => [['batch_id' => $item->id, 'qty' => 2, 'unit' => 'box', 'unit_cost' => 1200]]];

        $this->actingAs(User::factory()->create())->post(route('docs.store', 'receiving'), $payload)
            ->assertSessionHasErrors('lines')->assertSessionHas('missing_conversions', [['item_id' => $item->item_id, 'from' => 'box', 'to' => 'pcs', 'items' => 'C-4']]);

        $this->postJson(route('docs.conversion', 'receiving'), ['item_id' => $item->item_id, 'unit' => 'box', 'factor' => 12])->assertOk();
        $this->assertSame(12.0, UnitConverter::factor('box', 'pcs', $item->item_id));
        $this->assertNull(UnitConverter::factor('box', 'pcs'));   // milik barang, bukan global
        $this->postJson(route('docs.conversion', 'receiving'), ['item_id' => $item->item_id, 'unit' => 'pcs', 'factor' => 12])->assertStatus(422);
        $this->postJson(route('docs.conversion', 'receiving'), ['item_id' => $item->item_id, 'unit' => 'box', 'factor' => 0])->assertStatus(422);

        $this->post(route('docs.store', 'receiving'), $payload)->assertSessionHasNoErrors();
        $this->assertSame(24.0, StockDocumentLine::firstOrFail()->qty);
    }

    public function test_receiving_in_the_stock_unit_needs_no_conversion(): void
    {
        $supplier = Supplier::create(['code' => 'S1', 'name' => 'S One']);
        $item = $this->makeBatch(array_merge($this->itemPayload(), ['code' => 'C-3', 'unit' => 'pcs']));

        $this->receiveAs(User::factory()->create(), ['supplier_id' => $supplier->id, 'lines' => [['batch_id' => $item->id, 'qty' => 7, 'unit' => 'pcs', 'unit_cost' => 10]]]);
        $this->assertSame(7.0, $item->fresh()->stock_qty);
        $this->assertNull(StockDocumentLine::firstOrFail()->entered_unit);
    }

    public function test_converter_uses_direct_inverse_and_same_unit(): void
    {
        UnitConversion::create(['from_unit' => 'box', 'to_unit' => 'pcs', 'factor' => 12]);

        $this->assertSame(12.0, UnitConverter::factor('box', 'pcs'));
        $this->assertEqualsWithDelta(1 / 12, UnitConverter::factor('pcs', 'box'), 1e-9);
        $this->assertSame(1.0, UnitConverter::factor('kg', 'kg'));
        $this->assertNull(UnitConverter::factor('box', 'kg'));
        $this->assertSame(60.0, Decimal::round(UnitConverter::exact('5', 'box', 'pcs'), 6));
        $this->assertSame(2.0, Decimal::round(UnitConverter::exact('24', 'pcs', 'box'), 6));
    }

    // Simpan (draf) lalu Confirm supaya diproses ke stok.
    private function receiveAs(User $user, array $payload): void
    {
        $this->actingAs($user)->post(route('docs.store', 'receiving'), $payload)->assertRedirect();
        $this->put(route('docs.update', ['receiving', StockDocument::latest('id')->firstOrFail()]), $payload + ['_action' => 'confirm'])->assertRedirect();
    }
}
