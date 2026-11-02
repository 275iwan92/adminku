<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\StockDocument;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserGroup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga Permintaan Pembelian (PP, modul PUP): satu PP banyak barang, nomor PP-YYMM-0001 urut per bulan, satuan dan estimasi harga satuan terisi otomatis (satuan barang; harga beli terakhir),
 *                siklus Draf > Diajukan > Disetujui / Ditolak (alasan wajib) > Dibatalkan, pembuat tidak boleh menyetujui sendiri, hapus hanya draf, Duplicate dari ditolak, dan hak akses modul.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class PurchaseRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $boss;

    private Department $dept;

    private $paper;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 08:00:00');
        $group = UserGroup::create(['code' => 'PUG', 'name' => 'Purchase']);
        $group->syncAccess(['PUP' => 'rw']);
        $this->staff = User::factory()->create(['group_id' => $group->id]);
        $this->boss = User::factory()->supervisor()->create(['group_id' => $group->id]);
        $this->dept = Department::create(['code' => 'GDG', 'name' => 'Gudang']);
        $cat = Category::firstOrCreate(['code' => 'ATK'], ['name' => 'ATK']);
        $this->paper = $this->makeBatch(['code' => 'KRT', 'name' => 'Kertas A4', 'category_id' => $cat->id, 'unit' => 'pcs', 'unit_price' => 100, 'reorder_point' => 0, 'reorder_qty' => 50, 'lead_time_days' => 3]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function payload(array $over = []): array
    {
        return $over + ['doc_date' => '2026-11-02', 'department_id' => $this->dept->id, 'need_date' => '2026-11-10', 'note' => 'Stok menipis', 'lines' => [
            ['item_id' => $this->paper->item_id, 'qty' => 20],
            ['item_name' => 'Map plastik folio', 'qty' => 50, 'unit' => 'pcs', 'est_price' => 3500],
        ]];
    }

    private function create(array $over = [], ?User $by = null): PurchaseRequest
    {
        $this->actingAs($by ?? $this->staff)->post(route('pr.store'), $this->payload($over))->assertSessionHasNoErrors();

        return PurchaseRequest::latest('id')->firstOrFail();
    }

    private function act(PurchaseRequest $pr, string $action, User $by, array $extra = [])
    {
        return $this->actingAs($by)->put(route('pr.update', $pr), ['_action' => $action] + $extra);
    }

    public function test_one_pp_holds_many_items_with_sequential_numbers_per_month_and_autofilled_unit(): void
    {
        $a = $this->create();
        $b = $this->create();
        $c = $this->create(['doc_date' => '2026-12-03', 'need_date' => '2026-12-10']);

        $this->assertSame(['PP-2611-0001', 'PP-2611-0002', 'PP-2612-0001'], [$a->doc_no, $b->doc_no, $c->doc_no]);
        $this->assertSame('draft', $a->status);
        $this->assertCount(2, $a->lines);
        $this->assertSame('pcs', $a->lines[0]->unit);   // kosong -> satuan barang
        $this->assertNull($a->lines[0]->est_price);     // belum pernah dibeli
        $this->assertSame('Map plastik folio', $a->lines[1]->item_name);
        $this->assertEquals(175000, $a->total());
    }

    public function test_estimate_price_defaults_to_the_last_purchase_price(): void
    {
        $supplier = Supplier::create(['code' => 'S1', 'name' => 'CV Maju']);
        foreach ([50000, 52000] as $cost) {
            $doc = ['supplier_id' => $supplier->id, 'lines' => [['batch_id' => $this->paper->id, 'qty' => 10, 'unit_cost' => $cost]]];
            $this->actingAs(User::factory()->administrator()->create())->post(route('docs.store', 'receiving'), $doc);
            $this->put(route('docs.update', ['receiving', StockDocument::latest('id')->firstOrFail()]), $doc + ['_action' => 'confirm']);
        }

        $pr = $this->create();

        $this->assertEquals(52000, $pr->lines[0]->est_price);
        $this->assertEquals(52000, PurchaseRequest::lastCost($this->paper->item_id));
    }

    public function test_validation_needs_an_item_a_positive_qty_and_a_department(): void
    {
        $this->actingAs($this->staff)->post(route('pr.store'), $this->payload(['lines' => [['qty' => 5]]]))->assertSessionHasErrors('lines.0.item_name');
        $this->post(route('pr.store'), $this->payload(['lines' => [['item_id' => $this->paper->item_id, 'qty' => 0]]]))->assertSessionHasErrors('lines.0.qty');
        $this->post(route('pr.store'), $this->payload(['department_id' => '']))->assertSessionHasErrors('department_id');
        $this->post(route('pr.store'), $this->payload(['lines' => []]))->assertSessionHasErrors('lines');
        $this->post(route('pr.store'), $this->payload(['need_date' => '2026-10-01']))->assertSessionHasErrors('need_date');
        $this->assertSame(0, PurchaseRequest::count());
    }

    public function test_submit_then_the_creator_cannot_approve_but_a_supervisor_can(): void
    {
        $pr = $this->create(by: $this->boss);
        $this->act($pr, 'confirm', $this->boss, $this->payload())->assertSessionHasNoErrors();
        $this->assertSame('approval', $pr->fresh()->status);

        $this->act($pr, 'approve', $this->boss)->assertForbidden();   // pembuat sendiri
        $this->act($pr, 'approve', $this->staff)->assertForbidden();  // bukan Supervisor
        $other = User::factory()->supervisor()->create(['group_id' => $this->staff->group_id]);
        $this->act($pr, 'approve', $other)->assertRedirect();
        $this->assertSame(['approved', $other->id], [$pr->fresh()->status, $pr->fresh()->approved_by]);
    }

    public function test_reject_needs_a_reason_and_duplicate_makes_a_new_draft(): void
    {
        $pr = $this->create();
        $this->act($pr, 'confirm', $this->staff, $this->payload());

        $this->act($pr, 'reject', $this->boss)->assertStatus(422);
        $this->act($pr, 'reject', $this->boss, ['_reason' => 'Anggaran habis'])->assertRedirect();
        $this->assertSame(['rejected', 'Anggaran habis'], [$pr->fresh()->status, $pr->fresh()->reason]);

        $this->act($pr, 'duplicate', $this->staff)->assertRedirect();
        $copy = PurchaseRequest::latest('id')->first();
        $this->assertSame(['draft', 'PP-2611-0002', 2], [$copy->status, $copy->doc_no, $copy->lines()->count()]);
    }

    public function test_locked_after_submit_cancel_needs_a_reason_and_only_drafts_are_deleted(): void
    {
        $pr = $this->create();
        $this->act($pr, 'confirm', $this->staff, $this->payload());

        $this->act($pr, 'save', $this->staff, $this->payload(['note' => 'ubah']))->assertStatus(422);   // terkunci setelah diajukan
        $this->act($pr, 'delete', $this->staff)->assertStatus(422);
        $this->act($pr, 'cancel', $this->staff)->assertStatus(422);                                    // alasan wajib
        $this->act($pr, 'cancel', $this->staff, ['_reason' => 'Tidak jadi'])->assertRedirect();
        $this->assertSame('cancelled', $pr->fresh()->status);

        $draft = $this->create();
        $this->act($draft, 'delete', $this->staff)->assertRedirect(route('pr.index'));
        $this->assertNull(PurchaseRequest::find($draft->id));
    }

    public function test_a_pp_already_ordered_cannot_be_cancelled(): void
    {
        $pr = $this->create();
        $this->act($pr, 'confirm', $this->staff, $this->payload());
        $this->act($pr, 'approve', $this->boss);
        $pr->lines()->first()->update(['ordered_qty' => 5]);

        $this->act($pr, 'cancel', $this->boss, ['_reason' => 'x'])->assertStatus(422);
        $this->assertSame('approved', $pr->fresh()->status);
    }

    public function test_screens_open_and_the_module_is_guarded_by_group_rights(): void
    {
        $pr = $this->create();
        $this->actingAs($this->staff)->get(route('pr.index'))->assertOk()->assertSee($pr->doc_no, false)->assertSee('Gudang', false);
        $this->get(route('pr.create'))->assertOk()->assertSee('Estimasi harga satuan', false);
        $this->get(route('pr.show', $pr))->assertOk()->assertSee('Map plastik folio', false);

        $this->actingAs(User::factory()->supervisor()->create())->get(route('pr.index'))->assertForbidden();   // tanpa Grup: Purchase tidak ikut bawaan peran

        $reader = UserGroup::create(['code' => 'PUR-RO', 'name' => 'Baca']);
        $reader->syncAccess(['PUP' => 'ro']);
        $this->actingAs(User::factory()->create(['group_id' => $reader->id]))->get(route('pr.index'))->assertOk();
        $this->post(route('pr.store'), $this->payload())->assertForbidden();
    }
}
