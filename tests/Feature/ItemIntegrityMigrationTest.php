<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Batch;
use App\Models\Item;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga migrasi integritas items: unik (product_id, expiry_date), indeks expiry_date, dan penolakan aman bila data lama melanggar (stok negatif / batch ganda) tanpa mengubah data.
 *                Catatan: CHECK stock_qty >= 0 hanya dipasang di MySQL / MariaDB dan tidak teruji di sini (tes memakai SQLite).
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class ItemIntegrityMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const FILE = 'database/migrations/2026_11_09_100000_add_integrity_constraints_to_items.php';

    private function product(): Item
    {
        return Item::register(['name' => 'Obat', 'category_id' => Category::firstOrCreate(['code' => 'OBT'], ['name' => 'Obat'])->id, 'unit' => 'pcs', 'unit_price' => 1, 'tracks_expiry' => true], 'OBT-0001');
    }

    private function migration(): object
    {
        return require base_path(self::FILE);
    }

    // Migrasi lama menyebut tabel dan kolom dengan nama lama (items, product_id); selama dijalankan, i_batch dinamai sementara items dan item_id dinamai product_id.
    private function legacy(callable $run): void
    {
        Schema::rename('i_batch', 'items');
        Schema::table('items', fn ($t) => $t->renameColumn('item_id', 'product_id'));
        try {
            $run();
        } finally {
            Schema::table('items', fn ($t) => $t->renameColumn('product_id', 'item_id'));
            Schema::rename('items', 'i_batch');
        }
    }

    private function dropIndexes(): void
    {
        Schema::table('items', function ($table) {
            $table->dropIndex(['expiry_date']);
            $table->dropUnique(['product_id', 'expiry_date']);
        });
    }

    private function copyOf(Batch $item, string $code): array
    {
        $row = $item->getAttributes();
        unset($row['id']);

        return ['code' => $code] + $row;
    }

    public function test_indexes_exist_and_one_product_has_one_batch_per_expiry_date(): void
    {
        $this->assertTrue(Schema::hasIndex('i_batch', ['expiry_date']));
        $this->assertTrue(Schema::hasIndex('i_batch', ['item_id', 'expiry_date'], 'unique'));

        $product = $this->product();
        $batch = $product->batchFor('2027-01-31');
        $this->assertSame($batch->id, $product->batchFor('2027-01-31')->id);   // dua lot bertanggal sama menyatu

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('i_batch')->insert($this->copyOf($batch, 'LAIN'));
    }

    public function test_undated_batches_may_repeat_since_null_is_not_unique(): void
    {
        $product = $this->product();
        $undated = $product->batches()->create($product->only(Item::BATCH_COPY) + ['code' => 'X-1', 'expiry_date' => null]);
        DB::table('i_batch')->insert($this->copyOf($undated, 'X-2'));

        $this->assertSame(2, Batch::where('item_id', $product->id)->whereNull('expiry_date')->count());
    }

    public function test_migration_stops_with_a_clear_message_and_keeps_data_when_a_batch_is_duplicated(): void
    {
        $product = $this->product();
        $batch = $product->batchFor('2027-02-28');
        $this->legacy(fn () => $this->dropIndexes());
        DB::table('i_batch')->insert($this->copyOf($batch, 'OBT-0001-DUP'));

        try {
            $this->legacy(fn () => $this->migration()->up());
            $this->fail('Migrasi seharusnya berhenti.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString("batch ganda: produk #{$product->id} tanggal kedaluwarsa", $e->getMessage());
            $this->assertStringContainsString('data tidak diubah', $e->getMessage());
        }
        $this->assertSame(2, Batch::where('item_id', $product->id)->count());   // tidak ada yang dihapus
        $this->assertFalse(Schema::hasIndex('i_batch', ['expiry_date']));          // dan tidak ada indeks yang setengah terpasang
    }

    public function test_migration_stops_when_a_stock_qty_is_negative(): void
    {
        $item = $this->product()->batchFor('2027-03-31');
        $this->legacy(fn () => $this->dropIndexes());
        DB::table('i_batch')->where('id', $item->id)->update(['stock_qty' => -2]);

        try {
            $this->legacy(fn () => $this->migration()->up());
            $this->fail('Migrasi seharusnya berhenti.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString("stok negatif: item #{$item->id} {$item->code}", $e->getMessage());
        }
        $this->assertEquals(-2, DB::table('i_batch')->where('id', $item->id)->value('stock_qty'));   // tidak diubah
    }

    public function test_migration_applies_cleanly_on_existing_valid_data_and_rolls_back(): void
    {
        $this->product()->batchFor('2027-04-30');
        $migration = $this->migration();
        $this->legacy(fn () => $migration->down());
        $this->assertFalse(Schema::hasIndex('i_batch', ['expiry_date']));

        $this->legacy(fn () => $migration->up());

        $this->assertTrue(Schema::hasIndex('i_batch', ['expiry_date']));
        $this->assertTrue(Schema::hasIndex('i_batch', ['item_id', 'expiry_date'], 'unique'));
        $this->assertSame(1, Batch::count());
    }
}
