<?php

namespace Tests\Feature;

use App\Enums\StockDocumentType;
use App\Models\Category;
use App\Models\Batch;
use App\Models\Product;
use App\Models\StockDocument;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockDocuments;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga nomor dokumen dan pembuatan batch dari balapan: penyimpan yang kalah di kolom unik mengulang (nomor berikutnya / memakai batch yang sudah ada), bukan galat 500.
 *                Tabrakan disimulasikan lewat penyimpan lain yang menyelip antara baca dan simpan; SQLite tidak menguji kunci / konkurensi sungguhan.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class NumberingRaceTest extends TestCase
{
    use RefreshDatabase;

    private function receiving(): StockDocument
    {
        $user = User::factory()->create();
        $item = $this->makeBatch(['code' => 'R'.(Batch::count() + 1), 'name' => 'Item R', 'category_id' => Category::firstOrCreate(['code' => 'ATK'], ['name' => 'ATK'])->id, 'unit' => 'pcs', 'unit_price' => 10, 'reorder_point' => 1, 'reorder_qty' => 5]);

        return app(StockDocuments::class)->create(StockDocumentType::Receiving, ['supplier_id' => Supplier::firstOrCreate(['code' => 'S1'], ['name' => 'S'])->id], [['batch_id' => $item->id, 'qty' => 1, 'unit_cost' => 10]], $user);
    }

    // Penyimpan lain menyelip tepat setelah SELECT kita (di luar savepoint kita, seperti transaksi lain yang sudah commit): $inject jalan sekali.
    private function interloperAfterSelect(string $table, callable $inject): void
    {
        $done = false;
        DB::listen(function (QueryExecuted $query) use (&$done, $table, $inject) {
            if (! $done && str_starts_with($query->sql, 'select') && str_contains($query->sql, '"'.$table.'"')) {
                $done = true;
                $inject($query);
            }
        });
    }

    public function test_document_number_collision_takes_the_next_number_instead_of_failing(): void
    {
        $first = $this->receiving();
        $this->interloperAfterSelect('rm_stock', function (QueryExecuted $query) use ($first) {
            $row = $first->getAttributes();
            unset($row['id']);
            DB::table('rm_stock')->insert(['doc_no' => rtrim($query->bindings[0], '%').'0002'] + $row);
        });

        $second = $this->receiving();   // penyelip mengambil 0002; INSERT kita bertabrakan, lalu mengulang

        $this->assertSame(substr($first->doc_no, 0, -4).'0003', $second->doc_no);
        $this->assertSame(3, StockDocument::count());
        $this->assertSame(3, StockDocument::distinct()->count('doc_no'));
    }

    public function test_document_number_is_unchanged_without_a_collision(): void
    {
        $this->assertMatchesRegularExpression('/^RCV-\d{4}-0001$/', $this->receiving()->doc_no);
        $this->assertMatchesRegularExpression('/^RCV-\d{4}-0002$/', $this->receiving()->doc_no);
    }

    public function test_batch_collision_reuses_the_batch_created_by_the_other_saver(): void
    {
        $product = Product::register(['name' => 'Obat', 'category_id' => Category::create(['code' => 'OBT', 'name' => 'Obat'])->id, 'unit' => 'pcs', 'unit_price' => 1, 'tracks_expiry' => true], 'OBT-0001');
        $this->interloperAfterSelect('i_batch', function () use ($product) {
            $row = $product->batches()->make(['code' => 'OBT-0001-271231', 'expiry_date' => '2027-12-31'] + $product->only(Product::BATCH_COPY))->getAttributes();
            DB::table('i_batch')->insert($row + ['product_id' => $product->id, 'created_at' => now(), 'updated_at' => now()]);
        });

        $batch = $product->batchFor('2027-12-31');

        $this->assertSame('OBT-0001-271231', $batch->code);
        $this->assertSame(1, Batch::where('code', 'OBT-0001-271231')->count());
        $this->assertSame($batch->id, $product->batchFor('2027-12-31')->id);
    }
}
