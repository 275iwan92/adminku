<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Recipient;
use App\Models\ReorderOrder;
use App\Models\StockDocument;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\LeadTimes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga sambungan engine LeadTime DevLib: lead time dari pesanan yang sudah menerima barang (selisih hari kalender sampai penerimaan pertama), minimal 3 data (kurang = lead time manual barang), penerimaan yang dibatalkan tidak dihitung,
 *                perkiraan tiba dan peringatan terlambat pada pesanan terbuka, saran titik pesan ulang dari pemakaian 90 hari, dan tanpa galat saat tanpa data. Hanya keterangan / saran: aturan penutupan dan peringatan tidak berubah.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class LeadTimeTest extends TestCase
{
    use RefreshDatabase;

    private User $sup;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sup = User::factory()->supervisor()->create(['name' => 'Sari Supervisor']);
        $this->actingAs($this->sup);
        $this->item = $this->makeItem(['code' => 'LT-1', 'name' => 'Kertas HVS', 'category_id' => Category::create(['code' => 'ATK', 'name' => 'ATK'])->id, 'unit' => 'pcs', 'unit_price' => 10, 'reorder_point' => 5, 'reorder_qty' => 20]);
    }

    private function receive(float $qty): StockDocument
    {
        $payload = ['supplier_id' => Supplier::firstOrCreate(['code' => 'S1'], ['name' => 'S'])->id, 'lines' => [['item_id' => $this->item->id, 'qty' => $qty, 'unit_cost' => 10]]];
        $this->post(route('docs.store', 'receiving'), $payload)->assertSessionHasNoErrors();
        $doc = StockDocument::latest('id')->firstOrFail();
        $this->put(route('docs.update', ['receiving', $doc]), $payload + ['_action' => 'confirm'])->assertSessionHasNoErrors();

        return $doc->fresh();
    }

    private function issue(float $qty): StockDocument
    {
        $payload = ['recipient_id' => Recipient::firstOrCreate(['name' => 'Budi'])->id, 'lines' => [['item_id' => $this->item->id, 'qty' => $qty]]];
        $this->post(route('docs.store', 'issuance'), $payload)->assertSessionHasNoErrors();
        $doc = StockDocument::latest('id')->firstOrFail();
        $this->put(route('docs.update', ['issuance', $doc]), $payload + ['_action' => 'confirm'])->assertSessionHasNoErrors();

        return $doc->fresh();
    }

    // Pesanan selesai: dipesan $days hari sebelum "tiba", tiba $ago hari lalu (jam sengaja akhir hari: yang dihitung tanggal kalender).
    // Baris sintetis tanpa penerimaan nyata: close_reason 'manual' supaya ReorderOrders::reconcile (dijalankan tiap Penerimaan) tidak menghitung ulang dan membukanya.
    private function closedOrder(int $days, int $ago = 20): ReorderOrder
    {
        return ReorderOrder::create(['product_id' => $this->item->product_id, 'ordered_at' => today()->subDays($ago + $days), 'ref_no' => 'X', 'closed_at' => today()->subDays($ago)->setTime(23, 59), 'first_received_at' => today()->subDays($ago)->setTime(23, 59), 'close_reason' => 'manual']);
    }

    private function lead(): \Devlib\Engine\LeadTime
    {
        return LeadTimes::for($this->item->product->fresh());
    }

    public function test_closed_orders_give_the_average_in_calendar_days_rounded_up(): void
    {
        $this->closedOrder(4, 30);
        $this->closedOrder(5, 20);
        $this->closedOrder(6, 10);   // rata-rata 5
        $lt = $this->lead();
        $this->assertSame(['data', 5, 3, 3], [$lt->source(), $lt->effective(), $lt->count(), $lt->basis()]);

        $this->closedOrder(8, 5);    // 4, 5, 6, 8: rata-rata 5,75, dibulatkan ke atas
        $this->assertSame(6, $this->lead()->effective());
    }

    public function test_fewer_than_three_orders_use_the_manual_lead_time_or_nothing(): void
    {
        $this->closedOrder(4, 30);
        $this->closedOrder(6, 20);
        $this->assertSame(['none', null], [$this->lead()->source(), $this->lead()->effective()]);   // manual 0 = belum diisi

        $this->item->product->update(['lead_time_days' => 7]);
        $this->assertSame(['manual', 7], [$this->lead()->source(), $this->lead()->effective()]);

        $this->closedOrder(3, 10);
        $this->assertSame(['data', 5], [$this->lead()->source(), $this->lead()->effective()]);   // 4, 6, 3: data cukup, menggantikan manual
    }

    public function test_a_cancelled_receiving_reopens_the_order_and_it_is_not_counted(): void
    {
        $this->closedOrder(4, 30);
        $this->closedOrder(5, 20);
        $this->closedOrder(6, 10);
        $order = ReorderOrder::create(['product_id' => $this->item->product_id, 'ordered_at' => today()->subDays(3), 'ref_no' => 'P']);

        $in = $this->receive(10);                       // menutup pesanan: ikut dihitung (3 hari)
        $this->assertNotNull($order->fresh()->closed_at);
        $this->assertSame(4, $this->lead()->count());

        $this->put(route('docs.update', ['receiving', $in]), ['_action' => 'cancel', '_reason' => 'salah catat'])->assertSessionHasNoErrors();
        $this->assertNull($order->fresh()->closed_at);   // dibuka lagi
        $this->assertSame([3, 5], [$this->lead()->count(), $this->lead()->effective()]);
    }

    public function test_an_open_order_shows_the_expected_arrival_and_a_late_warning(): void
    {
        $this->closedOrder(4, 30);
        $this->closedOrder(5, 20);
        $this->closedOrder(6, 10);   // biasanya 5 hari
        $in = $this->receive(3);     // 3 <= titik 5, diterima sebelum pesanan ini (dimundurkan) supaya pesanan belum menerima apa pun
        StockMovement::where('stock_document_id', $in->id)->update(['moved_at' => now()->subDays(20)]);

        $late = ReorderOrder::create(['product_id' => $this->item->product_id, 'ordered_at' => today()->subDays(9), 'ref_no' => 'P-LATE', 'supplier_id' => Supplier::first()->id, 'qty' => 25, 'created_by' => $this->sup->id]);
        $expected = (new \DateTimeImmutable('today'))->modify('-4 days');
        $note = $late->note();
        $this->assertStringContainsString('Perkiraan tiba', $note);
        $this->assertStringContainsString(\Devlib\DateValue::of($expected->format('Y-m-d'))->display(), $note);
        $this->assertStringContainsString('Sudah 9 hari, biasanya 5. Periksa pesanan.', $note);
        $this->get(route('home'))->assertOk()->assertSee('Sudah 9 hari, biasanya 5. Periksa pesanan.', false)->assertSee('Sari Supervisor. Diterima sejak dipesan: 0 dari 25 pcs. Perkiraan tiba', false);

        $late->update(['ordered_at' => today()->subDays(2)]);   // belum terlambat: perkiraan saja
        $this->assertStringContainsString('Perkiraan tiba', $late->fresh()->note());
        $this->assertStringNotContainsString('Periksa pesanan', $late->fresh()->note());
    }

    public function test_without_any_lead_time_figure_the_note_adds_nothing_and_nothing_fails(): void
    {
        $this->receive(3);
        $order = ReorderOrder::create(['product_id' => $this->item->product_id, 'ordered_at' => today()->subDays(9), 'ref_no' => 'P', 'created_by' => $this->sup->id]);

        $this->assertStringEndsWith('Diterima sejak dipesan: 3 pcs.', $order->note());   // tanpa data dan tanpa manual: tidak ada kalimat tambahan
        $this->assertDoesNotMatchRegularExpression('/Perkiraan tiba \d{1,2}-/', $this->get(route('home'))->assertOk()->getContent());   // (kamus bahasa halaman memuat teks 'Perkiraan tiba {date}', jadi yang dicari kalimat bertanggal)
        $this->get(route('products.show', $this->item->product))->assertOk()->assertDontSee('Lead time otomatis', false)->assertDontSee('Saran Titik Pesan Ulang', false);
        $this->get(route('reports.summary', ['from' => today()->subDays(3)->toDateString(), 'to' => today()->toDateString()]))->assertOk();
    }

    public function test_the_manual_lead_time_alone_still_gives_an_expected_date(): void
    {
        $this->item->product->update(['lead_time_days' => 3]);
        $this->receive(3);
        $order = ReorderOrder::create(['product_id' => $this->item->product_id, 'ordered_at' => today(), 'ref_no' => 'P']);

        $this->assertStringContainsString('Perkiraan tiba', $order->fresh()->note());
        $this->assertStringNotContainsString('Periksa pesanan', $order->fresh()->note());
    }

    public function test_the_suggested_reorder_point_comes_from_90_day_usage_and_is_only_a_hint(): void
    {
        $this->closedOrder(4, 30);
        $this->closedOrder(5, 20);
        $this->closedOrder(6, 10);   // lead time 5
        $this->receive(200);
        $this->issue(45);            // 45 / 90 = 0,5 per hari
        $this->issue(9)->fresh();
        $cancelled = $this->issue(9);
        $this->put(route('docs.update', ['issuance', $cancelled]), ['_action' => 'cancel', '_reason' => 'salah catat'])->assertSessionHasNoErrors();
        $old = $this->issue(40);     // di luar 90 hari: tidak dihitung
        StockMovement::where('stock_document_id', $old->id)->update(['moved_at' => now()->subDays(100)]);

        // dihitung: 45 + 9 (yang dibatalkan dan yang lama tidak) = 54 / 90 = 0,6 per hari x 5 = 3
        $this->assertEqualsWithDelta(0.6, LeadTimes::dailyUsage($this->item->product), 1e-9);
        $before = $this->item->product->fresh()->only(['reorder_point', 'lead_time_days']);

        $html = $this->get(route('products.show', $this->item->product))->assertOk()->getContent();
        $this->assertStringContainsString('Lead time otomatis: 5 hari (rata-rata dari 3 pemesanan)', $html);
        $this->assertStringContainsString('pemakaian rata-rata 0,6 pcs/hari (90 hari terakhir) · lead time 5 hari', $html);
        $this->assertMatchesRegularExpression('/Titik Pesan Ulang: <strong><span data-advice-rop>8<\/span> pcs/', $html);   // 0,6 x (5 + 7 hari pengaman) = 7,2 -> 8
        $this->assertMatchesRegularExpression('/Jml Pesan Ulang: <strong><span data-advice-qty>18<\/span> pcs/', $html);   // 0,6 x 30 hari cakupan = 18
        $this->assertStringContainsString('data-advice-apply', $html);   // Supervisor boleh memakai saran
        $this->assertSame($before, $this->item->product->fresh()->only(['reorder_point', 'lead_time_days']));   // hanya saran
    }

    public function test_the_summary_report_shows_the_automatic_lead_time_next_to_the_manual_one(): void
    {
        $this->item->product->update(['lead_time_days' => 3]);
        $this->item->product->propagate();
        $this->closedOrder(4, 30);
        $this->closedOrder(5, 20);
        $this->closedOrder(6, 10);

        $this->get(route('reports.summary', ['from' => today()->subDays(3)->toDateString(), 'to' => today()->toDateString()]))->assertOk()->assertSee('Lead Time Otomatis (d)', false);
        $this->assertSame(5, $this->lead()->effective());
    }

    public function test_the_service_loads_many_products_with_one_orders_query(): void
    {
        $other = $this->makeItem(['code' => 'LT-2', 'name' => 'Pensil', 'category_id' => $this->item->category_id, 'unit' => 'pcs', 'unit_price' => 1]);
        $products = [$this->item->product->fresh(), $other->product->fresh()];

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $all = LeadTimes::forMany($products);
        $queries = collect(\Illuminate\Support\Facades\DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'i_reorder_order'))->count();

        $this->assertSame(1, $queries);
        $this->assertSame([$products[0]->id, $products[1]->id], array_keys($all));
    }
}
