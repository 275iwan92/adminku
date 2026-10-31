<?php

namespace Tests\Feature;

use App\Enums\StockDocumentType;
use App\Models\Category;
use App\Models\Batch;
use App\Models\Location;
use App\Models\StockCount;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockCounts;
use App\Services\StockDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga cetakan Stok Opname: Lembar Hitung (hitung buta tanpa stok sistem, urut per Lokasi lalu kode, kolom isian kosong) dan Berita Acara (ringkasan, baris selisih + alasan, tiga kotak tanda tangan, pratinjau saat Tinjau).
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class StockCountPrintTest extends TestCase
{
    use RefreshDatabase;

    private User $sup;

    private User $entry;

    /** @var array<string, Batch> */
    private array $items = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->sup = User::factory()->supervisor()->create(['name' => 'Sinta Supervisor']);
        $this->entry = User::factory()->create();
        Location::create(['code' => 'RAK-A', 'name' => 'Rak Depan']);
        Location::create(['code' => 'RAK-B', 'name' => 'Rak Belakang']);
        $cat = Category::create(['code' => 'ATK', 'name' => 'ATK']);
        $docs = app(StockDocuments::class);
        $supplier = Supplier::create(['code' => 'S1', 'name' => 'S']);
        // Urutan kode kebalikan dari urutan lokasi: Z1 di RAK-A harus tampil sebelum A1 di RAK-B.
        foreach ([['A1', 'RAK-B', 4321.5], ['Z1', 'RAK-A', 10], ['Y1', null, 3]] as [$code, $loc, $qty]) {
            $item = $this->makeBatch(['code' => $code, 'name' => "Barang {$code}", 'category_id' => $cat->id, 'unit' => 'pcs', 'unit_price' => 1000, 'location' => $loc]);
            $docs->confirm($docs->create(StockDocumentType::Receiving, ['supplier_id' => $supplier->id], [['batch_id' => $item->id, 'qty' => $qty, 'unit_cost' => 1000]], $this->sup), $this->sup);
            $this->items[$code] = $item;
        }
    }

    private function start(bool $blind = true): StockCount
    {
        return app(StockCounts::class)->start($this->sup, ['count_date' => today()->toDateString(), 'scope_type' => 'all', 'blind' => $blind, 'lock_transactions' => false, 'threshold_pct' => 5]);
    }

    private function lineId(StockCount $count, string $code): int
    {
        return $count->lines()->where('batch_id', $this->items[$code]->id)->value('id');
    }

    public function test_blind_sheet_has_no_system_stock_and_is_grouped_by_location_then_code(): void
    {
        $count = $this->start();
        $page = $this->actingAs($this->entry)->get(route('stock-counts.sheet', $count))->assertOk()->assertSee('Lembar Hitung Stok Opname')->assertSee($count->doc_no)->assertSee('Cakupan: Semua barang')
            ->assertSee('Hasil Hitung')->assertSee('Penghitung')->assertSee('Catatan')->assertSee('Batch (Kedaluwarsa)')->assertDontSee('Stok Sistem');
        $html = $page->getContent();
        $this->assertStringNotContainsString('4.321,5', $html);

        $order = [strpos($html, 'Lokasi RAK-A'), strpos($html, 'Barang Z1'), strpos($html, 'Lokasi RAK-B'), strpos($html, 'Barang A1'), strpos($html, 'Tanpa lokasi'), strpos($html, 'Barang Y1')];
        $this->assertNotContains(false, $order);
        $sorted = $order;
        sort($sorted);
        $this->assertSame($sorted, $order);   // RAK-A (Z1), RAK-B (A1), lalu tanpa lokasi (Y1)
        $this->assertStringContainsString('Rak Depan', $html);
    }

    public function test_open_count_sheet_shows_system_stock(): void
    {
        $count = $this->start(blind: false);

        $this->actingAs($this->sup)->get(route('stock-counts.sheet', $count))->assertOk()->assertSee('Stok Sistem')->assertSee('4.321,5');
    }

    public function test_minutes_are_supervisor_only_and_only_from_review_on(): void
    {
        $count = $this->start();
        $this->actingAs($this->sup)->get(route('stock-counts.report', $count))->assertStatus(422);   // masih Sedang dihitung
        $this->actingAs($this->entry)->get(route('stock-counts.report', $count))->assertForbidden();
        $this->get(route('stock-counts.show', $count))->assertDontSee('Berita Acara');
        $this->actingAs($this->sup)->get(route('stock-counts.show', $count))->assertDontSee('Berita Acara');
    }

    public function test_minutes_preview_and_final_have_summary_difference_rows_reasons_and_three_signature_boxes(): void
    {
        $counts = app(StockCounts::class);
        $count = $this->start();
        $counts->saveLine($count, $this->lineId($count, 'A1'), ['counted_qty' => 4300, 'counted_by' => 'Budi']);   // -21,5 (0,5%)
        $counts->saveLine($count, $this->lineId($count, 'Z1'), ['counted_qty' => 12, 'counted_by' => 'Sari']);     // +2 (20%)
        $counts->saveLine($count, $this->lineId($count, 'Y1'), ['counted_qty' => 3, 'counted_by' => 'Budi']);
        $counts->finish($count, $this->sup);
        $counts->reviewLine($count, $this->lineId($count, 'Z1'), ['reason' => 'Salah catat penerimaan']);

        $preview = $this->actingAs($this->sup)->get(route('stock-counts.report', $count))->assertOk()->assertSee('Berita Acara Stok Opname')->assertSee('PRATINJAU')
            ->assertSee('Sari, Budi')->assertSee('Penghitung')->assertSee('Supervisor')->assertSee('Penanggung Jawab Gudang')->assertSee('Salah catat penerimaan')
            ->assertSee('2 batch (1 lebih · 1 kurang)')->assertSee('(dibuat saat disetujui)');
        $this->assertSame(3, substr_count($preview->getContent(), '<i></i>'));   // tiga kotak tanda tangan: Penghitung, Supervisor, Penanggung Jawab Gudang
        $this->get(route('stock-counts.show', $count))->assertSee('Pratinjau Berita Acara');

        $counts->approve($count, $this->sup);
        $adj = $count->fresh()->adjustmentDocument;
        $final = $this->get(route('stock-counts.report', $count))->assertOk()->assertDontSee('PRATINJAU')->assertSee($adj->doc_no)->assertSee('Sinta Supervisor')->assertSee('Salah catat penerimaan');
        $this->assertSame(1, substr_count($final->getContent(), 'Penanggung Jawab Gudang'));
        $this->get(route('stock-counts.show', $count))->assertSee('Cetak Berita Acara');
    }

    public function test_minutes_without_any_difference_say_so_and_make_no_adjustment(): void
    {
        $counts = app(StockCounts::class);
        $count = $this->start();
        foreach (array_keys($this->items) as $code) {
            $counts->saveLine($count, $this->lineId($count, $code), ['counted_qty' => $this->items[$code]->fresh()->stock_qty]);
        }
        $counts->finish($count, $this->sup);
        $counts->approve($count, $this->sup);

        $this->actingAs($this->sup)->get(route('stock-counts.report', $count))->assertOk()->assertSee('Tidak ada selisih.')->assertSee('Tidak ada (tidak ada selisih)');
    }
}
