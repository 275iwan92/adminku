<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\StockDocument;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga data contoh tetap bisa dibuat ulang setelah skema berubah (kolom ditambah / dihapus): DatabaseSeeder harus jalan bersih, bisa diulang tanpa data ganda, dan stok hasilnya cocok dengan buku besar.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class SeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_runs_twice_without_duplicates_and_stock_matches_ledger(): void
    {
        $this->seed(DatabaseSeeder::class);
        $items = Batch::count();
        $docs = StockDocument::count();
        $this->assertGreaterThan(0, $items);
        $this->assertGreaterThan(0, $docs);

        $this->seed(DatabaseSeeder::class);
        $this->assertSame($items, Batch::count());
        $this->assertSame($docs, StockDocument::count());

        foreach (Batch::with('movements')->get() as $item) {
            $this->assertEqualsWithDelta($item->stock_qty, $item->movements->sum('qty'), 0.001, $item->code);
        }
    }
}
