<?php

namespace Tests\Feature;

use App\Enums\StockDocumentType;
use App\Models\Category;
use App\Models\StockCount;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockCounts;
use App\Services\StockDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga layar Hitung Stok Opname tampilan HP: daftar opname, kartu hitung memakai data dan endpoint yang sama dengan desktop, hitung buta tidak membocorkan stok sistem, simpan per barang (jawaban ringkas dengan jejak siapa / jam, 409 membawa baris terbaru), Selesai hitung dari HP kembali ke daftar HP.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class HpStockCountTest extends TestCase
{
    use RefreshDatabase;

    private User $sup;

    private User $entry;

    private int $a;

    private int $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sup = User::factory()->supervisor()->create(['name' => 'Sari Supervisor']);
        $this->entry = User::factory()->create(['name' => 'Eko Entry']);
        $cat = Category::create(['code' => 'ATK', 'name' => 'ATK']);
        $docs = app(StockDocuments::class);
        $supplier = Supplier::create(['code' => 'S1', 'name' => 'S']);
        foreach ([['A1', 'Pensil', 'RAK-A', 12345.5], ['B1', 'Penghapus', 'RAK-B', 7]] as $n => [$code, $name, $loc, $qty]) {
            $item = $this->makeBatch(['code' => $code, 'name' => $name, 'category_id' => $cat->id, 'unit' => 'pcs', 'unit_price' => 100, 'location' => $loc]);
            $docs->confirm($docs->create(StockDocumentType::Receiving, ['supplier_id' => $supplier->id], [['batch_id' => $item->id, 'qty' => $qty, 'unit_cost' => 100]], $this->sup), $this->sup);
            $n === 0 ? $this->a = $item->id : $this->b = $item->id;
        }
    }

    private function start(bool $blind = true): StockCount
    {
        return app(StockCounts::class)->start($this->sup, ['count_date' => today()->toDateString(), 'scope_type' => 'all', 'blind' => $blind, 'lock_transactions' => false, 'threshold_pct' => 5]);
    }

    private function lineId(StockCount $c, int $itemId): int
    {
        return $c->lines()->where('batch_id', $itemId)->value('id');
    }

    public function test_list_shows_counts_as_cards_with_a_count_button_only_while_counting(): void
    {
        $count = $this->start();

        $this->actingAs($this->entry)->get(route('hp.counts'))->assertOk()->assertSee($count->doc_no)->assertSee('Hitung sekarang')->assertSee(route('hp.count', $count), false)->assertDontSee('Kelola (Supervisor)');
        $this->actingAs($this->sup)->get(route('hp.counts'))->assertSee('Mulai atau tinjau opname');
    }

    public function test_count_screen_hands_the_rows_in_shelf_order_with_standard_input_and_no_hover_dependency(): void
    {
        $count = $this->start();

        $page = $this->actingAs($this->entry)->get(route('hp.count', $count))->assertOk()
            ->assertSee('inputmode="decimal"', false)->assertSee('data-search', false)->assertSee('Barang berikutnya')->assertSee('data-hp-rows', false);
        $html = $page->getContent();
        $this->assertLessThan(strpos($html, 'Penghapus'), strpos($html, 'Pensil'), 'urut lokasi: RAK-A sebelum RAK-B');
        $this->assertStringNotContainsString(':hover', $html);
    }

    public function test_blind_count_never_sends_system_stock_but_an_open_one_does(): void
    {
        $blind = $this->start();
        $html = $this->actingAs($this->entry)->get(route('hp.count', $blind))->getContent();
        $this->assertStringNotContainsString('system_qty', $html);
        $this->assertStringNotContainsString('12345.5', $html);

        $blind->update(['status' => 'cancelled']);
        $open = $this->start(false);
        $this->assertStringContainsString('system_qty', $this->actingAs($this->entry)->get(route('hp.count', $open))->getContent());
    }

    public function test_a_closed_count_returns_to_the_list(): void
    {
        $count = $this->start();
        $count->update(['status' => 'review']);

        $this->actingAs($this->entry)->get(route('hp.count', $count))->assertRedirect(route('hp.counts'));
    }

    public function test_save_per_item_answers_with_only_that_row_and_who_filled_it(): void
    {
        $count = $this->start();
        $id = $this->lineId($count, $this->a);

        $res = $this->actingAs($this->entry)->postJson(route('stock-counts.lines', $count), ['compact' => 1, 'changed' => [['id' => $id, 'rev' => 0, 'counted_qty' => '12,5'.''], ]]);
        $res->assertStatus(422);   // koma desimal dinormalkan di browser; server hanya menerima titik

        $res = $this->actingAs($this->entry)->postJson(route('stock-counts.lines', $count), ['compact' => 1, 'changed' => [['id' => $id, 'rev' => 0, 'counted_qty' => '12.5', 'counted_by' => 'Eko']]])->assertOk();
        $rows = $res->json('rows');
        $this->assertCount(1, $rows);
        $this->assertSame($id, $rows[0]['id']);
        $this->assertEquals(12.5, $rows[0]['counted_qty']);
        $this->assertSame($this->entry->id, $rows[0]['by_id']);
        $this->assertSame('Eko Entry', $rows[0]['by']);
        $this->assertSame(1, $rows[0]['rev']);
        $this->assertArrayNotHasKey('system_qty', $rows[0], 'hitung buta');
    }

    public function test_desktop_save_answer_is_unchanged_without_compact(): void
    {
        $count = $this->start();
        $id = $this->lineId($count, $this->a);

        $rows = $this->actingAs($this->entry)->postJson(route('stock-counts.lines', $count), ['changed' => [['id' => $id, 'rev' => 0, 'counted_qty' => '3']]])->assertOk()->json('rows');
        $this->assertCount(2, $rows);
        $this->assertArrayNotHasKey('by_id', $rows[0]);
    }

    public function test_conflict_returns_the_latest_row_and_overwriting_needs_a_reason(): void
    {
        $count = $this->start();
        $id = $this->lineId($count, $this->a);
        $this->actingAs($this->sup)->postJson(route('stock-counts.lines', $count), ['changed' => [['id' => $id, 'rev' => 0, 'counted_qty' => '10']]])->assertOk();

        $stale = ['compact' => 1, 'changed' => [['id' => $id, 'rev' => 0, 'counted_qty' => '11']]];
        $res = $this->actingAs($this->entry)->postJson(route('stock-counts.lines', $count), $stale)->assertStatus(409);
        $this->assertTrue($res->json('reason_required'));
        $this->assertSame('Sari Supervisor', $res->json('row.by'));
        $this->assertEquals(10, $res->json('row.counted_qty'));

        $this->actingAs($this->entry)->postJson(route('stock-counts.lines', $count), $stale + ['reason' => 'hitung ulang di rak'])->assertOk();
        $this->assertEquals(11, $count->lines()->find($id)->counted_qty);
    }

    public function test_finish_from_the_phone_returns_to_the_phone_list(): void
    {
        $count = $this->start();
        foreach ($count->lines as $line) {
            $this->actingAs($this->entry)->postJson(route('stock-counts.lines', $count), ['changed' => [['id' => $line->id, 'rev' => 0, 'counted_qty' => '1']]])->assertOk();
        }

        $this->actingAs($this->entry)->post(route('stock-counts.finish', $count), ['hp' => 1])->assertRedirect(route('hp.counts'));
        $this->assertSame('review', $count->fresh()->status);
    }
}
