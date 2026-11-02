<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Batch;
use App\Models\Recipient;
use App\Models\ReorderOrder;
use App\Models\StockDocument;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga info pemesanan ulang pada peringatan stok menipis: tanggal pesan, ref, pencatat, dan "diterima sejak dipesan" (hanya Penerimaan terkonfirmasi yang tidak dibatalkan sejak tanggal pesan).
 *                Hanya penanda: aturan penutupan pesanan dan perhitungan peringatan tidak berubah; jumlah yang dipesan tidak tercatat dan tidak ditampilkan.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class ReorderOrderInfoTest extends TestCase
{
    use RefreshDatabase;

    private User $sup;

    private Batch $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sup = User::factory()->supervisor()->create(['name' => 'Sari Supervisor']);
        $this->actingAs($this->sup);
        $this->item = $this->makeBatch(['code' => 'RO-1', 'name' => 'Kertas HVS', 'category_id' => Category::create(['code' => 'ATK', 'name' => 'ATK'])->id, 'unit' => 'pcs', 'unit_price' => 10, 'reorder_point' => 5, 'reorder_qty' => 20]);
    }

    private function receive(float $qty): StockDocument
    {
        $payload = ['supplier_id' => Supplier::firstOrCreate(['code' => 'S1'], ['name' => 'S'])->id, 'lines' => [['batch_id' => $this->item->id, 'qty' => $qty, 'unit_cost' => 10]]];
        $this->post(route('docs.store', 'receiving'), $payload)->assertSessionHasNoErrors();
        $doc = StockDocument::latest('id')->firstOrFail();
        $this->put(route('docs.update', ['receiving', $doc]), $payload + ['_action' => 'confirm'])->assertSessionHasNoErrors();

        return $doc->fresh();
    }

    private function issue(float $qty): void
    {
        $payload = ['recipient_id' => Recipient::firstOrCreate(['name' => 'Budi'])->id, 'lines' => [['batch_id' => $this->item->id, 'qty' => $qty]]];
        $this->post(route('docs.store', 'issuance'), $payload)->assertSessionHasNoErrors();
        $this->put(route('docs.update', ['issuance', StockDocument::latest('id')->firstOrFail()]), $payload + ['_action' => 'confirm'])->assertSessionHasNoErrors();
    }

    // Stok akhir 2 (di bawah titik 5) dengan: 10 diterima sesudah tanggal pesan, 4 diterima lalu dibatalkan, 3 diterima SEBELUM tanggal pesan. Pesanan dicatat belakangan, jadi masih terbuka.
    private function scenario(): ReorderOrder
    {
        $before = $this->receive(3);
        $when = now()->subDays(5);
        StockMovement::where('stock_document_id', $before->id)->update(['moved_at' => $when]);
        $before->update(['doc_at' => $when]);

        $this->receive(10);
        $cancelled = $this->receive(4);
        $this->put(route('docs.update', ['receiving', $cancelled]), ['_action' => 'cancel', '_reason' => 'salah catat'])->assertSessionHasNoErrors();
        $this->issue(11);
        $this->assertSame(2.0, $this->item->fresh()->stock_qty);

        return ReorderOrder::create(['item_id' => $this->item->item_id, 'ordered_at' => today()->subDays(2), 'ref_no' => 'PO-77', 'created_by' => $this->sup->id]);
    }

    public function test_the_note_shows_date_ref_who_and_what_was_received_since_ordering(): void
    {
        $order = $this->scenario();
        $expected = 'Sudah dicatat dipesan pada '.today()->subDays(2)->format('d/m/Y').', ref PO-77 oleh Sari Supervisor. Diterima sejak dipesan: 10 pcs.';   // 3 (sebelum pesan) dan 4 (dibatalkan) tidak dihitung

        $this->assertSame($expected, $order->note());
        $this->assertSame(10.0, $order->receivedSince());
    }

    public function test_home_shows_the_note_for_an_order_recorded_without_quantity(): void
    {
        $this->scenario();

        $html = $this->get(route('stock.home'))->assertOk()->getContent();
        $this->assertStringContainsString('Sudah dicatat dipesan pada '.today()->subDays(2)->format('d/m/Y').', ref PO-77 oleh Sari Supervisor. Diterima sejak dipesan: 10 pcs.', $html);
        $this->assertStringNotContainsString('Sudah dipesan ', $html);   // baris lama tanpa jumlah / supplier: bagian itu dilewati
    }

    public function test_the_summary_report_and_the_product_form_show_the_same_note(): void
    {
        $this->scenario();
        $note = 'ref PO-77 oleh Sari Supervisor. Diterima sejak dipesan: 10 pcs.';

        $this->get(route('reports.summary', ['from' => today()->subDays(7)->toDateString(), 'to' => today()->toDateString()]))->assertOk()->assertSee($note, false)->assertSee('Info Pemesanan', false);
        $this->get(route('products.show', $this->item->item))->assertOk()->assertSee($note, false);
    }

    public function test_an_item_above_the_reorder_point_or_without_an_open_order_shows_no_note(): void
    {
        $this->receive(10);   // 10 > titik 5: tidak ada peringatan
        ReorderOrder::create(['item_id' => $this->item->item_id, 'ordered_at' => today(), 'ref_no' => 'PO-1', 'created_by' => $this->sup->id]);

        $this->get(route('stock.home'))->assertOk()->assertDontSee('Sudah dicatat dipesan', false);
        $this->get(route('products.show', $this->item->item))->assertOk()->assertDontSee('Sudah dicatat dipesan', false);

        $this->issue(8);   // sekarang 2 <= 5, tetapi pesanan di atas sudah ada (terbuka)
        $this->get(route('stock.home'))->assertSee('Sudah dicatat dipesan', false);

        ReorderOrder::query()->update(['closed_at' => now()]);   // pesanan tertutup: kembali "Belum dipesan"
        $this->get(route('stock.home'))->assertOk()->assertDontSee('Sudah dicatat dipesan', false)->assertSee('Belum dipesan', false);
    }

    public function test_the_note_changes_neither_the_closing_rule_nor_the_alert(): void
    {
        $order = $this->scenario();

        $this->receive(1);   // pesanan lama tanpa jumlah: penerimaan apa pun tetap menutupnya
        $this->assertNotNull($order->fresh()->closed_at);
        $this->assertSame(3.0, $this->item->fresh()->stock_qty);
        $this->assertTrue($this->item->item->fresh()->isBelowReorderPoint());   // 3 <= 5: peringatan tetap menurut stok
    }
}
