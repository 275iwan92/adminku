<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\StockDocument;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserGroup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga Keputusan Pesanan (PUK) dan Pesanan Pembelian (PO, PUO): riwayat beli per pemasok (terakhir, harga terakhir, berapa kali, rincian) dari Penerimaan terkonfirmasi, barang belum pernah dibeli hanya info,
 *                Buat PO menambah ke draf PO pemasok (atau membuat baru) dengan harga terakhir, link ke baris PP dan jumlah dipesan / status PP, nomor PO-YYMM-0001, siklus Draf > Diajukan > Disetujui / Ditolak > Dibatalkan,
 *                pembuat tidak boleh menyetujui sendiri, PPN opsional, syarat bayar dari Pemasok, cetak hanya setelah Disetujui, dan hak akses modul.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $boss;

    private $paper;

    private Supplier $maju;

    private Supplier $sinar;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 08:00:00');
        $group = UserGroup::create(['code' => 'PUG', 'name' => 'Purchase']);
        $group->syncAccess(['PUP' => 'rw', 'PUK' => 'rw', 'PUO' => 'rw']);
        $this->staff = User::factory()->create(['group_id' => $group->id]);
        $this->boss = User::factory()->supervisor()->create(['group_id' => $group->id]);
        $cat = Category::firstOrCreate(['code' => 'ATK'], ['name' => 'ATK']);
        $this->paper = $this->makeBatch(['code' => 'KRT', 'name' => 'Kertas A4', 'category_id' => $cat->id, 'unit' => 'pcs', 'unit_price' => 100, 'reorder_point' => 0, 'reorder_qty' => 50, 'lead_time_days' => 3]);
        $this->maju = Supplier::create(['code' => 'S1', 'name' => 'CV Maju', 'email' => 'budi@maju.co.id', 'payment_term_days' => 30]);
        $this->sinar = Supplier::create(['code' => 'S2', 'name' => 'Toko Sinar', 'payment_term_days' => 0]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function receive(Supplier $supplier, int|float $cost, int $qty = 10, string $date = '2026-10-01'): void
    {
        Carbon::setTestNow($date.' 09:00:00');
        $doc = ['supplier_id' => $supplier->id, 'lines' => [['batch_id' => $this->paper->id, 'qty' => $qty, 'unit_cost' => $cost]]];
        $this->actingAs(User::factory()->administrator()->create())->post(route('docs.store', 'receiving'), $doc);
        $this->put(route('docs.update', ['receiving', StockDocument::latest('id')->firstOrFail()]), $doc + ['_action' => 'confirm']);
        Carbon::setTestNow('2026-11-02 08:00:00');
    }

    private function payload(array $over = []): array
    {
        return $over + ['doc_date' => '2026-11-02', 'supplier_id' => $this->maju->id, 'payment_term_days' => 30, 'need_date' => '2026-11-10', 'ship_to' => 'Gudang Utama', 'vat_pct' => 0, 'note' => 'Kirim pagi', 'lines' => [
            ['item_id' => $this->paper->item_id, 'qty' => 20, 'price' => 52000],
            ['item_name' => 'Map plastik', 'qty' => 50, 'unit' => 'pcs', 'price' => 3500],
        ]];
    }

    private function create(array $over = [], ?User $by = null): PurchaseOrder
    {
        $this->actingAs($by ?? $this->staff)->post(route('po.store'), $this->payload($over))->assertSessionHasNoErrors();

        return PurchaseOrder::latest('id')->firstOrFail();
    }

    private function act(PurchaseOrder $po, string $action, User $by, array $extra = [])
    {
        return $this->actingAs($by)->put(route('po.update', $po), ['_action' => $action] + $extra);
    }

    public function test_decision_shows_history_per_supplier_and_unknown_items_only_info(): void
    {
        $this->receive($this->maju, 50000, 10, '2026-09-01');
        $this->receive($this->maju, 52000, 10, '2026-10-01');
        $this->receive($this->sinar, 55000, 10, '2026-10-10');
        $q = ['proses' => 1, 'item_id' => $this->paper->item_id, 'qty' => 20];

        $page = $this->actingAs($this->staff)->get(route('po-decision.index', $q))->assertOk()->assertSee('CV Maju', false)->assertSee('Toko Sinar', false)->assertSee('Riwayat', false);
        $history = $page->viewData('result')['history'];
        $this->assertSame(['Toko Sinar', 'CV Maju'], $history->pluck('supplier')->all());   // terakhir dipakai di atas
        $this->assertSame([1, 2], $history->pluck('times')->all());
        $this->assertEquals(52000, $history->firstWhere('supplier', 'CV Maju')->last_price);
        $this->assertCount(2, $history->firstWhere('supplier', 'CV Maju')->rows);

        $this->get(route('po-decision.index', ['proses' => 1, 'item_name' => 'Barang baru', 'unit' => 'pcs', 'qty' => 3]))->assertOk()->assertSee('belum pernah dibeli', false);
        $this->get(route('po-decision.index', ['proses' => 1, 'item_id' => $this->paper->item_id]))->assertSessionHasErrors('qty');
    }

    public function test_make_po_creates_then_reuses_the_users_draft_for_that_supplier_with_the_last_price(): void
    {
        $this->receive($this->maju, 52000);
        $base = ['supplier_id' => $this->maju->id, 'item_id' => $this->paper->item_id, 'qty' => 20];

        $this->actingAs($this->staff)->post(route('po-decision.make'), $base)->assertRedirect();
        $po = PurchaseOrder::firstOrFail();
        $this->assertSame(['draft', null, 30], [$po->status, $po->doc_no, $po->payment_term_days]);   // draf belum bernomor: nomor lahir saat Confirm
        $this->assertEquals(52000, $po->lines[0]->price);

        $this->post(route('po-decision.make'), ['supplier_id' => $this->maju->id, 'item_name' => 'Map', 'unit' => 'pcs', 'qty' => 5]);
        $this->assertSame(1, PurchaseOrder::count());   // draf yang sama
        $this->assertCount(2, $po->fresh()->lines);

        $this->post(route('po-decision.make'), ['supplier_id' => $this->sinar->id] + $base);
        $this->assertSame(2, PurchaseOrder::count());   // pemasok lain = PO lain
        $this->post(route('po-decision.make'), array_merge($base, ['supplier_id' => '']))->assertSessionHasErrors('supplier_id');
    }

    public function test_make_po_from_a_pp_line_links_and_updates_ordered_qty_and_pp_status(): void
    {
        $this->actingAs($this->staff)->post(route('pr.store'), ['doc_date' => '2026-11-02', 'department_id' => \App\Models\Department::create(['code' => 'GDG', 'name' => 'Gudang'])->id, 'lines' => [['item_id' => $this->paper->item_id, 'qty' => 20], ['item_name' => 'Map', 'qty' => 5, 'unit' => 'pcs']]]);
        $pr = PurchaseRequest::firstOrFail();
        $this->put(route('pr.update', $pr), ['_action' => 'confirm', 'doc_date' => '2026-11-02', 'department_id' => $pr->department_id, 'lines' => [['item_id' => $this->paper->item_id, 'qty' => 20], ['item_name' => 'Map', 'qty' => 5, 'unit' => 'pcs']]]);
        $this->put(route('pr.update', $pr), ['_action' => 'approve'])->assertForbidden();   // bukan Supervisor
        $this->actingAs($this->boss)->put(route('pr.update', $pr), ['_action' => 'approve']);
        $line = $pr->lines()->first();

        $page = $this->actingAs($this->staff)->get(route('po-decision.index', ['mode' => 'pp']))->assertOk()->assertSee($pr->fresh()->doc_no, false);
        $this->assertCount(2, $page->viewData('outstanding'));

        $this->post(route('po-decision.make'), ['supplier_id' => $this->maju->id, 'item_id' => $this->paper->item_id, 'qty' => 8, 'line' => $line->id]);
        $this->assertEquals(8, $line->fresh()->ordered_qty);
        $this->assertSame('partial', $pr->fresh()->status);

        $po = PurchaseOrder::firstOrFail();
        $this->assertSame($line->id, $po->lines[0]->request_line_id);
        $this->act($po, 'delete', $this->staff)->assertRedirect(route('po.index'));   // draf dihapus: jumlah dipesan kembali
        $this->assertEquals(0, $line->fresh()->ordered_qty);
        $this->assertSame('approved', $pr->fresh()->status);

        $this->post(route('po-decision.make'), ['supplier_id' => $this->maju->id, 'item_id' => $this->paper->item_id, 'qty' => 20, 'line' => $line->id]);
        $this->post(route('po-decision.make'), ['supplier_id' => $this->maju->id, 'item_name' => 'Map', 'unit' => 'pcs', 'qty' => 5, 'line' => $pr->lines()->get()->last()->id]);
        $this->assertSame('done', $pr->fresh()->status);   // semua baris terpesan penuh
    }

    public function test_po_numbers_validation_totals_and_vat(): void
    {
        $a = $this->create();
        $b = $this->create(['vat_pct' => 11]);

        $this->assertSame([null, null], [$a->doc_no, $b->doc_no]);   // nomor lahir saat Confirm
        $this->act($a, 'confirm', $this->staff, $this->payload())->assertSessionHasNoErrors();
        $this->act($b, 'confirm', $this->staff, $this->payload(['vat_pct' => 11]))->assertSessionHasNoErrors();
        $this->assertSame(['PO-2611-0001', 'PO-2611-0002'], [$a->fresh()->doc_no, $b->fresh()->doc_no]);
        $this->assertSame([2, 'approval'], [$a->lines()->count(), $a->fresh()->status]);
        $this->assertEquals(1215000, $a->total());                 // 20 x 52.000 + 50 x 3.500
        $this->assertEqualsWithDelta(1215000 * 1.11, $b->total(), 0.01);   // PPN opsional

        $this->actingAs($this->staff)->post(route('po.store'), $this->payload(['supplier_id' => '']))->assertSessionHasErrors('supplier_id');
        $this->post(route('po.store'), $this->payload(['lines' => [['item_id' => $this->paper->item_id, 'qty' => 1]]]))->assertSessionHasErrors('lines.0.price');
        $this->post(route('po.store'), $this->payload(['need_date' => '2026-10-01']))->assertSessionHasErrors('need_date');
    }

    public function test_cycle_creator_cannot_approve_reason_needed_and_print_only_when_approved(): void
    {
        $po = $this->create(by: $this->boss);
        $this->get(route('po.print', $po))->assertForbidden();   // draf belum bisa dicetak
        $this->act($po, 'confirm', $this->boss, $this->payload())->assertSessionHasNoErrors();
        $this->assertSame('approval', $po->fresh()->status);

        $this->act($po, 'approve', $this->boss)->assertForbidden();    // pembuat sendiri
        $this->act($po, 'approve', $this->staff)->assertForbidden();   // bukan Supervisor
        $this->act($po, 'save', $this->boss, $this->payload())->assertStatus(422);   // terkunci
        $other = User::factory()->supervisor()->create(['group_id' => $this->staff->group_id]);
        $this->act($po, 'approve', $other)->assertRedirect();
        $this->assertSame('approved', $po->fresh()->status);

        $this->get(route('po.print', $po))->assertOk()->assertSee($po->fresh()->doc_no, false)->assertSee('CV Maju', false);
        $this->act($po, 'cancel', $this->boss)->assertStatus(422);   // alasan wajib
        $this->act($po, 'cancel', $this->boss, ['_reason' => 'Tidak jadi'])->assertRedirect();
        $this->assertSame('cancelled', $po->fresh()->status);

        $rej = $this->create();
        $this->act($rej, 'confirm', $this->staff, $this->payload());
        $this->act($rej, 'reject', $this->boss)->assertStatus(422);
        $this->act($rej, 'reject', $this->boss, ['_reason' => 'Harga mahal'])->assertRedirect();
        $this->act($rej, 'duplicate', $this->staff)->assertRedirect();
        $copy = PurchaseOrder::latest('id')->first();
        $this->assertSame(['draft', 2, $this->staff->id], [$copy->status, $copy->lines()->count(), $copy->created_by]);
    }

    public function test_received_po_cannot_be_cancelled_and_screens_follow_group_rights(): void
    {
        $po = $this->create();
        $this->act($po, 'confirm', $this->staff, $this->payload());
        $this->act($po, 'approve', $this->boss);
        $po->lines()->first()->update(['received_qty' => 5]);
        $this->act($po, 'cancel', $this->boss, ['_reason' => 'x'])->assertStatus(422);
        $this->assertSame('approved', $po->fresh()->status);

        $this->actingAs($this->staff)->get(route('po.index'))->assertOk()->assertSee($po->fresh()->doc_no, false);
        $this->get(route('po.create'))->assertOk();
        $this->get(route('po.show', $po))->assertOk()->assertSee('Map plastik', false)->assertSee('Riwayat', false);
        $this->get(route('po-decision.index'))->assertOk();

        $reader = UserGroup::create(['code' => 'PUR-RO', 'name' => 'Baca']);
        $reader->syncAccess(['PUO' => 'ro', 'PUK' => 'ro']);
        $this->actingAs(User::factory()->create(['group_id' => $reader->id]))->get(route('po.index'))->assertOk();
        $this->post(route('po.store'), $this->payload())->assertForbidden();
        $this->post(route('po-decision.make'), ['supplier_id' => $this->maju->id, 'item_id' => $this->paper->item_id, 'qty' => 1])->assertForbidden();
        $this->actingAs(User::factory()->supervisor()->create())->get(route('po.index'))->assertForbidden();   // tanpa Grup
    }
}
