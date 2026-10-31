<?php

namespace Tests\Feature;

use App\Enums\StockDocumentType;
use App\Models\Category;
use App\Models\Batch;
use App\Models\StockCount;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\StockCounts;
use App\Services\StockDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga layar input hasil hitung Stok Opname: simpan per sel lewat permintaan JSON (kosong / 0 / Tidak dihitung, sebagian, beberapa penghitung), hitung buta tidak membocorkan stok sistem,
 *                Selesai hitung menolak baris yang belum, Tambah barang, hak Data Entry / RO.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class StockCountEntryTest extends TestCase
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
        $this->a = $this->makeBatch(['code' => 'A1', 'name' => 'Pensil', 'category_id' => $cat->id, 'unit' => 'pcs', 'unit_price' => 100, 'location' => 'RAK-A']);
        $this->b = $this->makeBatch(['code' => 'B1', 'name' => 'Penghapus', 'category_id' => $cat->id, 'unit' => 'pcs', 'unit_price' => 100, 'location' => 'RAK-B']);
        $docs = app(StockDocuments::class);
        $supplier = Supplier::create(['code' => 'S1', 'name' => 'S']);
        foreach ([[$this->a, 12345.5], [$this->b, 7]] as [$item, $qty]) {
            $docs->confirm($docs->create(StockDocumentType::Receiving, ['supplier_id' => $supplier->id], [['batch_id' => $item->id, 'qty' => $qty, 'unit_cost' => 100]], $this->sup), $this->sup);
        }
    }

    private function start(array $over = []): StockCount
    {
        return app(StockCounts::class)->start($this->sup, $over + ['count_date' => today()->toDateString(), 'scope_type' => 'all', 'blind' => true, 'lock_transactions' => false, 'threshold_pct' => 5]);
    }

    private function lineId(StockCount $count, Batch $item): int
    {
        return $count->lines()->where('batch_id', $item->id)->value('id');
    }

    private function save(StockCount $count, array $rows)
    {
        return $this->postJson(route('stock-counts.lines', $count), ['changed' => $rows]);
    }

    public function test_blind_count_never_sends_system_stock_to_the_browser(): void
    {
        $count = $this->start();

        $page = $this->actingAs($this->entry)->get(route('stock-counts.show', $count))->assertOk()->assertSee('Hasil Hitung')->assertDontSee('System Stock')->assertDontSee('Stok Sistem');
        $this->assertStringNotContainsString('12345.5', $page->getContent());
        $this->assertStringNotContainsString('system_qty', $page->getContent());

        $json = $this->save($count, [['id' => $this->lineId($count, $this->a), 'counted_qty' => 12000]])->assertOk()->json('rows');
        $this->assertArrayNotHasKey('system_qty', $json[0]);
        $this->assertStringNotContainsString('12345.5', json_encode($json));

        $this->getJson(route('devlib.lookup', 'item_count').'?q=Pensil')->assertOk()->assertJsonMissing(['stock' => 12345.5]);
    }

    public function test_open_count_shows_the_live_difference_per_row_after_each_save(): void
    {
        $count = $this->start(['blind' => false]);
        $rows = collect($this->actingAs($this->entry)->save($count, [['id' => $this->lineId($count, $this->b), 'counted_qty' => 5]])->assertOk()->json('rows'))->keyBy('id');

        $this->assertSame([-2, 7], [$rows[$this->lineId($count, $this->b)]['diff'], $rows[$this->lineId($count, $this->b)]['system_qty']]);   // 5 - 7
        $this->assertSame('', $rows[$this->lineId($count, $this->a)]['diff']);   // belum dihitung: tanpa selisih
        $this->get(route('stock-counts.show', $count))->assertSee('Selisih');
    }

    public function test_open_count_shows_system_stock_to_everyone(): void
    {
        $count = $this->start(['blind' => false]);

        $page = $this->actingAs($this->entry)->get(route('stock-counts.show', $count))->assertOk()->assertSee('Stok Sistem');
        $this->assertStringContainsString('12345.5', $page->getContent());
    }

    public function test_data_entry_saves_blank_zero_and_skipped_per_row_and_two_people_fill_different_rows(): void
    {
        $count = $this->start();
        [$a, $b] = [$this->lineId($count, $this->a), $this->lineId($count, $this->b)];

        $rows = $this->actingAs($this->entry)->save($count, [['id' => $a, 'counted_qty' => 0, 'counted_by' => 'Budi']])->assertOk()->json('rows');
        $byId = collect($rows)->keyBy('id');
        $this->assertSame([0, 'Budi', 'yes', 'no'], [$byId[$a]['counted_qty'], $byId[$a]['counted_by'], $byId[$a]['filled'], $byId[$b]['filled']]);   // 0 = terisi, kosong = belum
        $this->assertSame('', $byId[$b]['counted_qty']);

        $other = User::factory()->create();
        $this->actingAs($other)->save($count, [['id' => $b, 'counted_qty' => 6.5, 'counted_by' => 'Sari']])->assertOk();
        $this->assertSame([0.0, 6.5], [$count->lines()->find($a)->counted_qty, $count->lines()->find($b)->counted_qty]);

        $this->save($count, [['id' => $a, 'counted_qty' => '']])->assertOk();   // dikosongkan = belum dihitung lagi
        $this->assertNull($count->lines()->find($a)->counted_qty);

        $this->save($count, [['id' => $a, 'skipped' => true, 'note' => 'rak dikunci']])->assertOk();
        $this->assertSame([true, 'rak dikunci'], [$count->lines()->find($a)->skipped, $count->lines()->find($a)->note]);
        $this->save($count, [['id' => $a, 'counted_qty' => -2]])->assertStatus(422);
    }

    public function test_counter_name_is_remembered_for_the_next_rows_in_the_same_session(): void
    {
        $count = $this->start();
        $this->actingAs($this->entry)->save($count, [['id' => $this->lineId($count, $this->a), 'counted_qty' => 5, 'counted_by' => 'Budi']])->assertOk();
        $this->save($count, [['id' => $this->lineId($count, $this->b), 'counted_qty' => 3]])->assertOk();

        $this->assertSame('Budi', $count->lines()->find($this->lineId($count, $this->b))->counted_by);
    }

    public function test_finish_is_refused_with_the_open_rows_listed_then_succeeds_and_locks_the_sheet(): void
    {
        $count = $this->start();
        $this->actingAs($this->entry)->save($count, [['id' => $this->lineId($count, $this->a), 'counted_qty' => 1]]);

        $this->post(route('stock-counts.finish', $count))->assertSessionHasErrors('lines');
        $this->assertStringContainsString('B1', session('errors')->first('lines'));
        $this->assertSame('counting', $count->fresh()->status);

        $this->save($count, [['id' => $this->lineId($count, $this->b), 'skipped' => true]]);
        $this->post(route('stock-counts.finish', $count))->assertSessionHasErrors('lines');   // Tidak dihitung tanpa catatan

        $this->save($count, [['id' => $this->lineId($count, $this->b), 'skipped' => true, 'note' => 'rusak']]);
        $this->post(route('stock-counts.finish', $count))->assertRedirect();
        $this->assertSame('review', $count->fresh()->status);

        $this->save($count, [['id' => $this->lineId($count, $this->a), 'counted_qty' => 9]])->assertStatus(422);   // sesudah Selesai hitung tidak bisa diubah
        $this->get(route('stock-counts.show', $count))->assertOk()->assertSee('hanya bisa dibaca');
    }

    public function test_add_item_found_on_the_floor_takes_current_stock(): void
    {
        $count = $this->start(['scope_type' => 'location', 'scope_value' => 'RAK-A']);
        $this->assertSame(1, $count->lines()->count());

        $this->actingAs($this->entry)->post(route('stock-counts.items', $count), ['batch_id' => $this->b->id])->assertRedirect();
        $line = $count->lines()->where('batch_id', $this->b->id)->firstOrFail();
        $this->assertSame([7.0, true], [$line->system_qty, $line->added_manually]);

        $this->post(route('stock-counts.items', $count), ['batch_id' => $this->b->id])->assertSessionHasErrors('batch_id');   // sudah ada
        $this->post(route('stock-counts.items', $count), [])->assertSessionHasErrors('batch_id');
    }

    public function test_read_only_group_cannot_save_or_finish(): void
    {
        $count = $this->start();
        $group = UserGroup::create(['code' => 'RO', 'name' => 'Lihat']);
        $group->syncAccess(['SO' => 'ro']);
        $viewer = User::factory()->create(['group_id' => $group->id]);

        $this->actingAs($viewer)->get(route('stock-counts.show', $count))->assertOk();
        $this->save($count, [['id' => $this->lineId($count, $this->a), 'counted_qty' => 1]])->assertForbidden();
        $this->post(route('stock-counts.finish', $count))->assertForbidden();
        $this->assertNull($count->lines()->find($this->lineId($count, $this->a))->counted_qty);
    }
}
