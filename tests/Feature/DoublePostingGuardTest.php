<?php

namespace Tests\Feature;

use App\Enums\StockDocumentType;
use App\Models\Category;
use App\Models\Batch;
use App\Models\StockDocument;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockDocuments;
use App\Services\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga agar dua permintaan Confirm / Cancel yang bersamaan tidak memposting atau membalik stok dua kali: status diperiksa ulang pada baris dokumen yang terkunci di awal transaksi,
 *                dan StockLedger::reverse() tidak membalik movement yang sudah punya pembalik.
 *                Catatan: SQLite (tes) tidak menguji kunci baris sungguhan; yang teruji di sini pemeriksaan ulang status dan idempotensi.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class DoublePostingGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $sup;

    private Batch $item;

    private StockDocuments $docs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sup = User::factory()->supervisor()->create();
        $cat = Category::create(['code' => 'ATK', 'name' => 'ATK']);
        $this->item = $this->makeBatch(['code' => 'G1', 'name' => 'Item G1', 'category_id' => $cat->id, 'unit' => 'pcs', 'unit_price' => 10, 'reorder_point' => 5, 'reorder_qty' => 20]);
        $this->docs = app(StockDocuments::class);
    }

    private function draftReceiving(float $qty): StockDocument
    {
        $supplier = Supplier::firstOrCreate(['code' => 'S1'], ['name' => 'S']);

        return $this->docs->create(StockDocumentType::Receiving, ['supplier_id' => $supplier->id], [['batch_id' => $this->item->id, 'qty' => $qty, 'unit_cost' => 10]], $this->sup);
    }

    private function confirmed(float $qty): StockDocument
    {
        $doc = $this->draftReceiving($qty);
        $this->docs->confirm($doc, $this->sup);

        return $doc->fresh();
    }

    private function assertRejected(callable $call): void
    {
        try {
            $call();
            $this->fail('Permintaan kedua seharusnya ditolak.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertSame('Aksi ini tidak diizinkan pada status sekarang.', $e->getMessage());
        }
    }

    public function test_confirm_twice_with_the_same_model_posts_once_and_the_second_is_rejected_with_the_status_message(): void
    {
        $doc = $this->draftReceiving(10);
        $stale = StockDocument::find($doc->id);          // model dimuat sebelum permintaan pertama selesai

        $this->docs->confirm($doc, $this->sup);
        $this->assertRejected(fn () => $this->docs->confirm($stale, $this->sup));

        $this->assertSame(10.0, $this->item->fresh()->stock_qty);
        $this->assertSame(1, StockMovement::where('stock_document_id', $doc->id)->count());
    }

    public function test_cancel_twice_with_the_same_model_reverses_once(): void
    {
        $doc = $this->confirmed(10);
        $stale = StockDocument::find($doc->id);

        $this->docs->cancel($doc, $this->sup, 'salah input');
        $this->assertRejected(fn () => $this->docs->cancel($stale, $this->sup, 'salah input'));

        $this->assertSame(0.0, $this->item->fresh()->stock_qty);
        $this->assertSame(1, StockMovement::where('stock_document_id', $doc->id)->whereNotNull('reversal_of')->count());
    }

    public function test_reverse_does_not_reverse_a_movement_that_already_has_a_reversal(): void
    {
        $doc = $this->confirmed(10);
        $ledger = app(StockLedger::class);

        $this->assertCount(1, $ledger->reverse($doc, $this->sup));
        $this->assertCount(0, $ledger->reverse($doc, $this->sup));      // dipanggil lagi: tidak ada yang tersisa untuk dibalik

        $this->assertSame(0.0, $this->item->fresh()->stock_qty);
        $this->assertSame(2, StockMovement::where('stock_document_id', $doc->id)->count());
    }

    public function test_a_document_whose_status_changed_after_loading_is_rejected_at_the_start_of_the_transaction(): void
    {
        $doc = $this->draftReceiving(10);
        $stale = StockDocument::find($doc->id);                          // masih draft di memori
        StockDocument::whereKey($doc->id)->update(['status' => 'cancelled']);   // diubah lewat query terpisah (permintaan lain)

        $this->assertRejected(fn () => $this->docs->confirm($stale, $this->sup));

        $this->assertSame(0.0, $this->item->fresh()->stock_qty);
        $this->assertSame(0, StockMovement::where('stock_document_id', $doc->id)->count());
    }
}
