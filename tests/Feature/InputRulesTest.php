<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Department;
use App\Models\Item;
use App\Models\Recipient;
use App\Models\StockDocument;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga aturan masukan dokumen stok hasil uji kemudahan: stok kurang melapor "tersedia X, dibutuhkan Y" tanpa menulis ulang draf, presisi qty satu aturan (maks 3 desimal, tidak dibulatkan diam-diam,
 *                0,0004 ditolak) di semua dokumen dan hitung opname, kedaluwarsa wajar (Penerimaan tidak boleh lampau), dan Penyesuaian dengan selisih 0 ditolak.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class InputRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $sup;

    private Batch $pen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sup = User::factory()->supervisor()->create();
        $cat = Category::create(['code' => 'ATK', 'name' => 'ATK']);
        $this->pen = $this->makeBatch(['code' => 'ATK-0002', 'name' => 'Pulpen Hitam', 'category_id' => $cat->id, 'unit' => 'pcs', 'unit_price' => 10]);
        $this->actingAs($this->sup);
    }

    private function supplier(): int
    {
        return Supplier::firstOrCreate(['code' => 'S1'], ['name' => 'S'])->id;
    }

    private function receive(array $line): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('docs.store', 'receiving'), ['supplier_id' => $this->supplier(), 'lines' => [$line + ['batch_id' => $this->pen->id, 'qty' => 1, 'unit_cost' => 10]]]);
    }

    private function stock(float $qty): void
    {
        $this->receive(['qty' => $qty])->assertSessionHasNoErrors();
        $doc = StockDocument::latest('id')->first();
        $this->put(route('docs.update', ['receiving', $doc]), ['_action' => 'confirm', 'supplier_id' => $this->supplier(), 'lines' => [['batch_id' => $this->pen->id, 'qty' => $qty, 'unit_cost' => 10]]])->assertSessionHasNoErrors();
    }

    private function issuePayload(float $qty): array
    {
        $dept = Department::firstOrCreate(['code' => 'D1'], ['name' => 'Dept']);

        return ['recipient_id' => Recipient::firstOrCreate(['name' => 'R'], ['department_id' => $dept->id])->id, 'lines' => [['batch_id' => $this->pen->id, 'qty' => $qty]]];
    }

    public function test_issuance_short_of_stock_says_available_and_needed_and_leaves_the_draft_untouched(): void
    {
        $this->stock(19);
        $this->post(route('docs.store', 'issuance'), $this->issuePayload(5))->assertSessionHasNoErrors();
        $draft = StockDocument::latest('id')->first();

        $this->put(route('docs.update', ['issuance', $draft]), ['_action' => 'confirm'] + $this->issuePayload(25))
            ->assertSessionHasErrors(['lines' => 'Stok ATK-0002 Pulpen Hitam tidak cukup: tersedia 19, dibutuhkan 25.']);

        $lines = $draft->fresh()->lines;
        $this->assertSame([5.0], $lines->pluck('qty')->all(), 'draf tetap isi sebelumnya, tidak ditulis ulang');
        $this->assertSame(19.0, $this->pen->fresh()->stock_qty);
    }

    public function test_a_saved_draft_keeps_one_line_as_typed_when_stock_is_short(): void
    {
        $this->stock(19);

        $this->post(route('docs.store', 'issuance'), $this->issuePayload(25))->assertSessionHasNoErrors();

        $this->assertSame([25.0], StockDocument::latest('id')->first()->lines->pluck('qty')->all());
    }

    public function test_short_stock_across_batches_reports_the_whole_item_total(): void
    {
        $milk = Item::register(['name' => 'Susu', 'category_id' => Category::first()->id, 'unit' => 'pcs', 'unit_price' => 5, 'tracks_expiry' => true]);
        $a = $milk->batchFor(today()->addDays(60));
        $b = $milk->batchFor(today()->addDays(90));
        $a->forceFill(['stock_qty' => 4])->save();
        $b->forceFill(['stock_qty' => 3])->save();
        $payload = $this->issuePayload(1);
        $payload['lines'] = [['batch_id' => $a->id, 'qty' => 10]];
        $this->post(route('docs.store', 'issuance'), $payload)->assertSessionHasNoErrors();
        $draft = StockDocument::latest('id')->first();

        $this->put(route('docs.update', ['issuance', $draft]), ['_action' => 'confirm'] + $payload)
            ->assertSessionHasErrors(['lines' => "Stok {$a->code} Susu tidak cukup: tersedia 7, dibutuhkan 10."]);
    }

    public function test_text_quantity_gives_a_single_message(): void
    {
        $messages = $this->receive(['qty' => 'abc'])->assertSessionHasErrors('lines.0.qty')->baseResponse->getSession()->get('errors')->getBag('default')->get('lines.0.qty');

        $this->assertCount(1, $messages);
    }

    public function test_receiving_rejects_a_quantity_that_would_round_to_zero(): void
    {
        $this->receive(['qty' => '0.0004'])->assertSessionHasErrors('lines.0.qty');
        $this->assertSame(0, StockDocument::count());
    }

    public function test_receiving_rejects_more_than_three_decimals_instead_of_rounding(): void
    {
        $this->receive(['qty' => '1.2345'])->assertSessionHasErrors('lines.0.qty');
        $this->receive(['qty' => '1.235'])->assertSessionHasNoErrors();
        $this->assertSame([1.235], StockDocument::first()->lines->pluck('qty')->all());
    }

    public function test_issuance_rejects_a_quantity_that_would_round_to_zero(): void
    {
        $this->stock(19);

        $this->post(route('docs.store', 'issuance'), $this->issuePayload(0.0004))->assertSessionHasErrors('lines.0.qty');
    }

    public function test_opening_balance_rejects_more_than_three_decimals(): void
    {
        $this->post(route('docs.store', 'opening'), ['doc_at' => today()->toDateString(), 'lines' => [['batch_id' => $this->pen->id, 'qty' => '2.0005', 'unit_cost' => 1]]])->assertSessionHasErrors('lines.0.qty');
    }

    public function test_adjustment_rejects_a_counted_quantity_with_more_than_three_decimals(): void
    {
        $this->stock(19);

        $this->post(route('docs.store', 'adjustment'), ['reason' => 'Opname', 'lines' => [['batch_id' => $this->pen->id, 'counted_qty' => '18.0004']]])->assertSessionHasErrors('lines.0.counted_qty');
    }

    public function test_adjustment_with_zero_difference_is_rejected(): void
    {
        $this->stock(19);

        $this->post(route('docs.store', 'adjustment'), ['reason' => 'Opname', 'lines' => [['batch_id' => $this->pen->id, 'counted_qty' => 19]]])
            ->assertSessionHasErrors(['lines' => 'Jumlah hitung ATK-0002 sama dengan stok sistem (19): tidak ada yang disesuaikan. Ubah jumlah hitungnya atau hapus barang itu dari baris.']);
        $this->assertSame(1, StockDocument::count());   // hanya Penerimaan awal
    }

    public function test_stock_count_rejects_a_count_with_more_than_three_decimals_on_desktop_and_phone_endpoint(): void
    {
        $this->stock(19);
        $count = app(\App\Services\StockCounts::class)->start($this->sup, ['count_date' => today()->toDateString(), 'scope_type' => 'all', 'blind' => true, 'lock_transactions' => false, 'threshold_pct' => 5]);
        $id = $count->lines()->value('id');

        $this->postJson(route('stock-counts.lines', $count), ['changed' => [['id' => $id, 'counted_qty' => '0.0004']]])->assertStatus(422);
        $this->postJson(route('stock-counts.lines', $count), ['changed' => [['id' => $id, 'counted_qty' => '1.2345']]])->assertStatus(422);
        $this->postJson(route('stock-counts.lines', $count), ['changed' => [['id' => $id, 'counted_qty' => '1.234']]])->assertOk();
        $this->assertSame(1.234, $count->lines()->first()->counted_qty);
    }

    public function test_receiving_rejects_an_expiry_date_before_today(): void
    {
        $milk = Item::register(['name' => 'Susu', 'category_id' => Category::first()->id, 'unit' => 'pcs', 'unit_price' => 5, 'tracks_expiry' => true]);
        $line = ['batch_id' => null, 'item_id' => $milk->id];

        $this->receive($line + ['expiry_date' => today()->subDay()->toDateString()])->assertSessionHasErrors('lines.0.expiry_date');
        $this->receive($line + ['expiry_date' => today()->toDateString()])->assertSessionHasNoErrors();
    }

    public function test_expiry_date_with_an_absurd_year_is_rejected(): void
    {
        $milk = Item::register(['name' => 'Susu', 'category_id' => Category::first()->id, 'unit' => 'pcs', 'unit_price' => 5, 'tracks_expiry' => true]);
        $line = ['batch_id' => null, 'item_id' => $milk->id];

        $this->receive($line + ['expiry_date' => '9999-12-31'])->assertSessionHasErrors('lines.0.expiry_date');
        $this->receive($line + ['expiry_date' => today()->addYears(60)->toDateString()])->assertSessionHasErrors('lines.0.expiry_date');
        $this->receive($line + ['expiry_date' => today()->addYears(5)->toDateString()])->assertSessionHasNoErrors();
    }

    public function test_opening_balance_still_accepts_an_expiry_date_in_the_past(): void
    {
        $milk = Item::register(['name' => 'Susu', 'category_id' => Category::first()->id, 'unit' => 'pcs', 'unit_price' => 5, 'tracks_expiry' => true]);
        $line = ['item_id' => $milk->id, 'qty' => 2, 'unit_cost' => 1];

        $this->post(route('docs.store', 'opening'), ['doc_at' => today()->toDateString(), 'lines' => [$line + ['expiry_date' => today()->subMonth()->toDateString()]]])->assertSessionHasNoErrors();
        $this->post(route('docs.store', 'opening'), ['doc_at' => today()->toDateString(), 'lines' => [$line + ['expiry_date' => '1990-01-01']]])->assertSessionHasErrors('lines.0.expiry_date');
    }
}
