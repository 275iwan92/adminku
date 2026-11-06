<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\CashPurpose;
use App\Models\User;
use App\Models\UserGroup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga master Kebutuhan ("Digunakan Untuk Kebutuhan" di Uang Keluar): 9 kategori pendek awal, tambah JSON 201 (plus di dropdown), nama kembar ditolak, hapus tidak merusak Uang Keluar lama,
 *                dan hak akses mengikuti modul Uang Keluar (KSK).
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class CashPurposeTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 08:00:00');
        $group = UserGroup::create(['code' => 'KASG', 'name' => 'Kas']);
        $group->syncAccess(['KSM' => 'rw', 'KSK' => 'rw']);
        $this->staff = User::factory()->create(['group_id' => $group->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_example_purposes_exist_and_users_add_and_delete_them(): void
    {
        $this->assertSame(9, CashPurpose::count());   // 9 kategori pendek awal (Transportasi ... Lainnya)
        $this->actingAs($this->staff);

        $json = $this->postJson(route('cash-purposes.store'), ['name' => 'Biaya listrik'])->assertCreated()->json();
        $this->assertSame(['id', 'name'], array_keys($json));
        $this->postJson(route('cash-purposes.store'), ['name' => 'Biaya listrik'])->assertUnprocessable();

        $this->put(route('cash-purposes.update', $json['id']), ['_action' => 'delete'])->assertRedirect(route('cash-purposes.index'));
        $this->assertFalse(CashPurpose::where('name', 'Biaya listrik')->exists());
    }

    public function test_deleting_a_purpose_keeps_old_cash_outs_readable_and_the_form_lists_the_dropdown(): void
    {
        $account = CashAccount::create(['name' => 'Kas Tunai', 'type' => 'cash', 'opening_balance' => 0]);
        $in = CashIn::create(['doc_no' => 'UM-1', 'doc_date' => '2026-11-02', 'cash_account_id' => $account->id, 'source_name' => 'X', 'amount' => 5000, 'status' => 'approved', 'created_by' => $this->staff->id]);
        $purpose = CashPurpose::create(['name' => 'Kebutuhan Lama']);
        $out = CashOut::create(['doc_date' => '2026-11-02', 'cash_in_id' => $in->id, 'cash_account_id' => $in->cash_account_id, 'amount' => 1000, 'purpose' => 'Kebutuhan Lama', 'status' => 'draft', 'created_by' => $this->staff->id]);
        $this->actingAs($this->staff)->put(route('cash-purposes.update', $purpose), ['_action' => 'delete']);

        $this->get(route('cash-out.show', $out))->assertOk()->assertSee('Kebutuhan Lama', false);
        $this->get(route('cash-out.create'))->assertOk()->assertSee('Operasional kantor', false)->assertSee('Digunakan Untuk Kebutuhan', false);
    }

    public function test_purposes_follow_the_cash_out_module_rights(): void
    {
        $none = UserGroup::create(['code' => 'NOKSK', 'name' => 'Tanpa uang keluar']);
        $none->syncAccess(['KSM' => 'rw']);
        $ro = UserGroup::create(['code' => 'KSKRO', 'name' => 'Uang keluar RO']);
        $ro->syncAccess(['KSK' => 'ro']);

        $this->actingAs(User::factory()->create(['group_id' => $none->id]))->get(route('cash-purposes.index'))->assertForbidden();
        $reader = User::factory()->create(['group_id' => $ro->id]);
        $this->actingAs($reader)->get(route('cash-purposes.index'))->assertOk();
        $this->actingAs($reader)->postJson(route('cash-purposes.store'), ['name' => 'X'])->assertForbidden();
    }

    public function test_cash_out_stores_the_category_id_and_lainnya_needs_a_description(): void
    {
        $account = CashAccount::create(['name' => 'Kas Tunai', 'type' => 'cash', 'opening_balance' => 0]);
        $in = CashIn::create(['doc_no' => 'UM-1', 'doc_date' => '2026-11-02', 'cash_account_id' => $account->id, 'source_name' => 'X', 'amount' => 90000, 'status' => 'approved', 'created_by' => $this->staff->id]);
        app(\App\Services\CashLedger::class)->postIn($in, null);
        $base = ['doc_date' => '2026-11-02', 'cash_in_id' => $in->id, 'cash_account_id' => $in->cash_account_id, 'amount' => 1000];
        $this->actingAs($this->staff);

        $this->post(route('cash-out.store'), $base + ['purpose' => 'Transportasi', 'description' => 'Bensin'])->assertSessionHasNoErrors();
        $out = CashOut::latest('id')->first();
        $this->assertSame(CashPurpose::where('name', 'Transportasi')->value('id'), $out->purpose_id);

        $this->post(route('cash-out.store'), $base + ['purpose' => 'Lainnya'])->assertSessionHasErrors('description');   // rincian wajib
        $this->post(route('cash-out.store'), $base + ['purpose' => 'Lainnya', 'description' => 'Dokumentasi'])->assertSessionHasNoErrors();
        $this->post(route('cash-out.store'), $base + ['purpose' => 'Teks bebas lama'])->assertSessionHasNoErrors();   // tanpa kategori = "Belum dikategorikan"
        $this->assertNull(CashOut::latest('id')->first()->purpose_id);
    }

    public function test_a_used_category_is_deactivated_not_deleted_and_leaves_the_dropdown(): void
    {
        $account = CashAccount::create(['name' => 'Kas Tunai', 'type' => 'cash', 'opening_balance' => 0]);
        $in = CashIn::create(['doc_no' => 'UM-1', 'doc_date' => '2026-11-02', 'cash_account_id' => $account->id, 'source_name' => 'X', 'amount' => 90000, 'status' => 'approved', 'created_by' => $this->staff->id]);
        $cat = CashPurpose::where('name', 'Konsumsi')->firstOrFail();
        CashOut::create(['doc_date' => '2026-11-02', 'cash_in_id' => $in->id, 'cash_account_id' => $in->cash_account_id, 'amount' => 1000, 'purpose' => 'Konsumsi', 'purpose_id' => $cat->id, 'status' => 'draft', 'created_by' => $this->staff->id]);
        $this->actingAs($this->staff)->put(route('cash-purposes.update', $cat), ['_action' => 'delete'])->assertRedirect(route('cash-purposes.index'));

        $this->assertFalse($cat->fresh()->is_active);
        $this->get(route('cash-out.create'))->assertOk()->assertDontSee('value="Konsumsi"', false);
        $this->put(route('cash-purposes.update', $cat), ['_action' => 'activate'])->assertRedirect();
        $this->assertTrue($cat->fresh()->is_active);
    }

    public function test_disbursement_report_groups_by_category_and_dashboard_shows_the_chart(): void
    {
        $group = UserGroup::create(['code' => 'RPTG', 'name' => 'Lap']);
        $group->syncAccess(['KSM' => 'rw', 'KSK' => 'rw', 'R-KOUT' => 'rw', 'R-KFLOW' => 'rw']);
        $user = User::factory()->create(['group_id' => $group->id]);
        $account = CashAccount::create(['name' => 'Kas Tunai', 'type' => 'cash', 'opening_balance' => 0]);
        $in = CashIn::create(['doc_no' => 'UM-1', 'doc_date' => '2026-11-01', 'cash_account_id' => $account->id, 'source_name' => 'Donatur', 'amount' => 9_000_000, 'status' => 'approved', 'created_by' => $user->id]);
        $cat = CashPurpose::where('name', 'Transportasi')->firstOrFail();
        CashOut::create(['doc_no' => 'UK-1', 'doc_date' => '2026-11-02', 'cash_in_id' => $in->id, 'cash_account_id' => $in->cash_account_id, 'amount' => 1_200_000, 'purpose' => 'Transportasi', 'purpose_id' => $cat->id, 'description' => 'Bensin', 'status' => 'approved', 'created_by' => $user->id]);
        CashOut::create(['doc_no' => 'UK-2', 'doc_date' => '2026-11-02', 'cash_in_id' => $in->id, 'cash_account_id' => $in->cash_account_id, 'amount' => 300_000, 'purpose' => 'Nama lama', 'status' => 'approved', 'created_by' => $user->id]);

        $res = $this->actingAs($user)->get(route('reports.cash-disbursements', ['from' => '2026-11-01', 'to' => '2026-11-30']))->assertOk();
        foreach ([['category', 'Transportasi'], ['category', 'Belum dikategorikan'], ['purpose', 'Bensin'], ['purpose', 'Nama lama']] as [$k, $v]) {
            $res->assertSee(htmlspecialchars('"'.$k.'":'.json_encode($v), ENT_QUOTES), false);
        }

        $this->actingAs($user)->get(route('opening', 'kas'))->assertOk()->assertSee('Pengeluaran per kebutuhan', false)->assertSee('Transportasi', false)->assertSee('Saldo kas tunai', false);
    }
}
