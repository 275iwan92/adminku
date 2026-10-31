<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\StockDocument;
use App\Models\StockDocumentLine;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga aturan ubah faktor satuan masuk: siapa pun boleh membuat dan memperbaiki yang belum pernah dipakai di dokumen terkonfirmasi; yang sudah dipakai hanya pemegang hak tulis Satuan (Supervisor),
 *                yang lain ditolak 403 dengan pesan baku. Dokumen lama tidak berubah. Bagian "Satuan masuk" di form barang menampilkan faktor, pemakaian, siapa / kapan mengubah, dan tautan Ubah menurut hak.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class ProductUnitRulesTest extends TestCase
{
    use RefreshDatabase;

    private const USED = 'Faktor ini sudah dipakai di dokumen; hubungi Supervisor untuk mengubahnya.';

    private Item $product;

    protected function setUp(): void
    {
        parent::setUp();
        Unit::create(['code' => 'dus', 'name' => 'Dus Besar']);
        $this->product = Item::register(['name' => 'Pensil', 'category_id' => Category::create(['code' => 'ATK', 'name' => 'ATK'])->id, 'unit' => 'pcs', 'unit_price' => 100, 'reorder_point' => 1, 'reorder_qty' => 5], 'PU-1');
    }

    private function save(string $unit, float $factor, string $route = 'products.units.save')
    {
        return $route === 'products.units.save'
            ? $this->postJson(route($route, $this->product), ['unit' => $unit, 'factor' => $factor])
            : $this->postJson(route($route, 'receiving'), ['item_id' => $this->product->id, 'unit' => $unit, 'factor' => $factor]);
    }

    // Penerimaan 5 dus yang sudah dikonfirmasi; mengembalikan dokumennya.
    private function confirmedReceiving(User $by): StockDocument
    {
        $payload = ['supplier_id' => Supplier::firstOrCreate(['code' => 'S1'], ['name' => 'S One'])->id, 'lines' => [['item_id' => $this->product->id, 'qty' => 5, 'unit' => 'dus', 'unit_cost' => 1200]]];
        $this->actingAs($by)->post(route('docs.store', 'receiving'), $payload)->assertRedirect();
        $doc = StockDocument::latest('id')->firstOrFail();
        $this->put(route('docs.update', ['receiving', $doc]), $payload + ['_action' => 'confirm'])->assertRedirect();

        return $doc->fresh();
    }

    public function test_data_entry_creates_a_factor_and_it_is_recorded(): void
    {
        $entry = User::factory()->create();
        $this->actingAs($entry);

        $this->save('dus', 12)->assertOk();
        $row = ItemUnit::firstOrFail();
        $this->assertSame(['12.000000', $entry->id, $entry->id], [$row->factor, $row->created_by, $row->updated_by]);
    }

    public function test_data_entry_may_correct_a_factor_that_no_document_has_used_yet(): void
    {
        $entry = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($other);
        $this->save('dus', 21)->assertOk();   // salah ketik awal

        $this->actingAs($entry);
        $this->save('dus', 12)->assertOk();
        $row = ItemUnit::firstOrFail();
        $this->assertSame(['12.000000', $other->id, $entry->id], [$row->factor, $row->created_by, $row->updated_by]);
    }

    public function test_a_draft_does_not_count_as_use(): void
    {
        $entry = User::factory()->create();
        $this->actingAs($entry);
        $this->save('dus', 21)->assertOk();
        $this->post(route('docs.store', 'receiving'), ['supplier_id' => Supplier::create(['code' => 'S1', 'name' => 'S'])->id, 'lines' => [['item_id' => $this->product->id, 'qty' => 1, 'unit' => 'dus', 'unit_cost' => 1]]])->assertRedirect();

        $this->save('dus', 12)->assertOk();   // draf belum mengunci faktor
        $this->assertSame('12.000000', ItemUnit::firstOrFail()->factor);
    }

    public function test_a_used_factor_is_refused_for_data_entry_with_the_standard_message_on_both_entry_points(): void
    {
        $entry = User::factory()->create();
        $this->actingAs($entry);
        $this->save('dus', 12)->assertOk();
        $this->confirmedReceiving($entry);

        $this->save('dus', 10)->assertForbidden()->assertJson(['message' => self::USED]);
        $this->save('dus', 10, 'docs.conversion')->assertForbidden()->assertJson(['message' => self::USED]);
        $this->assertSame('12.000000', ItemUnit::firstOrFail()->factor);

        $this->save('dus', 12)->assertOk();   // nilai yang sama bukan perubahan
    }

    public function test_a_cancelled_receiving_still_locks_the_factor(): void
    {
        $sup = User::factory()->supervisor()->create();
        $entry = User::factory()->create();
        $this->actingAs($entry);
        $this->save('dus', 12)->assertOk();
        $doc = $this->confirmedReceiving($entry);
        $this->actingAs($sup)->put(route('docs.update', ['receiving', $doc]), ['_action' => 'cancel', '_reason' => 'salah catat'])->assertRedirect();
        $this->assertSame('cancelled', $doc->fresh()->status);

        $this->actingAs($entry);
        $this->save('dus', 10)->assertForbidden();
    }

    public function test_supervisor_changes_a_used_factor_and_the_old_document_stays_as_it_was(): void
    {
        $sup = User::factory()->supervisor()->create();
        $entry = User::factory()->create();
        $this->actingAs($entry);
        $this->save('dus', 12)->assertOk();
        $doc = $this->confirmedReceiving($entry);

        $this->actingAs($sup);
        $this->save('dus', 10)->assertOk();
        $row = ItemUnit::firstOrFail();
        $this->assertSame(['10.000000', $entry->id, $sup->id], [$row->factor, $row->created_by, $row->updated_by]);

        $line = StockDocumentLine::where('stock_document_id', $doc->id)->firstOrFail();
        $this->assertSame([60.0, 5.0, 'dus'], [$line->qty, $line->entered_qty, $line->entered_unit]);   // 5 dus tetap 60 pcs
        $this->assertSame(60.0, $this->product->batches()->firstOrFail()->stock_qty);
        $html = str_replace('\\', '', $this->get(route('docs.show', ['receiving', $doc]))->assertOk()->getContent());
        $this->assertStringContainsString('&quot;stock_qty&quot;:60', $html);   // dokumen terkunci menampilkan qty tersimpan
    }

    public function test_the_factor_must_be_positive_and_not_for_the_stock_unit(): void
    {
        $this->actingAs(User::factory()->create());

        $this->save('dus', 0)->assertStatus(422);
        $this->save('pcs', 12)->assertStatus(422);
        $this->save('tidak-ada', 12)->assertStatus(422);
        $this->assertSame(0, ItemUnit::count());
    }

    public function test_the_product_form_lists_pack_units_with_use_who_and_when(): void
    {
        $entry = User::factory()->create(['name' => 'Siti Penginput']);
        $this->actingAs($entry);
        $this->save('dus', 12)->assertOk();
        $this->confirmedReceiving($entry);

        $html = $this->get(route('products.show', $this->product))->assertOk()->getContent();
        foreach (['Satuan masuk', 'Faktor ke pcs', 'dus · Dus Besar', '1 dokumen', 'Siti Penginput', 'Perubahan hanya berlaku untuk penerimaan berikutnya, dokumen lama tidak berubah.', 'Hubungi Supervisor'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('#ubah-dus', $html);   // sudah dipakai: Data Entry tanpa tautan Ubah

        $this->save('dus', 12);
        $this->actingAs(User::factory()->supervisor()->create());
        $this->assertStringContainsString('#ubah-dus', $this->get(route('products.show', $this->product))->getContent());
    }

    public function test_an_unused_factor_shows_the_change_link_to_data_entry(): void
    {
        $this->actingAs(User::factory()->create());
        $this->save('dus', 12)->assertOk();

        $html = $this->get(route('products.show', $this->product))->assertOk()->getContent();
        $this->assertStringContainsString('#ubah-dus', $html);
        $this->assertStringContainsString('Belum dipakai', $html);
    }

    public function test_a_new_product_has_no_pack_unit_section(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('products.create'))->assertOk()->assertDontSee('Tambah Satuan Masuk');
    }
}
