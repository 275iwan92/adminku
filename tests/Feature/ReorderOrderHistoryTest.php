<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Batch;
use App\Models\ReorderOrder;
use App\Models\StockDocument;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\ReorderOrders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga layar Riwayat Pemesanan (modul ORD): daftar memuat semua status (Dipesan, Diterima sebagian, Selesai, Ditutup manual) tanpa nilai uang, filter tanggal dan status, pesanan lama tanpa jumlah tampil "-" tanpa galat,
 *                detail beserta penerimaan yang dialokasikan (jumlahnya sama dengan received_qty hasil reconcile), tombol Tutup Pesanan hanya untuk yang berhak dan hanya pesanan terbuka, hak akses modul, dan migrasi pemberian modul.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class ReorderOrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $sup;

    private User $entry;

    private Batch $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sup = User::factory()->supervisor()->create();
        $this->entry = User::factory()->create();
        $this->actingAs($this->sup);
        $this->item = $this->makeBatch(['code' => 'H-1', 'name' => 'Kertas HVS', 'category_id' => Category::create(['code' => 'ATK', 'name' => 'ATK'])->id, 'unit' => 'pcs', 'unit_price' => 10, 'reorder_point' => 5]);
    }

    private function supplier(string $name = 'Toko Maju'): Supplier
    {
        return Supplier::firstOrCreate(['name' => $name], ['code' => 'S'.substr(md5($name), 0, 5)]);
    }

    private function order(?float $qty, int $daysAgo = 1, string $ref = 'PO', string $supplier = 'Toko Maju'): ReorderOrder
    {
        return ReorderOrder::create(['product_id' => $this->item->product_id, 'ordered_at' => today()->subDays($daysAgo), 'ref_no' => $ref, 'qty' => $qty, 'supplier_id' => $this->supplier($supplier)->id, 'created_by' => $this->sup->id]);
    }

    private function receive(float $qty): StockDocument
    {
        $payload = ['supplier_id' => $this->supplier()->id, 'lines' => [['batch_id' => $this->item->id, 'qty' => $qty, 'unit_cost' => 10]]];
        $this->post(route('docs.store', 'receiving'), $payload)->assertSessionHasNoErrors();
        $doc = StockDocument::latest('id')->firstOrFail();
        $this->put(route('docs.update', ['receiving', $doc]), $payload + ['_action' => 'confirm'])->assertSessionHasNoErrors();

        return $doc->fresh();
    }

    public function test_the_list_shows_every_status_without_money_and_is_in_the_transaction_menu(): void
    {
        $open = $this->order(10, 5, 'PO-OPEN');
        $partial = $this->order(30, 4, 'PO-PART');
        $legacy = $this->order(null, 3, 'PO-LAMA');
        $this->receive(12);   // PO-OPEN penuh (10), sisa 2 ke PO-PART
        $manual = $this->order(8, 2, 'PO-MANUAL');
        $this->post(route('reorder-orders.close', $manual), ['note' => 'tidak dikirim'])->assertRedirect(route('home'));

        $res = $this->get(route('reorder-orders.index'))->assertOk();
        foreach (['PO-OPEN', 'PO-PART', 'PO-LAMA', 'PO-MANUAL', 'Selesai', 'Diterima sebagian', 'Ditutup manual', 'Toko Maju', 'Kertas HVS', 'Riwayat Pemesanan'] as $text) {
            $res->assertSee($text);
        }
        foreach ($res->viewData('rows') as $r) {
            $this->assertSame([], array_values(array_intersect(array_keys($r), ['price', 'cost', 'amount', 'value', 'unit_price'])));   // tanpa nilai uang
        }
        $this->assertSame('Selesai', $open->fresh()->statusLabel());
        $this->assertSame('Diterima sebagian', $partial->fresh()->statusLabel());
        $this->assertSame('-', collect($res->viewData('rows'))->firstWhere('id', $legacy->id)['received']);   // lama tanpa jumlah: tanpa galat
        $this->assertSame(['-', '-', '-'], array_values(array_intersect_key(collect($res->viewData('rows'))->firstWhere('id', $legacy->id), array_flip(['qty', 'received', 'remaining']))));

        $this->get(route('home'))->assertSee('Riwayat Pemesanan');
    }

    public function test_row_values_remaining_lead_time_and_closing_note(): void
    {
        $o = $this->order(30, 4, 'PO-1');
        $this->receive(12);
        $manual = $this->order(8, 2, 'PO-M');
        $this->post(route('reorder-orders.close', $manual), ['note' => 'tidak dikirim']);

        $rows = collect($this->get(route('reorder-orders.index'))->viewData('rows'));
        $a = $rows->firstWhere('id', $o->id);
        $this->assertSame(['Diterima sebagian', '12', '18', '4'], [$a['status'], $a['received'], $a['remaining'], $a['lead']]);
        $this->assertStringContainsString('30', $a['qty']);
        $m = $rows->firstWhere('id', $manual->id);
        $this->assertSame('Ditutup manual', $m['status']);
        $this->assertStringContainsString('Ditutup: tidak dikirim', $m['closed']);
        $this->assertSame('-', $m['lead']);
    }

    public function test_lead_time_is_blank_until_the_first_receiving_and_zero_when_received_the_same_day(): void
    {
        $ordered = $this->order(10, 3, 'PO-BELUM');
        $rows = fn () => collect($this->get(route('reorder-orders.index'))->viewData('rows'));
        $this->assertSame(['Dipesan', '-', null], [$rows()->firstWhere('id', $ordered->id)['status'], $rows()->firstWhere('id', $ordered->id)['lead'], $rows()->firstWhere('id', $ordered->id)['first_received']]);
        $this->get(route('reorder-orders.show', $ordered))->assertOk()->assertDontSee('0 hari');

        $sameDay = $this->order(5, 0, 'PO-HARIINI');
        $this->receive(5);   // penerimaan hari ini: memenuhi PO-BELUM (10)? tidak; jumlah masuk FIFO ke yang lebih lama dulu
        $sameDay = $sameDay->fresh();
        $this->receive(10);
        $row = $rows()->firstWhere('id', $sameDay->id);
        $this->assertSame('0', $row['lead']);
        $this->get(route('reorder-orders.show', $sameDay))->assertOk()->assertSee('0 hari');
    }

    public function test_date_range_filter(): void
    {
        $old = $this->order(5, 40, 'PO-TUA');
        $new = $this->order(5, 2, 'PO-BARU');

        $ids = fn (array $q) => collect($this->get(route('reorder-orders.index', $q))->assertOk()->viewData('rows'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$old->id, $new->id], $ids([]));
        $this->assertSame([$new->id], $ids(['from' => today()->subDays(10)->toDateString()]));
        $this->assertSame([$old->id], $ids(['to' => today()->subDays(10)->toDateString()]));
        $this->get(route('reorder-orders.index', ['from' => 'bukan-tanggal']))->assertSessionHasErrors('from');
    }

    public function test_detail_lists_the_allocated_receipts_and_matches_reconcile(): void
    {
        $first = $this->order(10, 5, 'PO-A');
        $second = $this->order(20, 4, 'PO-B');
        $d1 = $this->receive(7);
        $d2 = $this->receive(8);   // total 15: A=10 (7 + 3), B=5 (5 dari d2)
        $service = app(ReorderOrders::class);

        foreach ([$first, $second] as $o) {
            $this->assertEqualsWithDelta((float) $o->fresh()->received_qty, array_sum(array_column($service->allocations($o->fresh()), 'qty')), 0.0005);
        }
        $this->assertSame([$d1->doc_no, $d2->doc_no], array_column($service->allocations($first->fresh()), 'doc_no'));
        $this->assertSame([7.0, 3.0], array_column($service->allocations($first->fresh()), 'qty'));
    }

    public function test_detail_page_shows_order_data_and_allocations(): void
    {
        $o = $this->order(10, 3, 'PO-D');
        $doc = $this->receive(4);

        $this->get(route('reorder-orders.show', $o))->assertOk()->assertSee('PO-D')->assertSee('Toko Maju')->assertSee($doc->doc_no)->assertSee('Diterima sebagian')->assertSee('Tutup Pesanan');
        $this->get(route('reorder-orders.show', $this->order(5, 1, 'PO-E')))->assertOk()->assertSee('Belum ada penerimaan');
    }

    public function test_legacy_order_without_quantity_and_manual_order_details_do_not_break(): void
    {
        $legacy = $this->order(null, 3, 'PO-LAMA');
        $doc = $this->receive(4);
        $this->get(route('reorder-orders.show', $legacy))->assertOk()->assertSee('Pesanan lama tanpa jumlah')->assertSee($doc->doc_no)->assertSee('Selesai');

        $manual = $this->order(9, 2, 'PO-M');
        $this->post(route('reorder-orders.close', $manual), ['note' => 'batal', 'return' => 'history'])->assertRedirect(route('reorder-orders.show', $manual));
        $this->get(route('reorder-orders.show', $manual))->assertOk()->assertSee('penerimaan tidak dirinci')->assertDontSee('Tutup Pesanan');
    }

    public function test_close_button_only_for_writers_and_only_open_orders(): void
    {
        $open = $this->order(10, 3, 'PO-O');
        $done = $this->order(2, 4, 'PO-DONE');
        $this->receive(2);

        $this->get(route('reorder-orders.show', $open))->assertSee('close-order-modal', false);
        $this->get(route('reorder-orders.show', $done))->assertDontSee('close-order-modal', false);

        $group = UserGroup::create(['code' => 'RO', 'name' => 'Baca']);
        $group->syncAccess(['ORD' => 'ro']);
        $reader = User::factory()->create(['group_id' => $group->id]);
        $this->actingAs($reader)->get(route('reorder-orders.show', $open))->assertOk()->assertDontSee('close-order-modal', false);

        $this->actingAs($this->entry)->get(route('reorder-orders.show', $open))->assertOk()->assertSee('close-order-modal', false);
        $this->post(route('reorder-orders.close', $open), ['note' => 'x', 'return' => 'history'])->assertRedirect(route('reorder-orders.show', $open));
        $this->assertSame('manual', $open->fresh()->close_reason);
    }

    public function test_access_follows_the_module(): void
    {
        $this->actingAs($this->entry)->get(route('reorder-orders.index'))->assertOk();

        $group = UserGroup::create(['code' => 'GD', 'name' => 'Gudang']);
        $group->syncAccess(['ISS' => 'rw']);
        $limited = User::factory()->create(['group_id' => $group->id]);
        $order = $this->order(5);
        $this->actingAs($limited)->get(route('reorder-orders.index'))->assertForbidden();
        $this->get(route('reorder-orders.show', $order))->assertForbidden();
        $this->get(route('home'))->assertOk();   // Beranda dan Catat Pemesanan tetap terbuka
        auth()->logout();
        $this->get(route('reorder-orders.index'))->assertRedirect(route('login'));
    }

    public function test_writing_orders_needs_rw_on_the_orders_module_on_the_server(): void
    {
        $open = $this->order(10, 3, 'PO-W');
        $payload = ['product_id' => $this->item->product_id, 'supplier_id' => $this->supplier()->id, 'qty' => 5, 'ordered_at' => today()->toDateString()];
        $mk = fn (string $code, array $access) => tap(UserGroup::create(['code' => $code, 'name' => $code]), fn ($g) => $g->syncAccess($access));
        $ro = User::factory()->create(['group_id' => $mk('RO', ['ORD' => 'ro', 'RCV' => 'rw'])->id]);
        $none = User::factory()->create(['group_id' => $mk('NO', ['RCV' => 'rw', 'ISS' => 'rw'])->id]);
        $rw = User::factory()->create(['group_id' => $mk('RW', ['ORD' => 'rw'])->id]);

        foreach ([$ro, $none] as $user) {
            $this->actingAs($user)->post(route('reorder-orders.store'), $payload)->assertForbidden();
            $this->post(route('reorder-orders.close', $open), ['note' => 'x'])->assertForbidden();
        }
        $this->assertNull($open->fresh()->closed_at);
        $this->assertSame(1, ReorderOrder::count());

        $this->actingAs($rw)->post(route('reorder-orders.store'), ['supplier_id' => 0] + $payload)->assertSessionHasErrors('supplier_id');   // lolos penjaga modul, ditolak validasi
        $this->post(route('reorder-orders.close', $open), ['note' => 'x'])->assertRedirect(route('home'));
        $this->assertSame('manual', $open->fresh()->close_reason);
        $this->actingAs($this->entry)->post(route('reorder-orders.store'), ['supplier_id' => 0] + $payload)->assertSessionHasErrors('supplier_id');   // Data Entry tanpa grup tetap bisa
    }

    public function test_home_hides_the_order_buttons_without_write_access(): void
    {
        $this->order(10, 3, 'PO-H');
        $group = UserGroup::create(['code' => 'RO', 'name' => 'RO']);
        $group->syncAccess(['ORD' => 'ro', 'ITM' => 'rw']);
        $ro = User::factory()->create(['group_id' => $group->id]);

        $this->actingAs($ro)->get(route('home'))->assertOk()->assertDontSee('reorder-modal', false)->assertDontSee('close-order-', false);
        $this->actingAs($this->entry)->get(route('home'))->assertOk()->assertSee('close-order-', false);
    }

    public function test_migration_follows_receiving_rights(): void
    {
        $writer = UserGroup::create(['code' => 'W', 'name' => 'Tulis']);
        $writer->syncAccess(['RCV' => 'rw']);
        $reader = UserGroup::create(['code' => 'R', 'name' => 'Baca']);
        $reader->syncAccess(['RCV' => 'ro']);
        $other = UserGroup::create(['code' => 'O', 'name' => 'Lain']);
        $other->syncAccess(['ISS' => 'rw']);

        $this->runLegacyGroupMigration('2026_11_16_100000_give_order_history_to_groups.php');

        $this->assertSame('rw', $writer->fresh()->moduleAccess()['ORD'] ?? null);
        $this->assertSame('ro', $reader->fresh()->moduleAccess()['ORD'] ?? null);
        $this->assertArrayNotHasKey('ORD', $other->fresh()->moduleAccess());
    }
}
