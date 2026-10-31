<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Department;
use App\Models\Batch;
use App\Models\Item;
use App\Models\Recipient;
use App\Models\ReorderOrder;
use App\Models\StockDocument;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockValuation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga aturan inti stok: login per peran, hak akses, saldo + moving average, stok tidak boleh minus, saldo awal, siklus penyesuaian, peringatan Reorder Point, dan nilai tersembunyi dari Data Entry.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class StockFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private User $entry;

    private Supplier $supplier;

    private Recipient $recipient;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->supervisor = User::factory()->supervisor()->create(['username' => 'sup']);
        $this->entry = User::factory()->create(['username' => 'ent']);
        $this->supplier = Supplier::create(['code' => 'S1', 'name' => 'Supplier One']);
        $this->department = Department::create(['code' => 'MNT', 'name' => 'Maintenance']);
        $this->recipient = Recipient::create(['name' => 'Budi', 'department_id' => $this->department->id]);
    }

    private function item(string $code = 'I1', float $rop = 0): Batch
    {
        $category = Category::firstOrCreate(['code' => 'ATK'], ['name' => 'ATK']);

        return $this->makeBatch(['code' => $code, 'name' => "Item {$code}", 'category_id' => $category->id, 'unit' => 'pcs', 'unit_price' => 100, 'reorder_point' => $rop, 'reorder_qty' => 50, 'lead_time_days' => 3]);
    }

    // Simpan (draf) lalu Confirm: stok baru berubah sesudah Confirm. Mengembalikan respons langkah terakhir (galat validasi pada simpan menghentikan lebih awal).
    private function postDoc(string $slug, array $payload, User $by)
    {
        $store = $this->actingAs($by)->post(route('docs.store', $slug), $payload);
        if (session()->has('errors')) {
            return $store;
        }

        return $this->put(route('docs.update', [$slug, StockDocument::latest('id')->firstOrFail()]), $payload + ['_action' => 'confirm']);
    }

    private function receive(Batch $item, float $qty, float $cost, ?User $by = null)
    {
        return $this->postDoc('receiving', [
            'supplier_id' => $this->supplier->id, 'ref_no' => 'SJ-1',
            'lines' => [['batch_id' => $item->id, 'qty' => $qty, 'unit_cost' => $cost]],
        ], $by ?? $this->entry);
    }

    private function issue(Batch $item, float $qty)
    {
        return $this->postDoc('issuance', [
            'recipient_id' => $this->recipient->id, 'department_id' => $this->department->id, 'lines' => [['batch_id' => $item->id, 'qty' => $qty]],
        ], $this->entry);
    }

    public function test_login_redirects_by_role_and_rejects_wrong_password(): void
    {
        $this->post(route('login.attempt'), ['login' => 'sup', 'password' => 'password'])->assertRedirect(route('home'));
        auth()->logout();
        $this->post(route('login.attempt'), ['login' => $this->entry->email, 'password' => 'password'])->assertRedirect(route('home', ['open' => 'RCV']));
        auth()->logout();
        $this->from(route('login'))->post(route('login.attempt'), ['login' => 'sup', 'password' => 'nope'])->assertRedirect(route('login'))->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->entry->update(['is_active' => false]);
        $this->post(route('login.attempt'), ['login' => 'ent', 'password' => 'password'])->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_receiving_and_issuance_update_balance_and_moving_average(): void
    {
        $item = $this->item();
        $this->receive($item, 100, 10)->assertRedirect();
        $this->receive($item, 100, 20);
        $item->refresh();
        $this->assertSame(200.0, $item->stock_qty);
        $this->assertEqualsWithDelta(15.0, $item->avg_cost, 0.0001);

        $this->issue($item, 50)->assertRedirect();
        $item->refresh();
        $this->assertSame(150.0, $item->stock_qty);
        $this->assertEqualsWithDelta(15.0, $item->avg_cost, 0.0001);
        $this->assertSame(3, $item->movements()->count());
        $this->assertSame(150.0, $item->movements()->latest('id')->first()->balance_qty);
    }

    public function test_issuance_cannot_make_stock_negative(): void
    {
        $item = $this->item();
        $this->receive($item, 10, 5);
        $this->issue($item, 11)->assertSessionHasErrors('lines');
        $this->assertSame(10.0, $item->fresh()->stock_qty);
        $this->assertSame(['draft'], StockDocument::where('type', 'OUT')->pluck('status')->all());  // gagal Confirm: tetap draf, stok tidak berubah
    }

    public function test_failed_save_keeps_the_typed_lines_with_item_labels(): void
    {
        $item = $this->item('I9');
        $this->receive($item, 1, 5);
        $this->from(route('docs.create', 'issuance'))->post(route('docs.store', 'issuance'), ['lines' => [['batch_id' => $item->id, 'qty' => 5]]])->assertSessionHasErrors('recipient_id');

        $this->get(route('docs.create', 'issuance'))->assertOk()->assertSee('batch_id_label', false)->assertSee('Item I9', false);
    }

    public function test_issuance_reaching_reorder_point_flashes_a_warning(): void
    {
        $item = $this->item('I2', rop: 5);
        $this->receive($item, 10, 5);
        $this->issue($item, 5)->assertSessionHas('reorder');
        $this->assertTrue($item->item->fresh()->isBelowReorderPoint());
        $this->actingAs($this->supervisor)->get(route('home'))->assertOk()->assertSee('di bawah titik pesan ulang');
    }

    public function test_data_entry_cannot_open_supervisor_screens(): void
    {
        $this->actingAs($this->entry);
        $this->get(route('docs.index', 'adjustment'))->assertForbidden();
        $this->get(route('docs.create', 'opening'))->assertForbidden();
        $this->get(route('reports.adjustment'))->assertForbidden();
        $this->post(route('docs.store', 'adjustment'), [])->assertForbidden();
    }

    public function test_supervisor_manages_item_parameters_and_they_reach_the_batches(): void
    {
        $category = Category::create(['code' => 'ATK', 'name' => 'ATK']);
        $payload = ['name' => 'Pen', 'category_id' => $category->id, 'unit' => 'box', 'unit_price' => 1500, 'reorder_point' => 5, 'reorder_qty' => 20, 'lead_time_days' => 4];
        $this->actingAs($this->supervisor)->post(route('products.store'), $payload)->assertRedirect();
        $product = Item::where('code', 'ATK-0001')->firstOrFail();  // kode otomatis dari awalan kategori
        $item = $product->batches()->firstOrFail();                         // barang biasa: satu batch, kodenya sama

        $this->put(route('products.update', $product), ['_action' => 'save', ...$payload, 'reorder_point' => 9])->assertRedirect();
        $this->assertSame([9.0, 'ATK-0001'], [$product->fresh()->reorder_point, $item->fresh()->code]);
        $this->assertSame(9.0, $item->fresh()->reorder_point);          // parameter produk diteruskan ke batch
        $this->put(route('products.update', $product), ['_action' => 'deactivate'])->assertRedirect();
        $this->assertSame([false, false], [$product->fresh()->is_active, $item->fresh()->is_active]);

        $this->actingAs($this->entry)->put(route('products.update', $product), ['_action' => 'save'] + $payload)->assertForbidden();
    }

    public function test_opening_balance_only_for_items_without_movements(): void
    {
        $fresh = $this->item('N1');
        $used = $this->item('N2');
        $this->receive($used, 5, 1);

        $this->postDoc('opening', ['doc_at' => today()->toDateString(), 'lines' => [['batch_id' => $used->id, 'qty' => 3, 'unit_cost' => 2]]], $this->supervisor)->assertSessionHasErrors('lines');
        $this->postDoc('opening', ['doc_at' => today()->toDateString(), 'lines' => [['batch_id' => $fresh->id, 'qty' => 3, 'unit_cost' => 2]]], $this->supervisor)->assertRedirect();
        $this->assertSame(3.0, $fresh->fresh()->stock_qty);
        $this->assertEqualsWithDelta(2.0, $fresh->fresh()->avg_cost, 0.0001);
    }

    public function test_adjustment_needs_approval_before_stock_changes(): void
    {
        $item = $this->item();
        $this->receive($item, 100, 10);

        $this->actingAs($this->supervisor)->post(route('docs.store', 'adjustment'), ['reason' => 'Opname', 'lines' => [['batch_id' => $item->id, 'counted_qty' => 90]]])->assertRedirect();
        $doc = StockDocument::where('type', 'ADJ')->firstOrFail();
        $this->assertSame('draft', $doc->status);
        $this->assertSame(-10.0, $doc->lines()->first()->qty);
        $this->assertSame(100.0, $item->fresh()->stock_qty);

        $this->put(route('docs.update', ['adjustment', $doc]), ['_action' => 'confirm', 'reason' => 'Opname', 'lines' => [['batch_id' => $item->id, 'counted_qty' => 90]]]);
        $this->assertSame('approval', $doc->fresh()->status);
        $this->assertSame(100.0, $item->fresh()->stock_qty);

        $this->put(route('docs.update', ['adjustment', $doc]), ['_action' => 'approve'])->assertRedirect();
        $this->assertSame('approved', $doc->fresh()->status);
        $this->assertSame($this->supervisor->id, $doc->fresh()->approved_by);
        $this->assertSame(90.0, $item->fresh()->stock_qty);
    }

    public function test_adjustment_is_blocked_when_stock_moved_after_the_count(): void
    {
        $item = $this->item();
        $this->receive($item, 100, 10);
        $this->actingAs($this->supervisor)->post(route('docs.store', 'adjustment'), ['reason' => 'Opname', 'lines' => [['batch_id' => $item->id, 'counted_qty' => 95]]]);
        $doc = StockDocument::where('type', 'ADJ')->firstOrFail();
        $this->put(route('docs.update', ['adjustment', $doc]), ['_action' => 'confirm', 'reason' => 'Opname', 'lines' => [['batch_id' => $item->id, 'counted_qty' => 95]]]);

        $this->issue($item, 5);
        $this->actingAs($this->supervisor)->put(route('docs.update', ['adjustment', $doc]), ['_action' => 'approve'])->assertSessionHasErrors('lines');
        $this->assertSame('approval', $doc->fresh()->status);
        $this->assertSame(95.0, $item->fresh()->stock_qty);

        $this->put(route('docs.update', ['adjustment', $doc]), ['_action' => 'reject'])->assertRedirect();
        $this->assertSame('rejected', $doc->fresh()->status);
    }

    public function test_summary_stock_reports_opening_movement_and_closing_for_the_period(): void
    {
        $item = $this->item('P1', rop: 40);
        Carbon::setTestNow('2026-09-10 10:00:00');
        $this->receive($item, 100, 10);   // sebelum periode = saldo awal
        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->issue($item, 30);
        Carbon::setTestNow('2026-10-15 09:00:00');
        $this->receive($item, 20, 10);
        Carbon::setTestNow('2026-10-25 09:00:00');
        $this->issue($item, 50);          // sesudah periode: tidak ikut
        Carbon::setTestNow();

        $html = $this->actingAs($this->supervisor)->get(route('reports.summary', ['from' => '2026-10-01', 'to' => '2026-10-20']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/&quot;open&quot;:100(\.0)?,&quot;in&quot;:20(\.0)?,&quot;out&quot;:30(\.0)?,&quot;adj&quot;:0(\.0)?,&quot;end&quot;:90(\.0)?/', $html);

        $later = $this->get(route('reports.summary', ['from' => '2026-10-21', 'to' => '2026-10-31']))->getContent();
        $this->assertMatchesRegularExpression('/&quot;open&quot;:90(\.0)?,&quot;in&quot;:0(\.0)?,&quot;out&quot;:50(\.0)?,&quot;adj&quot;:0(\.0)?,&quot;end&quot;:40(\.0)?.*PESAN ULANG/', $later);
    }

    public function test_valuation_series_is_weekly_within_a_month_with_prev_and_next(): void
    {
        $item = $this->item('V1');
        Carbon::setTestNow('2026-09-10 09:00:00');
        $this->receive($item, 100, 10);   // nilai 1.000
        Carbon::setTestNow('2026-10-15 09:00:00');
        $this->issue($item, 40);          // nilai 600
        Carbon::setTestNow('2026-10-19 12:00:00');

        $oct = StockValuation::series();
        $this->assertSame('Oktober 2026', $oct['title']);
        $this->assertSame([1000.0, 1000.0, 600.0], $oct['values']);      // s/d 7, 14 Okt (sebelum pengeluaran), 19 Okt (hari ini)
        $this->assertSame('s/d 19 Okt', end($oct['labels']));
        $this->assertSame('2026-09', $oct['prev']);
        $this->assertNull($oct['next']);

        $sep = StockValuation::series('2026-09');
        $this->assertSame([0.0, 1000.0, 1000.0, 1000.0, 1000.0], $sep['values']);   // s/d 7, 14, 21, 28, 30 Sep
        $this->assertNull($sep['prev']);
        $this->assertSame('2026-10', $sep['next']);

        $this->assertSame('Oktober 2026', StockValuation::series('2030-01')['title']);   // di luar rentang: dijepit
        $this->assertSame('September 2026', StockValuation::series('2020-01')['title']);
        $this->assertSame('Oktober 2026', StockValuation::series('ngawur')['title']);
        Carbon::setTestNow();
    }

    public function test_opening_screen_shows_the_valuation_chart_only_to_supervisors(): void
    {
        $item = $this->item('V2', rop: 5);
        $this->receive($item, 3, 10);

        $this->actingAs($this->supervisor)->get(route('home'))->assertOk()->assertSee('data-dl-chart', false);
        $this->actingAs($this->entry)->get(route('home'))->assertOk()->assertDontSee('data-dl-chart', false);
    }

    public function test_reorder_order_is_recorded_shown_on_the_opening_screen_and_closed_when_the_quantity_is_received(): void
    {
        $item = $this->item('R1', rop: 5);
        $fine = $this->item('R2', rop: 5);
        $this->receive($item, 3, 10);
        $this->receive($fine, 50, 10);

        $this->actingAs($this->entry)->get(route('home'))->assertOk()->assertSee('Belum dipesan')->assertDontSee('Item R2');

        $order = ['item_id' => $item->item_id, 'supplier_id' => Supplier::create(['code' => 'S9', 'name' => 'Toko Sembilan'])->id, 'qty' => 25, 'ordered_at' => today()->toDateString(), 'ref_no' => 'PO-77'];
        $this->post(route('reorder-orders.store'), $order)->assertRedirect(route('home'));
        $this->get(route('home'))->assertSee('Diterima sebagian')->assertSee('PO-77')->assertDontSee('Belum dipesan');   // 3 dari 25: penerimaan hari yang sama dengan tanggal pesan ikut dihitung

        $this->post(route('reorder-orders.store'), $order)->assertStatus(422);                                   // sudah terbuka
        $this->post(route('reorder-orders.store'), ['item_id' => $fine->item_id] + $order)->assertStatus(422);        // stok masih cukup
        $this->post(route('reorder-orders.store'), ['qty' => 0] + $order)->assertSessionHasErrors('qty');

        $this->receive($item, 20, 10);   // 23 dari 25: masih terbuka
        $this->assertNull(ReorderOrder::where('item_id', $item->item_id)->first()->closed_at);
        $this->receive($item, 2, 10);    // 25 dari 25: tuntas
        $this->assertNotNull(ReorderOrder::where('item_id', $item->item_id)->first()->closed_at);
        $this->assertNull($item->item->fresh()->openOrder);
    }

    public function test_issuance_uses_recipient_and_department_masters_and_copies_their_names(): void
    {
        $item = $this->item('M1');
        $this->receive($item, 20, 10);

        // departemen dikosongkan -> ikut departemen bawaan penerima; nama disalin ke dokumen
        $this->actingAs($this->entry)->post(route('docs.store', 'issuance'), ['recipient_id' => $this->recipient->id, 'lines' => [['batch_id' => $item->id, 'qty' => 2]]])->assertSessionHasNoErrors();
        $doc = StockDocument::where('type', 'OUT')->latest('id')->firstOrFail();
        $this->assertSame([$this->recipient->id, $this->department->id, 'Budi', 'Maintenance'], [$doc->recipient_id, $doc->department_id, $doc->recipient, $doc->department]);

        $this->post(route('docs.store', 'issuance'), ['lines' => [['batch_id' => $item->id, 'qty' => 1]]])->assertSessionHasErrors('recipient_id');   // penerima wajib dari master
        $this->recipient->update(['is_active' => false]);
        $this->post(route('docs.store', 'issuance'), ['recipient_id' => $this->recipient->id, 'lines' => [['batch_id' => $item->id, 'qty' => 1]]])->assertSessionHasErrors('recipient_id');
    }

    public function test_receiving_and_adjustment_also_take_optional_recipient_and_department(): void
    {
        $item = $this->item('M2');
        $this->actingAs($this->entry)->post(route('docs.store', 'receiving'), [
            'supplier_id' => $this->supplier->id, 'recipient_id' => $this->recipient->id,
            'lines' => [['batch_id' => $item->id, 'qty' => 5, 'unit_cost' => 10]],
        ])->assertSessionHasNoErrors();
        $in = StockDocument::where('type', 'IN')->firstOrFail();
        $this->assertSame(['Budi', $this->department->id, 'Maintenance'], [$in->recipient, $in->department_id, $in->department]);   // departemen ikut bawaan penerima

        $this->actingAs($this->supervisor)->post(route('docs.store', 'adjustment'), ['reason' => 'Opname', 'department_id' => $this->department->id, 'lines' => [['batch_id' => $item->id, 'counted_qty' => 4]]])->assertSessionHasNoErrors();
        $adj = StockDocument::where('type', 'ADJ')->firstOrFail();
        $this->assertSame(['Maintenance', null], [$adj->department, $adj->recipient_id]);

        // keduanya tetap opsional di Receiving
        $this->actingAs($this->entry)->post(route('docs.store', 'receiving'), ['supplier_id' => $this->supplier->id, 'lines' => [['batch_id' => $item->id, 'qty' => 1, 'unit_cost' => 10]]])->assertSessionHasNoErrors();
        $this->get(route('docs.create', 'receiving'))->assertOk()->assertSee('recipient_id', false);
    }

    public function test_recipient_and_department_can_be_added_quickly_as_json_from_the_issuance_form(): void
    {
        $this->actingAs($this->entry);
        $dept = $this->postJson(route('departments.store'), ['name' => 'Quality Control'])->assertCreated()->json();
        $this->assertSame('QUALITYCONTR', Department::find($dept['id'])->code);
        $this->postJson(route('departments.store'), ['name' => 'Quality-Control'])->assertCreated();                       // kode kembar -> diberi angka
        $this->assertSame(['QUALITYCONTR', 'QUALITYCONTR2'], Department::whereIn('name', ['Quality Control', 'Quality-Control'])->orderBy('id')->pluck('code')->all());

        $rec = $this->postJson(route('recipients.store'), ['name' => 'Rina', 'department_id' => $dept['id']])->assertCreated()->json();
        $this->assertSame(['id', 'name'], array_keys($rec));
        $this->postJson(route('recipients.store'), ['name' => ''])->assertStatus(422);

        $this->get(route('docs.create', 'issuance'))->assertOk()->assertDontSee('qa-recipient', false)->assertDontSee('Penerima baru', false);
        $this->get(route('departments.index'))->assertOk();
        $this->get(route('recipients.index'))->assertOk();
        $this->get(route('recipients.show', $this->recipient))->assertOk();
    }

    public function test_reports_render_and_hide_valuation_from_data_entry(): void
    {
        $item = $this->item();
        $this->receive($item, 10, 1234);

        foreach (['reports.summary', 'reports.receiving', 'reports.issuance', 'reports.stock-card'] as $route) {
            $this->actingAs($this->entry)->get(route($route, ['batch_id' => $item->id]))->assertOk()->assertDontSee('Nilai Stok')->assertDontSee('Harga Beli')->assertDontSee('1234');
        }
        $this->actingAs($this->supervisor)->get(route('reports.summary'))->assertOk()->assertSee('Total Nilai Stok');
        $this->get(route('reports.receiving'))->assertOk()->assertSee('Harga Beli');
        $this->get(route('reports.adjustment'))->assertOk();
        $this->get(route('reports.stock-card', ['batch_id' => $item->id, 'from' => today()->subDay()->toDateString(), 'to' => today()->toDateString()]))->assertOk()->assertSee('Item I1');
    }
}
