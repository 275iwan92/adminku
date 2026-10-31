<?php

namespace Tests\Feature;

use App\Enums\StockDocumentType;
use App\Models\Category;
use App\Models\Batch;
use App\Models\StockCount;
use App\Models\StockDocument;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockCounts;
use App\Services\StockDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga layar tinjau dan persetujuan Stok Opname lewat HTTP: hanya Supervisor, selisih / nilai tampil benar, alasan wajib, hitung ulang, stok bergeser, Setujui membuat satu Penyesuaian,
 *                Kartu Stok memuat referensi SO, Kembalikan, Batalkan, layar hasil hanya baca.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class StockCountReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $sup;

    private User $entry;

    private Batch $a;

    private Batch $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sup = User::factory()->supervisor()->create();
        $this->entry = User::factory()->create();
        $cat = Category::create(['code' => 'ATK', 'name' => 'ATK']);
        $this->a = $this->makeBatch(['code' => 'A1', 'name' => 'Pensil', 'category_id' => $cat->id, 'unit' => 'pcs', 'unit_price' => 100]);
        $this->b = $this->makeBatch(['code' => 'B1', 'name' => 'Penghapus', 'category_id' => $cat->id, 'unit' => 'pcs', 'unit_price' => 100]);
        $docs = app(StockDocuments::class);
        $supplier = Supplier::create(['code' => 'S1', 'name' => 'S']);
        foreach ([[$this->a, 10, 1500], [$this->b, 20, 200]] as [$item, $qty, $cost]) {
            $docs->confirm($docs->create(StockDocumentType::Receiving, ['supplier_id' => $supplier->id], [['batch_id' => $item->id, 'qty' => $qty, 'unit_cost' => $cost]], $this->sup), $this->sup);
        }
    }

    // Opname berstatus Tinjau: A1 10 -> 8 (-2, 20%), B1 20 -> 20.
    private function review(bool $lock = true): StockCount
    {
        $counts = app(StockCounts::class);
        $count = $counts->start($this->sup, ['count_date' => today()->toDateString(), 'scope_type' => 'all', 'blind' => true, 'lock_transactions' => $lock, 'threshold_pct' => 5]);
        $counts->saveLine($count, $this->lineId($count, $this->a), ['counted_qty' => 8]);
        $counts->saveLine($count, $this->lineId($count, $this->b), ['counted_qty' => 20]);
        $counts->finish($count, $this->entry);

        return $count->fresh();
    }

    private function lineId(StockCount $count, Batch $item): int
    {
        return $count->lines()->where('batch_id', $item->id)->value('id');
    }

    private function annotate(StockCount $count, Batch $item, array $in)
    {
        return $this->actingAs($this->sup)->postJson(route('stock-counts.review', $count), ['changed' => [['id' => $this->lineId($count, $item)] + $in]]);
    }

    public function test_review_screen_shows_difference_percent_value_and_summary_to_supervisor_only(): void
    {
        $count = $this->review();

        $page = $this->actingAs($this->sup)->get(route('stock-counts.show', $count))->assertOk()->assertSee('Setujui Opname')->assertSee('Kembalikan ke Penghitungan')->assertSee('Nilai Kurang');
        $rows = collect(json_decode(html_entity_decode(preg_match('/data-rows="([^"]*)"/', $page->getContent(), $m) ? $m[1] : '[]'), true))->keyBy('code');
        $this->assertSame([-2, -20, -3000, 'Besar', 'yes'], [$rows['A1']['diff'], $rows['A1']['pct'], $rows['A1']['value'], $rows['A1']['flag'], $rows['A1']['has_diff']]);   // -2 x 1.500
        $this->assertSame(['no', 0], [$rows['B1']['has_diff'], $rows['B1']['diff']]);

        $this->actingAs($this->entry);   // Data Entry: layar tinjau tidak ada, tidak ada nilai, semua aksi Supervisor ditolak
        $entryPage = $this->get(route('stock-counts.show', $count))->assertOk()->assertDontSee('Setujui Opname')->assertDontSee('Nilai Kurang');
        $this->assertStringNotContainsString('-3000', $entryPage->getContent());
        $this->postJson(route('stock-counts.review', $count), ['changed' => []])->assertForbidden();
        foreach (['approve', 'reject', 'refresh', 'cancel', 'unlock'] as $action) {
            $this->post(route("stock-counts.{$action}", $count))->assertForbidden();
        }
    }

    public function test_reason_is_required_above_the_threshold_then_approve_posts_one_adjustment_visible_in_the_stock_card(): void
    {
        $count = $this->review();
        $this->actingAs($this->sup)->post(route('stock-counts.approve', $count))->assertSessionHasErrors('lines');
        $this->assertStringContainsString('A1', session('errors')->first('lines'));
        $this->assertSame('review', $count->fresh()->status);
        $this->assertSame(10.0, $this->a->fresh()->stock_qty);

        $rows = $this->annotate($count, $this->a, ['reason' => 'Rusak kena banjir'])->assertOk()->json('rows');
        $this->assertSame('Rusak kena banjir', collect($rows)->firstWhere('code', 'A1')['reason']);

        $before = StockDocument::count();
        $this->post(route('stock-counts.approve', $count))->assertRedirect(route('stock-counts.show', $count));
        $this->assertSame($before + 1, StockDocument::count());
        $adj = StockDocument::latest('id')->firstOrFail();
        $this->assertSame(["Stok Opname {$count->doc_no}", 'approved', StockDocumentType::Adjustment], [$adj->reason, $adj->status, $adj->type]);
        $this->assertSame([8.0, 20.0], [$this->a->fresh()->stock_qty, $this->b->fresh()->stock_qty]);
        $this->assertSame($adj->id, $count->fresh()->adjustment_document_id);

        $this->get(route('reports.stock-card', ['batch_id' => $this->a->id]))->assertOk()->assertSee("Stok Opname {$count->doc_no}")->assertSee('Penyesuaian');   // butir 8: kartu stok memuat referensi SO
        $this->get(route('stock-counts.show', $count))->assertOk()->assertSee($adj->doc_no)->assertDontSee('Setujui Opname');
        $this->postJson(route('stock-counts.review', $count), ['changed' => [['id' => $this->lineId($count, $this->a), 'reason' => 'x']]])->assertStatus(422);   // layar hasil hanya baca
        $this->post(route('stock-counts.approve', $count))->assertStatus(422);
    }

    public function test_recount_changes_the_difference_and_can_remove_it(): void
    {
        $count = $this->review();

        $rows = $this->annotate($count, $this->a, ['recount_qty' => 10])->assertOk()->json('rows');   // hitung ulang menemukan 10: selisih hilang
        $a = collect($rows)->firstWhere('code', 'A1');
        $this->assertSame([0, 'no', 'Dihitung ulang'], [$a['diff'], $a['has_diff'], $a['state']]);

        $this->post(route('stock-counts.approve', $count))->assertRedirect();
        $this->assertSame(0, StockDocument::where('reason', "Stok Opname {$count->doc_no}")->count());   // tanpa selisih: tanpa Penyesuaian
        $this->assertSame('done', $count->fresh()->status);
        $this->assertSame(10.0, $this->a->fresh()->stock_qty);
    }

    public function test_moved_stock_blocks_approval_and_refresh_reopens_only_the_moved_lines(): void
    {
        $count = $this->review(lock: false);
        $docs = app(StockDocuments::class);
        $docs->confirm($docs->create(StockDocumentType::Receiving, ['supplier_id' => Supplier::first()->id], [['batch_id' => $this->a->id, 'qty' => 5, 'unit_cost' => 1500]], $this->sup), $this->sup);   // A1 menjadi 15
        $this->annotate($count, $this->a, ['reason' => 'Rusak']);

        $this->get(route('stock-counts.show', $count))->assertOk()->assertSee('Stok bergeser sejak cut-off')->assertSee('Perbarui Baris Bergeser');
        $this->post(route('stock-counts.approve', $count))->assertSessionHasErrors('lines');
        $this->assertStringContainsString('A1: sistem 10, kini 15', session('errors')->first('lines'));

        $this->post(route('stock-counts.refresh', $count))->assertRedirect();
        $fresh = $count->fresh();
        $this->assertSame('counting', $fresh->status);
        $a = $count->lines()->find($this->lineId($count, $this->a));
        $b = $count->lines()->find($this->lineId($count, $this->b));
        $this->assertSame([15.0, null, 20.0], [$a->system_qty, $a->counted_qty, $b->counted_qty]);   // B1 tidak bergeser: tetap terisi
    }

    public function test_return_to_counting_needs_a_note_and_cancel_releases_the_lock(): void
    {
        $count = $this->review();
        $this->actingAs($this->sup)->post(route('stock-counts.reject', $count), ['note' => ''])->assertStatus(422);
        $this->post(route('stock-counts.reject', $count), ['note' => 'Hitung ulang rak A'])->assertRedirect();
        $this->assertSame(['counting', 'Hitung ulang rak A'], [$count->fresh()->status, $count->fresh()->review_note]);
        $this->get(route('stock-counts.show', $count))->assertOk()->assertSee('Hitung ulang rak A');

        $this->assertNotNull(StockCount::lockingNow());
        $this->post(route('stock-counts.cancel', $count))->assertRedirect();
        $this->assertNull(StockCount::lockingNow());
        $this->assertSame(10.0, $this->a->fresh()->stock_qty);
        $this->get(route('stock-counts.show', $count))->assertOk()->assertSee('Dibatalkan')->assertDontSee('Setujui Opname');
    }

    public function test_locked_transactions_are_refused_with_the_clear_message_over_http(): void
    {
        $count = $this->review();
        $this->actingAs($this->sup);
        $this->post(route('docs.store', 'receiving'), ['supplier_id' => Supplier::first()->id, 'lines' => [['batch_id' => $this->a->id, 'qty' => 1, 'unit_cost' => 100]]])->assertRedirect();   // draf boleh
        $draft = StockDocument::latest('id')->firstOrFail();

        $this->put(route('docs.update', ['receiving', $draft]), ['supplier_id' => Supplier::first()->id, 'lines' => [['batch_id' => $this->a->id, 'qty' => 1, 'unit_cost' => 100]], '_action' => 'confirm'])->assertSessionHasErrors('lines');
        $this->assertStringContainsString("Stok Opname {$count->doc_no} sedang berjalan, transaksi dikunci. Hubungi Supervisor.", session('errors')->first('lines'));
        $this->assertSame('draft', $draft->fresh()->status);
    }
}
