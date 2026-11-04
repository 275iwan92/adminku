<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\CashIn;
use App\Models\User;
use App\Models\UserGroup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga Uang Masuk (modul KSM): nomor UM-YYMM-0001 urut per bulan (lahir saat Confirm), siklus Draf > Diajukan > Disetujui / Ditolak (alasan wajib) > Dibatalkan,
 *                pembuat tidak boleh menyetujui sendiri, Uang Masuk yang sudah punya Uang Keluar tertaut tidak bisa dibatalkan, hapus hanya draf, Duplicate dari ditolak/dibatalkan, dan hak akses modul.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class CashInTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $boss;

    private CashAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 08:00:00');
        $group = UserGroup::create(['code' => 'KASG', 'name' => 'Kas']);
        $group->syncAccess(['KSM' => 'rw', 'KSK' => 'rw']);
        $this->staff = User::factory()->create(['group_id' => $group->id]);
        $this->boss = User::factory()->supervisor()->create(['group_id' => $group->id]);
        $this->account = CashAccount::create(['name' => 'Bank BCA', 'type' => 'bank', 'opening_balance' => 1_000_000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function payload(array $over = []): array
    {
        return $over + ['doc_date' => '2026-11-02', 'cash_account_id' => $this->account->id, 'source_name' => 'Institusi A', 'amount' => 5_000_000];
    }

    private function create(array $over = [], ?User $by = null): CashIn
    {
        $this->actingAs($by ?? $this->staff)->post(route('cash-in.store'), $this->payload($over))->assertSessionHasNoErrors();

        return CashIn::latest('id')->firstOrFail();
    }

    private function act(CashIn $cashIn, string $action, User $by, array $extra = [])
    {
        return $this->actingAs($by)->put(route('cash-in.update', $cashIn), ['_action' => $action] + $extra);
    }

    public function test_sequential_numbers_per_month_born_at_confirm(): void
    {
        $a = $this->create();
        $b = $this->create();
        $c = $this->create(['doc_date' => '2026-12-03']);

        $this->assertSame([null, null, null], [$a->doc_no, $b->doc_no, $c->doc_no]);
        $this->act($a, 'confirm', $this->staff, $this->payload())->assertSessionHasNoErrors();
        $this->act($b, 'confirm', $this->staff, $this->payload())->assertSessionHasNoErrors();
        $this->act($c, 'confirm', $this->staff, $this->payload(['doc_date' => '2026-12-03']))->assertSessionHasNoErrors();
        $this->assertSame(['UM-2611-0001', 'UM-2611-0002', 'UM-2612-0001'], [$a->fresh()->doc_no, $b->fresh()->doc_no, $c->fresh()->doc_no]);
        $this->assertSame('approval', $a->fresh()->status);
    }

    public function test_validation_needs_date_account_source_and_a_positive_amount(): void
    {
        $this->actingAs($this->staff)->post(route('cash-in.store'), $this->payload(['source_name' => '']))->assertSessionHasErrors('source_name');
        $this->post(route('cash-in.store'), $this->payload(['amount' => 0]))->assertSessionHasErrors('amount');
        $this->post(route('cash-in.store'), $this->payload(['cash_account_id' => 999]))->assertSessionHasErrors('cash_account_id');
        $this->assertSame(0, CashIn::count());
    }

    public function test_submit_then_the_creator_cannot_approve_but_a_supervisor_can(): void
    {
        $cashIn = $this->create(by: $this->boss);
        $this->act($cashIn, 'confirm', $this->boss, $this->payload())->assertSessionHasNoErrors();

        $this->act($cashIn, 'approve', $this->boss)->assertForbidden();   // pembuat sendiri
        $this->act($cashIn, 'approve', $this->staff)->assertForbidden();  // bukan Supervisor
        $other = User::factory()->supervisor()->create(['group_id' => $this->staff->group_id]);
        $this->act($cashIn, 'approve', $other)->assertRedirect();
        $this->assertSame(['approved', $other->id], [$cashIn->fresh()->status, $cashIn->fresh()->approved_by]);
    }

    public function test_admin_data_role_cannot_approve(): void
    {
        $cashIn = $this->create();
        $this->act($cashIn, 'confirm', $this->staff, $this->payload());
        $adminData = User::factory()->adminData()->create(['group_id' => $this->staff->group_id]);

        $this->act($cashIn, 'approve', $adminData)->assertForbidden();
    }

    public function test_reject_needs_a_reason_and_duplicate_makes_a_new_draft(): void
    {
        $cashIn = $this->create();
        $this->act($cashIn, 'confirm', $this->staff, $this->payload());

        $this->act($cashIn, 'reject', $this->boss)->assertStatus(422);
        $this->act($cashIn, 'reject', $this->boss, ['_reason' => 'Dokumen tidak lengkap'])->assertRedirect();
        $this->assertSame(['rejected', 'Dokumen tidak lengkap'], [$cashIn->fresh()->status, $cashIn->fresh()->reason]);

        $this->act($cashIn, 'duplicate', $this->staff)->assertRedirect();
        $copy = CashIn::latest('id')->first();
        $this->assertSame(['draft', null], [$copy->status, $copy->doc_no]);
    }

    public function test_locked_after_submit_cancel_needs_a_reason_and_only_drafts_are_deleted(): void
    {
        $cashIn = $this->create();
        $this->act($cashIn, 'confirm', $this->staff, $this->payload());

        $this->act($cashIn, 'save', $this->staff, $this->payload(['source_name' => 'ubah']))->assertStatus(422);   // terkunci setelah diajukan
        $this->act($cashIn, 'delete', $this->staff)->assertStatus(422);
        $this->act($cashIn, 'cancel', $this->staff)->assertStatus(422);                                            // alasan wajib
        $this->act($cashIn, 'cancel', $this->staff, ['_reason' => 'Batal diterima'])->assertRedirect();
        $this->assertSame('cancelled', $cashIn->fresh()->status);

        $draft = $this->create();
        $this->act($draft, 'delete', $this->staff)->assertRedirect(route('cash-in.index'));
        $this->assertNull(CashIn::find($draft->id));
    }

    public function test_a_cash_in_already_linked_to_a_cash_out_cannot_be_cancelled(): void
    {
        $cashIn = $this->create();
        $this->act($cashIn, 'confirm', $this->staff, $this->payload());
        $this->act($cashIn, 'approve', $this->boss);

        $payload = ['doc_date' => '2026-11-03', 'cash_in_id' => $cashIn->id, 'amount' => 1_000_000, 'purpose' => 'Sewa tempat'];
        $this->actingAs($this->staff)->post(route('cash-out.store'), $payload);
        $cashOut = \App\Models\CashOut::latest('id')->firstOrFail();
        $this->put(route('cash-out.update', $cashOut), ['_action' => 'confirm'] + $payload);   // diajukan: cukup untuk mengunci Uang Masuk

        $this->act($cashIn, 'cancel', $this->boss, ['_reason' => 'x'])->assertStatus(422);
        $this->assertSame('approved', $cashIn->fresh()->status);
    }

    public function test_screens_open_and_the_module_is_guarded_by_group_rights(): void
    {
        $cashIn = $this->create();
        $this->actingAs($this->staff)->get(route('cash-in.index'))->assertOk()->assertSee('(draf)', false)->assertSee('Institusi A', false);
        $this->act($cashIn, 'confirm', $this->staff, $this->payload());
        $this->get(route('cash-in.index'))->assertOk()->assertSee('UM-2611-0001', false);
        $this->get(route('cash-in.create'))->assertOk()->assertSee('Sumber Dana', false);
        $this->get(route('cash-in.show', $cashIn))->assertOk()->assertSee('Institusi A', false);

        $this->actingAs(User::factory()->supervisor()->create())->get(route('cash-in.index'))->assertForbidden();   // tanpa Grup: Kas tidak ikut bawaan peran

        $reader = UserGroup::create(['code' => 'KAS-RO', 'name' => 'Baca']);
        $reader->syncAccess(['KSM' => 'ro']);
        $this->actingAs(User::factory()->create(['group_id' => $reader->id]))->get(route('cash-in.index'))->assertOk();
        $this->post(route('cash-in.store'), $this->payload())->assertForbidden();
    }
}
