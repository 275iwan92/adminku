<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\StockDocument;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserGroup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga Penerimaan Pembelian (PV, PUV): hanya dari PO Disetujui / Diterima sebagian, jumlah diterima tidak melebihi sisa, Konfirmasi membuat dan mengonfirmasi Penerimaan Barang (RCV) di Stok (pemasok, harga PO, surat jalan, stok bertambah),
 *                jumlah diterima dan status PO (Diterima sebagian / Selesai) ikut, barang teks bebas dan kedaluwarsa wajib ditolak, Batalkan hanya Supervisor (stok kembali, PO kembali), Hapus hanya draf, dan hak akses modul.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class PurchaseReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $boss;

    private $paper;

    private $milk;

    private Supplier $maju;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 08:00:00');
        $group = UserGroup::create(['code' => 'PUG', 'name' => 'Purchase']);
        $group->syncAccess(['PUP' => 'rw', 'PUK' => 'rw', 'PUO' => 'rw', 'PUV' => 'rw']);
        $this->staff = User::factory()->create(['group_id' => $group->id]);
        $this->boss = User::factory()->supervisor()->create(['group_id' => $group->id]);
        $cat = Category::firstOrCreate(['code' => 'ATK'], ['name' => 'ATK']);
        $this->paper = $this->makeBatch(['code' => 'KRT', 'name' => 'Kertas A4', 'category_id' => $cat->id, 'unit' => 'pcs', 'unit_price' => 100, 'reorder_point' => 0, 'reorder_qty' => 50, 'lead_time_days' => 3]);
        $this->milk = $this->makeBatch(['code' => 'SSU', 'name' => 'Susu UHT', 'category_id' => $cat->id, 'unit' => 'pcs', 'unit_price' => 100, 'reorder_point' => 0, 'reorder_qty' => 50, 'lead_time_days' => 3, 'tracks_expiry' => true, 'expiry_date' => '2027-05-20']);
        $this->maju = Supplier::create(['code' => 'S1', 'name' => 'CV Maju']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // PO Disetujui: kertas 20 x 52.000, susu 24 x 7.500; opsional baris teks bebas.
    private function approvedPo(bool $freeText = false): PurchaseOrder
    {
        $lines = [['item_id' => $this->paper->item_id, 'qty' => 20, 'price' => 52000], ['item_id' => $this->milk->item_id, 'qty' => 24, 'price' => 7500]];
        if ($freeText) {
            $lines[] = ['item_name' => 'Map baru', 'qty' => 5, 'unit' => 'pcs', 'price' => 1000];
        }
        $payload = ['doc_date' => '2026-11-02', 'supplier_id' => $this->maju->id, 'lines' => $lines];
        $this->actingAs($this->staff)->post(route('po.store'), $payload)->assertSessionHasNoErrors();
        $po = PurchaseOrder::latest('id')->firstOrFail();
        $this->put(route('po.update', $po), ['_action' => 'confirm'] + $payload);
        $this->actingAs($this->boss)->put(route('po.update', $po), ['_action' => 'approve']);

        return $po->fresh();
    }

    private function lineIds(PurchaseOrder $po): array
    {
        return $po->lines()->pluck('id')->all();
    }

    private function pvPayload(PurchaseOrder $po, array $qty, array $expiry = []): array
    {
        $lines = [];
        foreach ($this->lineIds($po) as $i => $id) {
            $lines[$id] = ['qty' => $qty[$i] ?? 0] + (isset($expiry[$i]) ? ['expiry_date' => $expiry[$i]] : []);
        }

        return ['order_id' => $po->id, 'doc_date' => '2026-11-10', 'ref_no' => 'SJ-8841', 'receiver' => 'Rina', 'note' => 'Dus penyok', 'lines' => $lines];
    }

    private function pv(PurchaseOrder $po, array $qty, array $expiry = [], string $action = 'save'): PurchaseReceipt
    {
        $payload = $this->pvPayload($po, $qty, $expiry);
        $this->actingAs($this->staff)->post(route('pv.store'), $payload)->assertSessionHasNoErrors();
        $pv = PurchaseReceipt::latest('id')->firstOrFail();
        if ($action === 'confirm') {
            $this->put(route('pv.update', $pv), ['_action' => 'confirm'] + $payload)->assertSessionHasNoErrors();
        }

        return $pv->fresh();
    }

    public function test_confirm_creates_a_confirmed_rcv_adds_stock_and_updates_the_po(): void
    {
        $po = $this->approvedPo();
        $pv = $this->pv($po, [8, 24], [null, '2027-05-20'], 'confirm');

        $this->assertSame(['confirmed', 'PV-2611-0001'], [$pv->status, $pv->doc_no]);
        $rcv = StockDocument::findOrFail($pv->stock_document_id);
        $this->assertSame(['confirmed', $this->maju->id, 'SJ-8841'], [$rcv->status, $rcv->supplier_id, $rcv->ref_no]);
        $this->assertEquals(8, $this->paper->fresh()->stock_qty);
        $this->assertEquals(24, $this->milk->fresh()->stock_qty);
        $this->assertEquals(52000, $rcv->lines()->where('batch_id', $this->paper->id)->first()->unit_cost);   // harga dari PO

        $po->refresh();
        $this->assertSame('partial', $po->status);
        $this->assertSame([8.0, 24.0], $po->lines->map(fn ($l) => (float) $l->received_qty)->all());
        $this->assertSame(['SJ-8841'], [PurchaseReceipt::first()->ref_no]);

        $pv2 = $this->pv($po, [12], [], 'confirm');   // sisa kertas; susu sudah penuh
        $this->assertSame('done', $po->fresh()->status);
        $this->assertEquals(20, $this->paper->fresh()->stock_qty);
        $this->assertSame('PV-2611-0002', $pv2->doc_no);
    }

    public function test_receiving_is_limited_to_the_remaining_qty_and_needs_something_received(): void
    {
        $po = $this->approvedPo();

        $this->actingAs($this->staff)->post(route('pv.store'), $this->pvPayload($po, [21]))->assertSessionHasErrors('lines');
        $this->post(route('pv.store'), $this->pvPayload($po, [0, 0]))->assertSessionHasErrors('lines');
        $this->post(route('pv.store'), array_merge($this->pvPayload($po, [5]), ['order_id' => 999]))->assertNotFound();

        $this->pv($po, [15, 0], [], 'confirm');
        $this->post(route('pv.store'), $this->pvPayload($po, [6]))->assertSessionHasErrors('lines');   // sisa kertas 5
        $this->post(route('pv.store'), $this->pvPayload($po, [5]))->assertSessionHasNoErrors();

        $draft = $this->actingAs($this->staff)->get(route('pv.create', ['po' => $po->id]))->assertOk()->viewData('rows');
        $this->assertSame([5.0, 24.0], array_column($draft, 'left'));
    }

    public function test_free_text_items_and_missing_expiry_block_confirm_and_nothing_is_created(): void
    {
        $po = $this->approvedPo(true);
        $ids = $this->lineIds($po);

        $draft = $this->pv($po, [0, 0, 5]);   // draf boleh, Konfirmasi tidak
        $this->put(route('pv.update', $draft), ['_action' => 'confirm'] + $this->pvPayload($po, [0, 0, 5]))->assertSessionHasErrors('lines');

        $draft2 = $this->pv($po, [0, 24]);
        $this->put(route('pv.update', $draft2), ['_action' => 'confirm'] + $this->pvPayload($po, [0, 24]))->assertSessionHasErrors('lines');   // susu wajib kedaluwarsa
        $this->put(route('pv.update', $draft2), ['_action' => 'confirm'] + $this->pvPayload($po, [0, 24], [null, '2026-10-01']))->assertSessionHasErrors('lines');   // sudah lewat

        $this->assertSame(0, StockDocument::count());
        $this->assertSame(['draft', 'draft'], PurchaseReceipt::orderBy('id')->pluck('status')->all());
        $this->assertSame('approved', $po->fresh()->status);
        $this->assertCount(3, $ids);
    }

    public function test_only_approved_or_partial_pos_receive_goods(): void
    {
        $payload = ['doc_date' => '2026-11-02', 'supplier_id' => $this->maju->id, 'lines' => [['item_id' => $this->paper->item_id, 'qty' => 5, 'price' => 1000]]];
        $this->actingAs($this->staff)->post(route('po.store'), $payload);
        $draftPo = PurchaseOrder::firstOrFail();

        $this->get(route('pv.create', ['po' => $draftPo->id]))->assertOk();   // PO draf tidak dimuat
        $this->assertSame([], $this->get(route('pv.create', ['po' => $draftPo->id]))->viewData('rows'));
        $this->post(route('pv.store'), ['order_id' => $draftPo->id, 'doc_date' => '2026-11-10', 'lines' => [1 => ['qty' => 1]]])->assertNotFound();
    }

    public function test_cancel_is_supervisor_only_returns_stock_and_reopens_the_po(): void
    {
        $po = $this->approvedPo();
        $pv = $this->pv($po, [8, 0], [], 'confirm');

        $this->actingAs($this->staff)->put(route('pv.update', $pv), ['_action' => 'cancel', '_reason' => 'Salah input'])->assertForbidden();
        $this->actingAs($this->boss)->put(route('pv.update', $pv), ['_action' => 'cancel'])->assertStatus(422);   // alasan wajib
        $this->put(route('pv.update', $pv), ['_action' => 'cancel', '_reason' => 'Salah input'])->assertRedirect();

        $this->assertSame('cancelled', $pv->fresh()->status);
        $this->assertSame('cancelled', StockDocument::findOrFail($pv->stock_document_id)->status);
        $this->assertEquals(0, $this->paper->fresh()->stock_qty);
        $this->assertSame('approved', $po->fresh()->status);
        $this->assertEquals(0, $po->fresh()->lines[0]->received_qty);

        $this->pv($po, [8], [], 'confirm');   // bisa diterima ulang penuh
        $this->assertEquals(8, $this->paper->fresh()->stock_qty);
    }

    public function test_drafts_can_be_deleted_confirmed_cannot_and_screens_follow_group_rights(): void
    {
        $po = $this->approvedPo();
        $draft = $this->pv($po, [3]);
        $this->actingAs($this->staff)->put(route('pv.update', $draft), ['_action' => 'delete'])->assertRedirect(route('pv.index'));
        $this->assertNull(PurchaseReceipt::find($draft->id));

        $pv = $this->pv($po, [3], [], 'confirm');
        $this->put(route('pv.update', $pv), ['_action' => 'delete'])->assertStatus(422);
        $this->put(route('pv.update', $pv), ['_action' => 'save'] + $this->pvPayload($po, [4]))->assertStatus(422);   // terkunci

        $this->get(route('pv.index'))->assertOk()->assertSee($pv->doc_no, false)->assertSee('SJ-8841', false);
        $this->get(route('pv.show', $pv))->assertOk()->assertSee('Kertas A4', false)->assertSee(StockDocument::find($pv->stock_document_id)->doc_no, false);
        $this->get(route('po.show', $po))->assertOk()->assertSee('Terima barang', false);

        $reader = UserGroup::create(['code' => 'PUR-RO', 'name' => 'Baca']);
        $reader->syncAccess(['PUV' => 'ro']);
        $this->actingAs(User::factory()->create(['group_id' => $reader->id]))->get(route('pv.index'))->assertOk();
        $this->post(route('pv.store'), $this->pvPayload($po, [1]))->assertForbidden();
        $this->actingAs(User::factory()->supervisor()->create())->get(route('pv.index'))->assertForbidden();   // tanpa Grup
        $this->assertNotNull(Item::find($this->paper->item_id));
    }
}
