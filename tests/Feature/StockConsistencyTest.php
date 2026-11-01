<?php

namespace Tests\Feature;

use App\Enums\StockDocumentType;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Department;
use App\Models\Recipient;
use App\Models\StockDocument;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockConsistency;
use App\Services\StockDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga pemeriksaan konsistensi stok (sstock:check-stock): setelah alur penuh (terima, keluar, penyesuaian, pembatalan) saldo batch = buku besar, biaya rata-rata dan nilai cocok, dokumen cocok dengan pergerakan;
 *                data yang dirusak (saldo batch, buku besar, biaya) terdeteksi dan dilaporkan tanpa diperbaiki.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class StockConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private StockDocuments $docs;

    private User $sup;

    private Batch $pen;

    private int $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->docs = app(StockDocuments::class);
        $this->sup = User::factory()->supervisor()->create();
        $this->pen = $this->makeBatch(['code' => 'ATK-0001', 'name' => 'Pulpen', 'category_id' => Category::create(['code' => 'ATK', 'name' => 'ATK'])->id, 'unit' => 'pcs', 'unit_price' => 10]);
        $this->supplier = Supplier::create(['code' => 'S1', 'name' => 'S'])->id;
    }

    private function book(StockDocumentType $type, array $header, array $lines): StockDocument
    {
        $doc = $this->docs->create($type, $header, $lines, $this->sup);
        if ($type === StockDocumentType::Adjustment) {
            $this->docs->submit($doc);
            $this->docs->approve($doc, $this->sup);
        } else {
            $this->docs->confirm($doc, $this->sup);
        }

        return $doc;
    }

    private function fullFlow(): void
    {
        $this->book(StockDocumentType::Receiving, ['supplier_id' => $this->supplier], [['batch_id' => $this->pen->id, 'qty' => 10, 'unit_cost' => 10.3333]]);
        $second = $this->book(StockDocumentType::Receiving, ['supplier_id' => $this->supplier], [['batch_id' => $this->pen->id, 'qty' => 7.5, 'unit_cost' => 21.7777]]);
        $recipient = Recipient::create(['name' => 'R', 'department_id' => Department::create(['code' => 'D', 'name' => 'D'])->id]);
        $this->book(StockDocumentType::Issuance, ['recipient_id' => $recipient->id], [['batch_id' => $this->pen->id, 'qty' => 4.25]]);
        $this->book(StockDocumentType::Adjustment, ['reason' => 'Opname'], [['batch_id' => $this->pen->id, 'counted_qty' => 20]]);
        $this->docs->cancel($second, $this->sup, 'salah catat');   // Supervisor: dibalik langsung
    }

    public function test_a_full_flow_with_a_cancellation_is_consistent(): void
    {
        $this->fullFlow();

        $result = StockConsistency::run();

        $this->assertSame(array_fill_keys(array_keys($result['problems']), []), $result['problems']);
        $this->assertGreaterThan(0, $result['summary']['pergerakan']);
    }

    public function test_the_command_succeeds_when_consistent_and_fails_when_a_batch_balance_was_tampered_with(): void
    {
        $this->fullFlow();
        $this->artisan('sstock:check-stock')->assertSuccessful();

        DB::table('i_batch')->where('id', $this->pen->id)->update(['stock_qty' => 99]);

        $this->artisan('sstock:check-stock')->assertFailed();
        $problems = StockConsistency::run()['problems'];
        $this->assertNotEmpty($problems['saldo']);
        $this->assertNotEmpty($problems['jumlah']);
        $this->assertSame(99.0, $this->pen->fresh()->stock_qty, 'pemeriksaan tidak memperbaiki data');
    }

    public function test_a_broken_ledger_chain_and_a_wrong_average_cost_are_reported(): void
    {
        $this->fullFlow();
        $id = DB::table('rm_stockcard')->orderBy('id')->skip(1)->value('id');
        DB::table('rm_stockcard')->where('id', $id)->update(['balance_qty' => 123, 'balance_avg_cost' => 1]);

        $problems = StockConsistency::run()['problems'];

        $this->assertNotEmpty($problems['rantai']);
        $this->assertNotEmpty($problems['biaya']);
    }

    public function test_a_document_whose_movements_do_not_match_its_lines_is_reported(): void
    {
        $this->fullFlow();
        $first = StockDocument::where('type', StockDocumentType::Receiving)->orderBy('id')->first();
        DB::table('rm_stock_line')->where('stock_document_id', $first->id)->update(['qty' => 11]);

        $this->assertNotEmpty(StockConsistency::run()['problems']['dokumen']);
    }
}
