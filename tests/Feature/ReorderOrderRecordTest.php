<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Batch;
use App\Models\ReorderOrder;
use App\Models\StockDocument;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga catatan pemesanan sederhana (bukan PO) di "Catat Pemesanan": supplier (aktif), jumlah dipesan > 0 dalam satuan stok, tanggal, ref dan catatan opsional; info peringatan menampilkan supplier + jumlah;
 *                baris lama tanpa supplier / jumlah tetap tampil; pesanan ber-jumlah tertutup hanya bila jumlah diterima memenuhi jumlah dipesan (lihat ReorderOrderClosingTest).
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class ReorderOrderRecordTest extends TestCase
{
    use RefreshDatabase;

    private User $sup;

    private Batch $item;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sup = User::factory()->supervisor()->create(['name' => 'Sari Supervisor']);
        $this->actingAs($this->sup);
        $this->supplier = Supplier::create(['code' => 'S1', 'name' => 'Toko Maju']);
        $this->item = $this->makeBatch(['code' => 'RR-1', 'name' => 'Kertas HVS', 'category_id' => Category::create(['code' => 'ATK', 'name' => 'ATK'])->id, 'unit' => 'pcs', 'unit_price' => 10, 'reorder_point' => 5, 'reorder_qty' => 20]);
        $this->receive(3);   // 3 <= titik 5: di bawah titik pesan ulang
    }

    private function receive(float $qty): void
    {
        $payload = ['supplier_id' => $this->supplier->id, 'lines' => [['batch_id' => $this->item->id, 'qty' => $qty, 'unit_cost' => 10]]];
        $this->post(route('docs.store', 'receiving'), $payload)->assertSessionHasNoErrors();
        $this->put(route('docs.update', ['receiving', StockDocument::latest('id')->firstOrFail()]), $payload + ['_action' => 'confirm'])->assertSessionHasNoErrors();
    }

    private function order(array $extra = []): array
    {
        return array_merge(['product_id' => $this->item->product_id, 'supplier_id' => $this->supplier->id, 'qty' => 25, 'ordered_at' => today()->toDateString(), 'ref_no' => 'PO-5', 'note' => 'kirim pagi'], $extra);
    }

    public function test_the_popup_saves_supplier_quantity_date_ref_and_note(): void
    {
        $this->post(route('reorder-orders.store'), $this->order(['ordered_at' => today()->subDay()->toDateString()]))->assertRedirect(route('home'));

        $o = ReorderOrder::firstOrFail();
        $this->assertSame([$this->supplier->id, 25.0, 'PO-5', 'kirim pagi', $this->sup->id, today()->subDay()->toDateString()], [$o->supplier_id, $o->qty, $o->ref_no, $o->note, $o->created_by, $o->ordered_at->toDateString()]);
    }

    public function test_the_reference_and_note_are_optional(): void
    {
        $this->post(route('reorder-orders.store'), $this->order(['ref_no' => null, 'note' => null]))->assertSessionHasNoErrors();

        $this->assertSame('', ReorderOrder::firstOrFail()->ref_no);
        $this->assertStringContainsString('Sudah dipesan 25 pcs ke Toko Maju pada '.today()->format('d/m/Y').' oleh Sari Supervisor.', ReorderOrder::firstOrFail()->note());   // tanpa ", ref"
        $this->assertStringNotContainsString('ref', ReorderOrder::firstOrFail()->note());
    }

    public function test_validation_rejects_a_bad_quantity_and_a_missing_or_inactive_supplier(): void
    {
        $inactive = Supplier::create(['code' => 'S2', 'name' => 'Toko Tutup', 'is_active' => false]);

        $this->post(route('reorder-orders.store'), $this->order(['qty' => 0]))->assertSessionHasErrors('qty');
        $this->post(route('reorder-orders.store'), $this->order(['qty' => -3]))->assertSessionHasErrors('qty');
        $this->post(route('reorder-orders.store'), $this->order(['qty' => null]))->assertSessionHasErrors('qty');
        $this->post(route('reorder-orders.store'), $this->order(['supplier_id' => null]))->assertSessionHasErrors('supplier_id');
        $this->post(route('reorder-orders.store'), $this->order(['supplier_id' => 9999]))->assertSessionHasErrors('supplier_id');
        $this->post(route('reorder-orders.store'), $this->order(['supplier_id' => $inactive->id]))->assertSessionHasErrors('supplier_id');
        $this->assertSame(0, ReorderOrder::count());
    }

    public function test_the_alert_shows_supplier_and_quantity_and_the_report_has_the_columns(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('name="supplier_id"', false)->assertSee('Jumlah Dipesan', false)->assertSee('Toko Maju', false)->assertSee('data-reorder-unit', false)->assertSee('No. Referensi Pemesanan', false)->assertDontSee('Referensi Pemesanan (PO)', false);   // popup (hanya ada selama ada barang yang belum dipesan)
        $this->post(route('reorder-orders.store'), $this->order());
        $expected = 'Sudah dipesan 25 pcs ke Toko Maju pada '.today()->format('d/m/Y').', ref PO-5 oleh Sari Supervisor. Diterima sejak dipesan: 3 dari 25 pcs.';

        $this->get(route('home'))->assertOk()->assertSee($expected, false)->assertSee('Catatan: kirim pagi', false);
        $this->get(route('products.show', $this->item->product))->assertOk()->assertSee($expected, false);
        $html = $this->get(route('reports.summary', ['from' => today()->subDays(3)->toDateString(), 'to' => today()->toDateString()]))->assertOk()->getContent();
        foreach (['Supplier Pesanan', 'Jumlah Dipesan', 'Toko Maju', '25 pcs'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }

    public function test_an_old_order_without_supplier_or_quantity_still_shows_without_error(): void
    {
        ReorderOrder::create(['product_id' => $this->item->product_id, 'ordered_at' => today(), 'ref_no' => 'PO-OLD', 'created_by' => $this->sup->id]);

        $this->assertStringStartsWith('Sudah dicatat dipesan pada '.today()->format('d/m/Y').', ref PO-OLD oleh Sari Supervisor.', ReorderOrder::firstOrFail()->note());
        $this->get(route('home'))->assertOk()->assertSee('Sudah dicatat dipesan pada', false)->assertSee('PO-OLD', false);
        $this->get(route('reports.summary', ['from' => today()->subDays(3)->toDateString(), 'to' => today()->toDateString()]))->assertOk();
        $this->get(route('products.show', $this->item->product))->assertOk();
    }

    public function test_a_deleted_supplier_leaves_the_order_readable(): void
    {
        $other = Supplier::create(['code' => 'S3', 'name' => 'Toko Hilang']);
        $this->post(route('reorder-orders.store'), $this->order(['supplier_id' => $other->id]));
        $other->delete();

        $this->assertNull(ReorderOrder::firstOrFail()->supplier_id);   // null on delete
        $this->get(route('home'))->assertOk()->assertSee('Sudah dipesan 25 pcs pada', false);
    }

    public function test_a_partial_receiving_keeps_the_order_open_until_the_ordered_quantity_arrives(): void
    {
        $this->post(route('reorder-orders.store'), $this->order());   // dipesan 25; 3 dari penerimaan hari yang sama ikut dihitung

        $this->receive(1);                                            // 4 dari 25: tetap terbuka
        $this->assertNull(ReorderOrder::firstOrFail()->closed_at);

        $this->receive(21);                                           // 25 dari 25: tuntas
        $this->assertNotNull(ReorderOrder::firstOrFail()->closed_at);
    }
}
