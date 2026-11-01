<?php

namespace Tests\Feature;

use App\Enums\StockDocumentType;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockConsistency;
use App\Services\StockDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga akurasi buku besar: membatalkan pergerakan TERAKHIR mengembalikan saldo DAN biaya rata-rata persis seperti sebelumnya (tanpa selisih pembulatan 0,0001),
 *                dan nilai stok sesudah pembatalan tetap lolos pemeriksaan konsistensi.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class StockAccuracyTest extends TestCase
{
    use RefreshDatabase;

    private function book(StockDocuments $docs, User $by, StockDocumentType $type, Batch $b, float $qty, ?float $cost = null)
    {
        $header = $type === StockDocumentType::Receiving ? ['supplier_id' => Supplier::firstOrCreate(['code' => 'S1'], ['name' => 'S'])->id] : [];
        $doc = $docs->create($type, $header, [['batch_id' => $b->id, 'qty' => $qty, 'unit_cost' => $cost]], $by);
        $docs->confirm($doc, $by);

        return $doc;
    }

    public function test_cancelling_the_last_receipt_restores_the_exact_previous_average_cost(): void
    {
        $docs = app(StockDocuments::class);
        $sup = User::factory()->supervisor()->create();
        $b = $this->makeBatch(['code' => 'A1', 'name' => 'A', 'category_id' => Category::create(['code' => 'ATK', 'name' => 'ATK'])->id, 'unit' => 'pcs', 'unit_price' => 1]);
        $this->book($docs, $sup, StockDocumentType::Receiving, $b, 10, 10);
        $this->book($docs, $sup, StockDocumentType::Issuance, $b, 4);
        $second = $this->book($docs, $sup, StockDocumentType::Receiving, $b, 5, 20);   // (6 x 10 + 5 x 20) / 11 = 14,5455 (dibulatkan)
        $this->assertSame(14.5455, $b->fresh()->avg_cost);

        $docs->cancel($second, $sup, 'salah catat');

        $this->assertSame([6.0, 10.0], [$b->fresh()->stock_qty, $b->fresh()->avg_cost], 'hitung balik dari 14,5455 akan memberi 9,9999');
        $this->assertSame([], array_filter(StockConsistency::run()['problems']));
    }

    public function test_cancelling_an_earlier_receipt_after_a_later_movement_still_uses_the_formula_and_stays_consistent(): void
    {
        $docs = app(StockDocuments::class);
        $sup = User::factory()->supervisor()->create();
        $b = $this->makeBatch(['code' => 'A1', 'name' => 'A', 'category_id' => Category::create(['code' => 'ATK', 'name' => 'ATK'])->id, 'unit' => 'pcs', 'unit_price' => 1]);
        $first = $this->book($docs, $sup, StockDocumentType::Receiving, $b, 10, 10);
        $this->book($docs, $sup, StockDocumentType::Receiving, $b, 10, 20);   // rata-rata 15
        $this->book($docs, $sup, StockDocumentType::Issuance, $b, 5);

        $docs->cancel($first, $sup, 'salah catat');   // 15 stok -> tinggal 5 setelah menarik 10: (15 x 15 - 10 x 10) / 5 = 25

        $this->assertSame([5.0, 25.0], [$b->fresh()->stock_qty, $b->fresh()->avg_cost]);
        $this->assertSame([], array_filter(StockConsistency::run()['problems']));
    }
}
