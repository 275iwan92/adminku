<?php

namespace Tests\Feature;

use App\Enums\StockDocumentType;
use App\Models\Category;
use App\Models\Batch;
use App\Models\StockCount;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockCounts;
use App\Services\StockDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga tiga pelengkap Stok Opname: baris selisih besar berwarna (kolom tone, tidak bocor saat hitung buta), tombol Simpan Cadangan (stock-counts.save membandingkan baris terisi di layar dengan database),
 *                dan Perbarui SATU baris bergeser dari layar tinjau (stock-counts.refresh-line, hanya Supervisor, hanya baris yang bergeser).
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class StockCountPolishTest extends TestCase
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
        foreach ([[$this->a, 10], [$this->b, 20]] as [$item, $qty]) {
            $docs->confirm($docs->create(StockDocumentType::Receiving, ['supplier_id' => $supplier->id], [['batch_id' => $item->id, 'qty' => $qty, 'unit_cost' => 100]], $this->sup), $this->sup);
        }
    }

    private function start(bool $blind, bool $lock = false): StockCount
    {
        return app(StockCounts::class)->start($this->sup, ['count_date' => today()->toDateString(), 'scope_type' => 'all', 'blind' => $blind, 'lock_transactions' => $lock, 'threshold_pct' => 5]);
    }

    private function lineId(StockCount $count, Batch $item): int
    {
        return $count->lines()->where('batch_id', $item->id)->value('id');
    }

    private function fill(StockCount $count, float $a, float $b): void
    {
        $counts = app(StockCounts::class);
        $counts->saveLine($count, $this->lineId($count, $this->a), ['counted_qty' => $a]);
        $counts->saveLine($count, $this->lineId($count, $this->b), ['counted_qty' => $b]);
    }

    private function rows(string $html): Collection
    {
        // baris grid = "rows" di konfigurasi komponen (data-dl2-config) yang memuatnya
        preg_match_all('/data-dl2-config="([^"]*)"/', $html, $all);
        foreach ($all[1] as $cfg) {
            $j = json_decode(html_entity_decode($cfg), true);
            if (is_array($j) && isset($j['rows'])) {
                return collect($j['rows'])->keyBy('code');
            }
        }

        return collect();
    }

    public function test_large_differences_are_tinted_on_the_entry_sheet_but_never_on_a_blind_count(): void
    {
        $open = $this->start(blind: false);
        $this->fill($open, 8, 20);   // A1 -20% (besar), B1 sama
        $html = $this->actingAs($this->entry)->get(route('stock-counts.show', $open))->assertOk()->assertSee('&quot;rowTone&quot;:&quot;tone&quot;', false)->getContent();
        $rows = $this->rows($html);
        $this->assertSame(['danger', ''], [$rows['A1']['tone'], $rows['B1']['tone']]);

        $open->update(['status' => 'cancelled']);
        $blind = $this->start(blind: true);
        $this->fill($blind, 8, 20);
        $html = $this->get(route('stock-counts.show', $blind))->assertOk()->assertDontSee('&quot;rowTone&quot;:&quot;tone&quot;', false)->getContent();
        $this->assertArrayNotHasKey('tone', $this->rows($html)['A1']);
    }

    public function test_large_differences_are_tinted_on_the_review_sheet(): void
    {
        $count = $this->start(blind: true);
        $this->fill($count, 8, 20);
        app(StockCounts::class)->finish($count, $this->entry);

        $html = $this->actingAs($this->sup)->get(route('stock-counts.show', $count))->assertOk()->assertSee('&quot;rowTone&quot;:&quot;tone&quot;', false)->getContent();
        $rows = $this->rows($html);
        $this->assertSame(['danger', ''], [$rows['A1']['tone'], $rows['B1']['tone']]);
    }

    public function test_save_backup_confirms_when_the_screen_matches_the_server_and_warns_when_it_does_not(): void
    {
        $count = $this->start(blind: true);
        $this->fill($count, 8, 20);
        $this->actingAs($this->entry);

        $this->postJson(route('stock-counts.save', $count), ['seen' => 2])->assertOk()->assertJsonPath('message', fn ($m) => str_contains($m, '2 dari 2 baris sudah lengkap'));
        $this->postJson(route('stock-counts.save', $count), ['seen' => 1])->assertStatus(409)->assertJsonPath('error', fn ($m) => str_contains($m, 'Layar menunjukkan 1 baris terisi, di server 2'));
        $this->postJson(route('stock-counts.save', $count))->assertStatus(409);

        app(StockCounts::class)->finish($count, $this->entry);
        $this->postJson(route('stock-counts.save', $count), ['seen' => 2])->assertStatus(422);
    }

    public function test_save_backup_button_is_on_the_entry_sheet_only_while_counting(): void
    {
        $count = $this->start(blind: true);
        $this->actingAs($this->entry)->get(route('stock-counts.show', $count))->assertOk()->assertSee('data-count-save', false)->assertSee('Simpan Cadangan');

        $this->fill($count, 8, 20);
        app(StockCounts::class)->finish($count, $this->entry);
        $this->get(route('stock-counts.show', $count))->assertOk()->assertDontSee('data-count-save', false);
    }

    public function test_one_moved_line_can_be_refreshed_alone(): void
    {
        $count = $this->start(blind: true);
        $this->fill($count, 8, 15);   // A1 -2, B1 -5: keduanya selisih
        app(StockCounts::class)->finish($count, $this->entry);
        $docs = app(StockDocuments::class);
        foreach ([$this->a, $this->b] as $item) {
            $docs->confirm($docs->create(StockDocumentType::Receiving, ['supplier_id' => Supplier::first()->id], [['batch_id' => $item->id, 'qty' => 3, 'unit_cost' => 100]], $this->sup), $this->sup);   // keduanya bergeser
        }
        $a = $this->lineId($count, $this->a);
        $b = $this->lineId($count, $this->b);

        $this->actingAs($this->sup)->get(route('stock-counts.show', $count))->assertOk()->assertSee('Perbarui A1')->assertSee('Perbarui B1')->assertSee('Perbarui Baris Bergeser');

        $this->post(route('stock-counts.refresh-line', [$count, $a]))->assertRedirect(route('stock-counts.show', $count))->assertSessionHas('status', fn ($s) => str_starts_with($s, 'A1 diperbarui'));
        $lineA = $count->lines()->find($a);
        $lineB = $count->lines()->find($b);
        $this->assertSame('counting', $count->fresh()->status);
        $this->assertSame([13.0, null], [$lineA->system_qty, $lineA->counted_qty]);   // A1: stok sekarang, dikosongkan
        $this->assertSame([20.0 + 3, 15.0], [$this->b->fresh()->stock_qty, $lineB->counted_qty]);   // B1 tidak disentuh
        $this->assertSame(20.0, $lineB->system_qty);
    }

    public function test_refresh_one_line_rejects_a_line_that_did_not_move_and_foreign_lines_and_non_supervisors(): void
    {
        $count = $this->start(blind: true);
        $this->fill($count, 8, 20);
        app(StockCounts::class)->finish($count, $this->entry);
        $a = $this->lineId($count, $this->a);

        $this->actingAs($this->entry)->post(route('stock-counts.refresh-line', [$count, $a]))->assertForbidden();

        $this->actingAs($this->sup)->post(route('stock-counts.refresh-line', [$count, $a]))->assertStatus(422);   // tidak bergeser
        $this->assertSame([10.0, 8.0, 'review'], [$count->lines()->find($a)->system_qty, $count->lines()->find($a)->counted_qty, $count->fresh()->status]);

        $this->post(route('stock-counts.refresh-line', [$count, 999999]))->assertNotFound();   // baris yang bukan milik opname ini
    }
}
