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
 * Tujuan       : Menjaga master Kebutuhan ("Digunakan Untuk Kebutuhan" di Uang Keluar): 8 contoh awal, tambah JSON 201 (plus di dropdown), nama kembar ditolak, hapus tidak merusak Uang Keluar lama,
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
        $this->assertSame(8, CashPurpose::count());
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
        $out = CashOut::create(['doc_date' => '2026-11-02', 'cash_in_id' => $in->id, 'amount' => 1000, 'purpose' => 'Kebutuhan Lama', 'status' => 'draft', 'created_by' => $this->staff->id]);
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
}
