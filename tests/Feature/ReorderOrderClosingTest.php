<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Batch;
use App\Models\ReorderOrder;
use App\Models\StockDocument;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\LeadTimes;
use App\Services\ReorderOrders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga aturan penutupan pesanan ulang: pesanan ber-jumlah ditutup hanya saat jumlah diterima memenuhi jumlah dipesan (penerimaan sebagian = diterima sebagian, tetap terbuka), kelebihan mengalir FIFO ke pesanan berikutnya,
 *                pembatalan penerimaan membuka kembali (idempoten), pesanan lama tanpa jumlah tetap tertutup oleh penerimaan apa pun, tutup manual, lead time sampai penerimaan pertama, penerimaan sebelum tanggal pesan / produk lain tidak dihitung.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class ReorderOrderClosingTest extends TestCase
{
    use RefreshDatabase;

    private User $sup;

    private Batch $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sup = User::factory()->supervisor()->create(['name' => 'Sari Supervisor']);
        $this->actingAs($this->sup);
        $this->item = $this->makeBatch(['code' => 'CL-1', 'name' => 'Kertas HVS', 'category_id' => Category::create(['code' => 'ATK', 'name' => 'ATK'])->id, 'unit' => 'pcs', 'unit_price' => 10, 'reorder_point' => 5, 'reorder_qty' => 20]);
    }

    // Penerimaan terkonfirmasi; $daysAgo memundurkan waktu pergerakannya (pesanan dihitung ulang seperti setelah Confirm).
    private function receive(float $qty, int $daysAgo = 0, ?Batch $item = null): StockDocument
    {
        $item ??= $this->item;
        $payload = ['supplier_id' => Supplier::firstOrCreate(['code' => 'S1'], ['name' => 'Toko Maju'])->id, 'lines' => [['batch_id' => $item->id, 'qty' => $qty, 'unit_cost' => 10]]];
        $this->post(route('docs.store', 'receiving'), $payload)->assertSessionHasNoErrors();
        $doc = StockDocument::latest('id')->firstOrFail();
        $this->put(route('docs.update', ['receiving', $doc]), $payload + ['_action' => 'confirm'])->assertSessionHasNoErrors();
        if ($daysAgo > 0) {
            StockMovement::where('stock_document_id', $doc->id)->update(['moved_at' => now()->subDays($daysAgo)]);
            app(ReorderOrders::class)->reconcile($item->product_id);
        }

        return $doc->fresh();
    }

    private function cancel(StockDocument $doc): void
    {
        $this->put(route('docs.update', ['receiving', $doc]), ['_action' => 'cancel', '_reason' => 'salah catat'])->assertSessionHasNoErrors();
    }

    private function order(?float $qty, int $daysAgo = 1, string $ref = 'PO'): ReorderOrder
    {
        return ReorderOrder::create(['product_id' => $this->item->product_id, 'ordered_at' => today()->subDays($daysAgo), 'ref_no' => $ref, 'qty' => $qty, 'supplier_id' => Supplier::firstOrCreate(['code' => 'S1'], ['name' => 'Toko Maju'])->id, 'created_by' => $this->sup->id]);
    }

    public function test_a_partial_receiving_does_not_close_the_order(): void
    {
        $order = $this->order(25);

        $this->receive(10);

        $o = $order->fresh();
        $this->assertSame([10.0, null, null, 'partial', 'Diterima sebagian'], [$o->received_qty, $o->closed_at, $o->receiving_id, $o->status(), $o->statusLabel()]);
        $this->assertNotNull($o->first_received_at);
        $this->assertStringContainsString('Diterima sejak dipesan: 10 dari 25 pcs.', $o->note());
    }

    public function test_the_receiving_that_completes_the_quantity_closes_the_order(): void
    {
        $order = $this->order(25);
        $this->receive(10);
        $second = $this->receive(15);

        $o = $order->fresh();
        $this->assertSame([25.0, 'received', $second->id, 'done'], [$o->received_qty, $o->close_reason, $o->receiving_id, $o->status()]);
        $this->assertNotNull($o->closed_at);
    }

    public function test_an_excess_receiving_flows_fifo_to_the_next_open_order(): void
    {
        $newer = $this->order(10, 1, 'PO-B');
        $older = $this->order(10, 3, 'PO-A');   // dibuat belakangan tetapi dipesan lebih dulu: dialokasikan dulu

        $first = $this->receive(15);
        $this->assertSame([10.0, $first->id], [$older->fresh()->received_qty, $older->fresh()->receiving_id]);
        $this->assertSame([5.0, null], [$newer->fresh()->received_qty, $newer->fresh()->closed_at]);

        $second = $this->receive(5);
        $this->assertSame([10.0, $second->id, 'received'], [$newer->fresh()->received_qty, $newer->fresh()->receiving_id, $newer->fresh()->close_reason]);
        $this->assertSame($first->id, $older->fresh()->receiving_id);   // yang sudah tuntas tidak bergeser
    }

    public function test_cancelling_a_receiving_reopens_the_order_and_reconcile_is_idempotent(): void
    {
        $order = $this->order(25);
        $first = $this->receive(10);
        $second = $this->receive(15);
        $this->assertNotNull($order->fresh()->closed_at);

        $this->cancel($second);
        $o = $order->fresh();
        $this->assertSame([10.0, null, null, null], [$o->received_qty, $o->closed_at, $o->receiving_id, $o->close_reason]);

        $service = app(ReorderOrders::class);
        $before = $order->fresh()->getAttributes();
        $service->reconcile($this->item->product_id);
        $service->reconcile($this->item->product_id);
        $this->assertSame($before, $order->fresh()->getAttributes());   // diulang: tidak berubah

        $this->cancel($first);   // urutan batal bebas: sekarang tidak ada penerimaan sama sekali
        $this->assertSame([0.0, null, null], [$order->fresh()->received_qty, $order->fresh()->first_received_at, $order->fresh()->closed_at]);

        $third = $this->receive(25);   // dikonfirmasi lagi sesudah pembatalan: menutup dengan dokumen baru
        $this->assertSame([$third->id, 'received'], [$order->fresh()->receiving_id, $order->fresh()->close_reason]);
    }

    public function test_cancelling_the_first_receiving_keeps_the_later_one_counted(): void
    {
        $order = $this->order(25);
        $first = $this->receive(10);
        $second = $this->receive(5);

        $this->cancel($first);

        $o = $order->fresh();
        $this->assertSame(5.0, $o->received_qty);
        $this->assertEquals($second->fresh()->doc_at->timestamp, $o->first_received_at->timestamp);   // penerimaan pertama yang tersisa
    }

    public function test_an_old_order_without_quantity_is_still_closed_by_any_receiving(): void
    {
        $order = $this->order(null);

        $doc = $this->receive(1);
        $o = $order->fresh();
        $this->assertSame([$doc->id, 'received', 'done'], [$o->receiving_id, $o->close_reason, $o->status()]);
        $this->assertNotNull($o->first_received_at);

        $this->cancel($doc);
        $this->assertSame([null, null, null], [$order->fresh()->closed_at, $order->fresh()->first_received_at, $order->fresh()->close_reason]);
    }

    public function test_a_receiving_before_the_order_date_or_for_another_product_is_not_counted(): void
    {
        $other = $this->makeBatch(['code' => 'CL-2', 'name' => 'Pensil', 'category_id' => $this->item->category_id, 'unit' => 'pcs', 'unit_price' => 1]);
        $order = $this->order(5, 2);

        $this->receive(10, 5);          // sebelum tanggal pesan
        $this->receive(10, 0, $other);  // produk lain
        $this->assertSame([0.0, null, null], [$order->fresh()->received_qty, $order->fresh()->closed_at, $order->fresh()->first_received_at]);

        $this->receive(5);              // sesudah tanggal pesan: tuntas
        $this->assertNotNull($order->fresh()->closed_at);
    }

    public function test_closing_manually_keeps_the_order_closed_and_adds_the_reason(): void
    {
        $order = $this->order(25);
        $order->update(['note' => 'kirim pagi']);

        $this->post(route('reorder-orders.close', $order), ['note' => 'supplier kirim kurang'])->assertRedirect(route('home'));

        $o = $order->fresh();
        $this->assertSame(['manual', 'Ditutup manual', 'kirim pagi Ditutup: supplier kirim kurang'], [$o->close_reason, $o->statusLabel(), $o->note]);
        $this->assertNotNull($o->closed_at);

        $this->post(route('reorder-orders.close', $order))->assertStatus(422);   // hanya yang masih terbuka
        $this->receive(30);                                                      // penerimaan berikutnya tidak membuka atau mengalokasikan ke pesanan manual
        $o = $order->fresh();
        $this->assertSame([0.0, 'manual', null], [$o->received_qty, $o->close_reason, $o->receiving_id]);
    }

    public function test_closing_requires_a_signed_in_user_like_recording_an_order(): void
    {
        $order = $this->order(25);
        auth()->logout();

        $this->post(route('reorder-orders.close', $order))->assertRedirect(route('login'));
        $this->assertNull($order->fresh()->closed_at);
    }

    public function test_lead_time_counts_to_the_first_receiving_and_skips_manual_orders_without_receiving(): void
    {
        $partial = $this->order(25, 10);
        $this->receive(5, 4);   // pertama datang 4 hari lalu: 6 hari sejak dipesan
        $this->receive(5, 1);   // yang kedua tidak mengubah lead time
        $this->assertNull($partial->fresh()->closed_at);

        $manual = $this->order(25, 9);   // semua penerimaan (10 < 25) sudah dipakai pesanan yang lebih lama: ini tidak punya penerimaan
        $this->post(route('reorder-orders.close', $manual))->assertRedirect();

        $lt = LeadTimes::for($this->item->product->fresh());
        $this->assertSame(1, $lt->count());   // hanya pesanan yang punya penerimaan pertama (6 hari, bukan 9)
        $this->assertNull($manual->fresh()->first_received_at);
    }

    public function test_a_late_warning_stops_after_the_first_receiving(): void
    {
        foreach ([[30, 4], [20, 5], [10, 6]] as [$ago, $days]) {   // biasanya 5 hari
            $at = today()->subDays($ago)->setTime(23, 59);
            ReorderOrder::create(['product_id' => $this->item->product_id, 'ordered_at' => today()->subDays($ago + $days), 'ref_no' => 'X', 'closed_at' => $at, 'first_received_at' => $at, 'close_reason' => 'manual']);   // sintetis tanpa penerimaan nyata: 'manual' agar tidak dihitung ulang
        }
        $order = $this->order(25, 9);

        $this->assertStringContainsString('Periksa pesanan', $order->fresh()->note());   // 9 hari tanpa penerimaan: terlambat

        $this->receive(5);
        $this->assertStringNotContainsString('Periksa pesanan', $order->fresh()->note());
        $this->assertStringNotContainsString('Perkiraan tiba', $order->fresh()->note());
    }

    public function test_home_shows_the_partial_status_and_the_close_action_when_below_the_reorder_point(): void
    {
        $this->receive(3);   // 3 <= titik 5
        $order = $this->order(25, 0);
        $this->receive(1);   // penerimaan hari yang sama dengan tanggal pesan ikut dihitung: 3 + 1

        $this->get(route('home'))->assertOk()->assertSee('Diterima sebagian', false)->assertSee('Diterima sejak dipesan: 4 dari 25 pcs.', false)
            ->assertSee(route('reorder-orders.close', $order), false)->assertSee('Tutup Pesanan', false);
    }
}
