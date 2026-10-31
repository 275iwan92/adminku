<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Recipient;
use App\Models\StockDocument;
use App\Models\StockDocumentLine;
use App\Models\Supplier;
use App\Models\UnitConversion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga aturan "Pengeluaran dan Penyesuaian selalu dalam satuan stok barang": tanpa pemilih satuan di form, isian unit diabaikan, tanpa popup konversi, dokumen lama yang punya entered_* terbaca dalam satuan stok.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class IssuanceStockUnitTest extends TestCase
{
    use RefreshDatabase;

    private function stocked(float $qty = 100): \App\Models\Batch
    {
        $item = $this->makeBatch(['code' => 'K-1', 'name' => 'Kertas', 'category_id' => Category::firstOrCreate(['code' => 'ATK'], ['name' => 'ATK'])->id, 'unit' => 'pcs', 'unit_price' => 10, 'reorder_point' => 1, 'reorder_qty' => 5]);
        $payload = ['supplier_id' => Supplier::create(['code' => 'S1', 'name' => 'S One'])->id, 'lines' => [['batch_id' => $item->id, 'qty' => $qty, 'unit_cost' => 10]]];
        $this->post(route('docs.store', 'receiving'), $payload)->assertRedirect();
        $this->put(route('docs.update', ['receiving', StockDocument::latest('id')->firstOrFail()]), $payload + ['_action' => 'confirm'])->assertRedirect();

        return $item;
    }

    public function test_issuance_ignores_a_posted_unit_and_never_asks_for_a_conversion(): void
    {
        $this->actingAs(User::factory()->create());
        $item = $this->stocked();
        $payload = ['recipient_id' => Recipient::create(['name' => 'Budi'])->id, 'lines' => [['batch_id' => $item->id, 'qty' => 2, 'unit' => 'box']]];

        $this->post(route('docs.store', 'issuance'), $payload)->assertRedirect()->assertSessionMissing('missing_conversions');
        $doc = StockDocument::latest('id')->firstOrFail();
        $this->put(route('docs.update', ['issuance', $doc]), $payload + ['_action' => 'confirm'])->assertRedirect();

        $line = $doc->lines()->firstOrFail();
        $this->assertSame([2.0, null, null], [$line->qty, $line->entered_qty, $line->entered_unit]);   // 2 pcs, bukan 2 box
        $this->assertSame(98.0, $item->fresh()->stock_qty);
    }

    public function test_issuance_and_adjustment_forms_have_no_unit_picker(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());
        $item = $this->stocked();
        $units = array_keys(\App\Models\Unit::pluck('name', 'code')->all());

        foreach (['issuance', 'adjustment'] as $type) {
            $html = $this->get(route('docs.create', $type))->assertOk()->getContent();
            $this->assertStringNotContainsString('stock_qty', $html);   // kolom "= In stock unit" hanya milik Penerimaan
            $this->assertStringNotContainsString('conv-missing', $html);
            foreach ($units as $code) {
                $this->assertStringNotContainsString("&quot;{$code}&quot;:&quot;{$code} ", $html);   // tidak ada daftar pilihan satuan di kolom
            }
        }
        $this->get(route('docs.create', 'receiving'))->assertOk()->assertSee('stock_qty', false)->assertSee('&quot;pcs&quot;:&quot;pcs ', false);   // kontrol: pola pencarian di atas memang cocok bila daftar satuan ada
    }

    public function test_legacy_issuance_with_entered_unit_reopens_in_the_stock_unit(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());
        $item = $this->stocked();
        UnitConversion::create(['from_unit' => 'box', 'to_unit' => 'pcs', 'factor' => 12]);
        $payload = ['recipient_id' => Recipient::create(['name' => 'Budi'])->id, 'lines' => [['batch_id' => $item->id, 'qty' => 24]]];
        $this->post(route('docs.store', 'issuance'), $payload)->assertRedirect();
        $doc = StockDocument::latest('id')->firstOrFail();
        StockDocumentLine::where('stock_document_id', $doc->id)->update(['entered_qty' => 2, 'entered_unit' => 'box']);   // bentuk data lama

        $html = $this->get(route('docs.show', ['issuance', $doc]))->assertOk()->getContent();
        $this->assertStringContainsString('&quot;qty&quot;:24', $html);
        $this->assertStringNotContainsString('&quot;qty&quot;:2.0', $html);
        $this->assertStringNotContainsString('&quot;unit&quot;:&quot;box&quot;', $html);
    }
}
