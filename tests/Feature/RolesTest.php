<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Department;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\StockDocument;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\StockCounts;
use App\Support\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga tiga peran atas: Admin Data (adm) membuka semua layar tetapi tidak menyetujui dan tidak mengelola pengguna; Supervisor (spv) membuka semua dan menyetujui; Administrator (admin) semuanya.
 *                Persetujuan dijaga di server (403) DAN tombolnya tidak digambar: PP, PO, Penyesuaian Stok, Stok Opname (setujui / kembalikan), pembatalan dokumen.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class RolesTest extends TestCase
{
    use RefreshDatabase;

    private User $adm;

    private User $spv;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $groups = UserGroup::query()->pluck('id', 'code');
        $this->adm = User::factory()->adminData()->create(['group_id' => $groups['ADMINDATA']]);
        $this->spv = User::factory()->supervisor()->create(['group_id' => $groups['SUPERVISOR']]);
        $this->admin = User::factory()->administrator()->create(['group_id' => $groups['ADMIN']]);
        $this->staff = User::factory()->create(['group_id' => $groups['SUPERVISOR']]);
    }

    private function receive(): \App\Models\Batch
    {
        $cat = Category::create(['code' => 'ATK', 'name' => 'ATK']);
        $batch = $this->makeBatch(['code' => 'A1', 'name' => 'Pensil', 'category_id' => $cat->id, 'unit' => 'pcs', 'unit_price' => 100]);
        $in = ['supplier_id' => Supplier::create(['code' => 'S1', 'name' => 'S'])->id, 'lines' => [['batch_id' => $batch->id, 'qty' => 10, 'unit_cost' => 5]]];
        $this->actingAs($this->admin)->post(route('docs.store', 'receiving'), $in);
        $this->put(route('docs.update', ['receiving', StockDocument::latest('id')->firstOrFail()]), $in + ['_action' => 'confirm'])->assertSessionHasNoErrors();

        return $batch;
    }

    public function test_only_the_approving_roles_can_approve(): void
    {
        $this->assertSame([false, true, true, false], [$this->adm->canApprove(), $this->spv->canApprove(), $this->admin->canApprove(), $this->staff->canApprove()]);
        $this->assertTrue($this->adm->isSupervisor());   // membuka layar setingkat Supervisor
        $this->assertSame('Admin Data', $this->adm->roleLabel());
    }

    public function test_all_three_open_every_module_but_only_the_administrator_manages_users(): void
    {
        $open = ['units.index', 'employees.index', 'candidates.index', 'disciplines.index', 'ga-settings.edit', 'fleet.index', 'ac-care.index', 'pr.index', 'po.index', 'pv.index', 'po-decision.index', 'stock-counts.index', 'reports.adjustment', 'stock.home'];
        foreach ([$this->adm, $this->spv, $this->admin] as $u) {
            foreach ($open as $route) {
                $this->actingAs($u)->get(route($route))->assertOk();
            }
        }
        foreach (['users.index', 'users.create', 'groups.index', 'groups.create'] as $route) {
            $this->actingAs($this->adm)->get(route($route))->assertForbidden();
            $this->actingAs($this->spv)->get(route($route))->assertForbidden();
            $this->actingAs($this->admin)->get(route($route))->assertOk();
        }
        $payload = ['name' => 'Baru', 'username' => 'baru', 'email' => 'b@x.test', 'role' => 'admin_data', 'password' => 'rahasia123'];
        $this->actingAs($this->adm)->post(route('users.store'), $payload)->assertForbidden();
        $this->actingAs($this->spv)->post(route('users.store'), $payload)->assertForbidden();
        $this->actingAs($this->admin)->post(route('users.store'), $payload)->assertRedirect();
        $this->assertSame('admin_data', User::where('username', 'baru')->value('role'));
        $this->actingAs($this->admin)->get(route('users.create'))->assertSee('Admin Data');
    }

    public function test_menu_shows_security_only_to_the_administrator(): void
    {
        $codes = fn (User $u) => collect(Menu::layers($u))->pluck('code')->all();
        $this->assertContains('SEC', $codes($this->admin));
        foreach ([$this->adm, $this->spv] as $u) {
            $this->assertNotContains('SEC', $codes($u));
            $this->assertEqualsCanonicalizing(['MST', 'STK', 'GA', 'HR', 'PUR', 'KAS'], $codes($u));   // semua modul selain Keamanan
        }
    }

    public function test_purchase_request_approve_is_forbidden_for_admin_data_and_the_button_is_not_drawn(): void
    {
        $dept = Department::create(['code' => 'GDG', 'name' => 'Gudang']);
        $payload = ['doc_date' => '2026-11-02', 'department_id' => $dept->id, 'need_date' => '2026-11-10', 'lines' => [['item_name' => 'Map', 'qty' => 5, 'unit' => 'pcs']]];
        $this->actingAs($this->staff)->post(route('pr.store'), $payload);
        $pr = PurchaseRequest::latest('id')->firstOrFail();
        $this->put(route('pr.update', $pr), ['_action' => 'confirm'] + $payload)->assertSessionHasNoErrors();
        $this->assertSame('approval', $pr->fresh()->status);

        $this->actingAs($this->adm)->get(route('pr.show', $pr))->assertOk()->assertDontSee('data-f-act="approve"', false);
        $this->actingAs($this->spv)->get(route('pr.show', $pr))->assertOk()->assertSee('data-f-act="approve"', false);
        $this->actingAs($this->adm)->put(route('pr.update', $pr), ['_action' => 'approve'])->assertForbidden();
        $this->actingAs($this->adm)->put(route('pr.update', $pr), ['_action' => 'reject', 'reject_reason' => 'tidak'])->assertForbidden();
        $this->assertSame('approval', $pr->fresh()->status);
        $this->actingAs($this->spv)->put(route('pr.update', $pr), ['_action' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $pr->fresh()->status);
    }

    public function test_purchase_order_approve_is_forbidden_for_admin_data(): void
    {
        $supplier = Supplier::create(['code' => 'S1', 'name' => 'CV Maju', 'payment_term_days' => 30]);
        $payload = ['doc_date' => '2026-11-02', 'supplier_id' => $supplier->id, 'payment_term_days' => 30, 'need_date' => '2026-11-10', 'ship_to' => 'Gudang', 'vat_pct' => 0, 'lines' => [['item_name' => 'Map', 'qty' => 5, 'unit' => 'pcs', 'price' => 1000]]];
        $this->actingAs($this->staff)->post(route('po.store'), $payload);
        $po = PurchaseOrder::latest('id')->firstOrFail();
        $this->put(route('po.update', $po), ['_action' => 'confirm'] + $payload)->assertSessionHasNoErrors();

        $this->actingAs($this->adm)->get(route('po.show', $po))->assertOk()->assertDontSee('data-f-act="approve"', false);
        $this->actingAs($this->spv)->get(route('po.show', $po))->assertOk()->assertSee('data-f-act="approve"', false);
        $this->actingAs($this->adm)->put(route('po.update', $po), ['_action' => 'approve'])->assertForbidden();
        $this->actingAs($this->adm)->put(route('po.update', $po), ['_action' => 'reject', 'reject_reason' => 'tidak'])->assertForbidden();
        $this->actingAs($this->spv)->put(route('po.update', $po), ['_action' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $po->fresh()->status);
    }

    public function test_adjustment_approve_and_instant_cancel_are_for_approvers_only(): void
    {
        $batch = $this->receive();

        $adj = ['reason' => 'Rusak', 'lines' => [['batch_id' => $batch->id, 'counted_qty' => 8]]];
        $this->actingAs($this->adm)->post(route('docs.store', 'adjustment'), $adj)->assertSessionHasNoErrors();
        $doc = StockDocument::latest('id')->firstOrFail();
        $this->put(route('docs.update', ['adjustment', $doc]), $adj + ['_action' => 'confirm'])->assertSessionHasNoErrors();
        $this->assertSame('approval', $doc->fresh()->status);

        $more = ['supplier_id' => Supplier::firstOrFail()->id, 'lines' => [['batch_id' => $batch->id, 'qty' => 5, 'unit_cost' => 5]]];
        $this->actingAs($this->admin)->post(route('docs.store', 'receiving'), $more);
        $this->put(route('docs.update', ['receiving', $rcv = StockDocument::latest('id')->firstOrFail()]), $more + ['_action' => 'confirm'])->assertSessionHasNoErrors();
        // Membatalkan dokumen terproses: Admin Data hanya mengajukan (cancel_pending), Supervisor yang menyetujui.
        $this->actingAs($this->adm)->put(route('docs.update', ['receiving', $rcv]), ['_action' => 'cancel', '_reason' => 'salah input'])->assertSessionHasNoErrors();
        $this->assertSame('cancel_pending', $rcv->fresh()->status);
        $this->actingAs($this->adm)->put(route('docs.update', ['receiving', $rcv]), ['_action' => 'approve'])->assertForbidden();
        $this->actingAs($this->spv)->put(route('docs.update', ['receiving', $rcv]), ['_action' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $rcv->fresh()->status);

        $this->actingAs($this->adm)->get(route('docs.show', ['adjustment', $doc]))->assertOk()->assertDontSee('data-f-act="approve"', false);
        $this->actingAs($this->spv)->get(route('docs.show', ['adjustment', $doc]))->assertOk()->assertSee('data-f-act="approve"', false);
        $this->actingAs($this->adm)->put(route('docs.update', ['adjustment', $doc]), ['_action' => 'approve'])->assertForbidden();
        $this->actingAs($this->spv)->put(route('docs.update', ['adjustment', $doc]), ['_action' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $doc->fresh()->status);
    }

    public function test_stock_count_approve_and_return_are_forbidden_for_admin_data(): void
    {
        $this->receive();
        $counts = app(StockCounts::class);
        $count = $counts->start($this->spv, ['count_date' => today()->toDateString(), 'scope_type' => 'all', 'blind' => true, 'lock_transactions' => false, 'threshold_pct' => 5]);
        $counts->saveLine($count, $count->lines()->firstOrFail()->id, ['counted_qty' => 10]);
        $counts->finish($count, $this->staff);

        $this->actingAs($this->adm)->get(route('stock-counts.show', $count))->assertOk()->assertDontSee(route('stock-counts.approve', $count), false)->assertDontSee('return-modal');
        $this->actingAs($this->spv)->get(route('stock-counts.show', $count))->assertOk()->assertSee(route('stock-counts.approve', $count), false);
        $this->actingAs($this->adm)->post(route('stock-counts.approve', $count))->assertForbidden();
        $this->actingAs($this->adm)->post(route('stock-counts.reject', $count), ['note' => 'ulang'])->assertForbidden();
        $this->assertSame('review', $count->fresh()->status);
        $this->actingAs($this->spv)->post(route('stock-counts.approve', $count))->assertRedirect();
        $this->assertSame('done', $count->fresh()->status);
    }
}
