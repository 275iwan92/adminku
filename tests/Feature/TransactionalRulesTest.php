<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Department;
use App\Models\Batch;
use App\Models\Recipient;
use App\Models\StockDocument;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga aturan data transaksional dan master dari pemilik: cetakan membawa MODE (DRAFT / CONFIRMED / CANCELLED), Print tersedia di semua mode,
 *                authority bisa bertingkat (config sstock.approval), dokumen baru berisi nilai bawaan, dan master (grup) hanya Activate / Deactivate, tidak dihapus.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class TransactionalRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $entry;

    private User $sup;

    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entry = User::factory()->create();
        $this->sup = User::factory()->supervisor()->create();
        $cat = Category::create(['code' => 'ATK', 'name' => 'ATK']);
        $this->itemId = $this->makeBatch(['code' => 'T1', 'name' => 'Item T1', 'category_id' => $cat->id, 'unit' => 'pcs', 'unit_price' => 10, 'reorder_point' => 1, 'reorder_qty' => 5])->id;
    }

    private function receive(): StockDocument
    {
        $payload = ['supplier_id' => Supplier::create(['code' => 'S1', 'name' => 'S'])->id, 'lines' => [['batch_id' => $this->itemId, 'qty' => 10, 'unit_cost' => 10]]];
        $this->actingAs($this->entry)->post(route('docs.store', 'receiving'), $payload)->assertSessionHasNoErrors();

        return StockDocument::latest('id')->firstOrFail();
    }

    private function act(StockDocument $doc, string $action, User $by, array $payload = [])
    {
        return $this->actingAs($by)->put(route('docs.update', [$doc->type->slug(), $doc]), $payload + ['_action' => $action, '_reason' => 'koreksi data']);
    }

    private function adjustment(): StockDocument
    {
        $payload = ['reason' => 'Opname', 'lines' => [['batch_id' => $this->itemId, 'counted_qty' => 4]]];
        $this->actingAs($this->sup)->post(route('docs.store', 'adjustment'), $payload)->assertSessionHasNoErrors();
        $doc = StockDocument::latest('id')->firstOrFail();
        $this->act($doc, 'confirm', $this->sup, $payload);

        return $doc;
    }

    public function test_the_printout_carries_the_mode_in_every_status(): void
    {
        $doc = $this->receive();
        $payload = ['supplier_id' => $doc->supplier_id, 'lines' => [['batch_id' => $this->itemId, 'qty' => 10, 'unit_cost' => 10]]];

        $this->get(route('docs.show', ['receiving', $doc]))->assertOk()->assertSee(route('docs.print', ['receiving', $doc]), false);
        $this->get(route('docs.print', ['receiving', $doc]))->assertOk()->assertSee('DRAFT')->assertSee($doc->doc_no);

        $this->act($doc, 'confirm', $this->entry, $payload);
        $this->get(route('docs.print', ['receiving', $doc]))->assertOk()->assertSee('CONFIRMED')->assertDontSee('DRAFT');

        $this->act($doc, 'cancel', $this->sup);
        $this->get(route('docs.print', ['receiving', $doc]))->assertOk()->assertSee('CANCELLED');
        $this->get(route('docs.show', ['receiving', $doc]))->assertOk()->assertSee(route('docs.print', ['receiving', $doc]), false);   // Print tetap ada setelah Cancelled
    }

    public function test_every_stock_document_type_can_be_printed(): void
    {
        $this->actingAs($this->sup);
        $adj = $this->adjustment();
        $this->get(route('docs.print', ['adjustment', $adj]))->assertOk()->assertSee('WAITING APPROVAL');

        $out = ['recipient_id' => Recipient::create(['name' => 'R', 'department_id' => Department::create(['code' => 'D', 'name' => 'D'])->id])->id, 'lines' => [['batch_id' => $this->itemId, 'qty' => 1]]];
        $this->post(route('docs.store', 'issuance'), $out)->assertSessionHasNoErrors();
        $this->get(route('docs.print', ['issuance', StockDocument::latest('id')->firstOrFail()]))->assertOk()->assertSee('DRAFT');
        $this->get(route('docs.print', ['receiving', $adj]))->assertNotFound();
    }

    public function test_adjustment_is_approved_by_a_supervisor_only(): void
    {
        $doc = $this->adjustment();
        $this->act($doc, 'approve', $this->sup)->assertRedirect();

        $this->assertSame('approved', $doc->fresh()->status);
        $this->act($doc, 'approve', $this->entry)->assertForbidden();
    }

    public function test_new_documents_open_with_default_values(): void
    {
        $this->actingAs($this->entry)->get(route('docs.create', 'receiving'))->assertOk()->assertSee('qty&quot;:1', false);
        $this->actingAs($this->sup)->get(route('docs.create', 'adjustment'))->assertOk()->assertSee('Opname stok', false);
    }

    public function test_cancelling_a_processed_document_auto_creates_a_reversing_adjustment_record(): void
    {
        $in = $this->receive();
        $payload = ['supplier_id' => $in->supplier_id, 'lines' => [['batch_id' => $this->itemId, 'qty' => 10, 'unit_cost' => 10]]];
        $this->act($in, 'confirm', $this->entry, $payload);
        $this->act($in, 'cancel', $this->sup);

        $adj = StockDocument::where('reversal_of_document_id', $in->id)->firstOrFail();
        $this->assertSame(['approved', 'ADJ'], [$adj->status, $adj->type->value]);
        $this->assertSame([-10.0, 10.0, 0.0], [$adj->lines()->first()->qty, $adj->lines()->first()->system_qty, $adj->lines()->first()->counted_qty]);
        $this->assertSame(0.0, $this->item()->stock_qty);                                   // stok dibalik sekali saja
        $this->get(route('docs.show', ['receiving', $in]))->assertOk()->assertSee($adj->doc_no);
        $this->get(route('docs.print', ['adjustment', $adj]))->assertOk()->assertSee('APPROVED');

        $this->act($adj, 'cancel', $this->sup)->assertStatus(422);                          // pembalik tidak dibatalkan lagi
        $this->assertSame('approved', $adj->fresh()->status);
    }

    public function test_detail_rows_use_the_two_state_buttons_and_stay_readonly_once_confirmed(): void
    {
        $doc = $this->receive();
        $this->get(route('docs.show', ['receiving', $doc]))->assertOk()->assertSee('rowState&quot;:true', false)->assertSee('readonly&quot;:false', false);

        $this->act($doc, 'confirm', $this->entry, ['supplier_id' => $doc->supplier_id, 'lines' => [['batch_id' => $this->itemId, 'qty' => 10, 'unit_cost' => 10]]]);
        $this->get(route('docs.show', ['receiving', $doc]))->assertOk()->assertSee('readonly&quot;:true', false);   // Confirmed: tabel terkunci, tanpa tombol baris
    }

    public function test_a_group_is_deactivated_not_deleted_and_leaves_the_user_choices(): void
    {
        $admin = User::factory()->administrator()->create();
        $group = UserGroup::create(['code' => 'QA', 'name' => 'QA Team']);
        $this->actingAs($admin);

        $this->get(route('users.create'))->assertSee('QA Team');
        $this->postJson(route('groups.toggle', $group))->assertOk()->assertJson(['active' => false]);
        $this->assertFalse($group->fresh()->is_active);
        $this->get(route('users.create'))->assertDontSee('QA Team');
        $this->get(route('groups.show', $group))->assertOk()->assertSee('Activate');

        $this->put(route('groups.update', $group), ['_action' => 'activate'])->assertRedirect();
        $this->assertTrue($group->fresh()->is_active);
    }

    private function item()
    {
        return Batch::findOrFail($this->itemId);
    }
}
