<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\CashAdjustment;
use App\Models\CashMovement;
use App\Models\User;
use App\Models\UserGroup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga Penyesuaian Kas (KSA): alasan wajib, nomor PK-YYMM-0001, siklus Draf -> Confirm -> Approve, Approve SELALU oleh Supervisor (bukan pembuat, bukan Admin Data, bukan staf),
 *                baris buku besar baru ada setelah Approve, pengurangan melebihi saldo ditolak (simpan dan Approve), pembatalan menulis baris pembalik, tampil di Kartu Saldo, hak akses modul.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class CashAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $boss;

    private CashAccount $cash;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 08:00:00');
        $group = UserGroup::create(['code' => 'KASG', 'name' => 'Kas']);
        $group->syncAccess(['KSA' => 'rw', 'KSB' => 'ro']);
        $this->staff = User::factory()->create(['group_id' => $group->id]);
        $this->boss = User::factory()->supervisor()->create(['group_id' => $group->id]);
        $this->cash = CashAccount::create(['name' => 'Kas Tunai', 'type' => 'cash', 'opening_balance' => 1_000_000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function payload(array $over = []): array
    {
        return $over + ['doc_date' => '2026-11-02', 'cash_account_id' => $this->cash->id, 'direction' => 'add', 'amount' => 200_000, 'cause' => 'Selisih hitung fisik'];
    }

    private function submitted(array $over = []): CashAdjustment
    {
        $this->actingAs($this->staff)->post(route('cash-adjust.store'), $this->payload($over))->assertSessionHasNoErrors();
        $doc = CashAdjustment::latest('id')->firstOrFail();
        $this->put(route('cash-adjust.update', $doc), ['_action' => 'confirm'] + $this->payload($over))->assertSessionHasNoErrors();

        return $doc->fresh();
    }

    private function act(CashAdjustment $doc, string $action, User $by, array $extra = [])
    {
        return $this->actingAs($by)->put(route('cash-adjust.update', $doc), ['_action' => $action] + $extra);
    }

    public function test_a_reason_is_required_and_the_number_is_born_at_confirm(): void
    {
        $this->actingAs($this->staff)->post(route('cash-adjust.store'), $this->payload(['cause' => '  ']))->assertSessionHasErrors('cause');

        $doc = $this->submitted();
        $this->assertSame('PK-2611-0001', $doc->doc_no);
        $this->assertSame('approval', $doc->status);
    }

    public function test_nothing_is_written_to_the_ledger_before_approve_and_only_a_supervisor_not_the_creator_approves(): void
    {
        $doc = $this->submitted();
        $this->assertSame(0, CashMovement::where('doc_type', 'cash_adjust')->count());

        $this->act($doc, 'approve', $this->staff)->assertForbidden();   // staf biasa
        $creatorBoss = $this->submitted();
        $this->actingAs($this->boss)->post(route('cash-adjust.store'), $this->payload())->assertSessionHasNoErrors();
        $mine = CashAdjustment::latest('id')->firstOrFail();
        $this->put(route('cash-adjust.update', $mine), ['_action' => 'confirm'] + $this->payload());
        $this->act($mine, 'approve', $this->boss)->assertForbidden();   // pembuat sendiri
        $adminData = User::factory()->create(['group_id' => $this->boss->group_id, 'role' => User::ROLE_ADMIN_DATA]);
        $this->act($doc, 'approve', $adminData)->assertForbidden();   // Admin Data tidak menyetujui
        $this->assertSame(0, CashMovement::where('doc_type', 'cash_adjust')->count());

        $this->act($doc, 'approve', $this->boss)->assertSessionHasNoErrors();
        $this->assertSame('approved', $doc->fresh()->status);
        $this->assertSame(1_200_000.0, $this->cash->currentBalance());
        $this->assertSame(200_000.0, (float) CashMovement::where('doc_type', 'cash_adjust')->where('doc_id', $doc->id)->value('amount'));
        $this->assertNotNull($creatorBoss);
    }

    public function test_a_decrease_over_the_balance_is_refused_at_save_and_at_approve(): void
    {
        $this->actingAs($this->staff)->post(route('cash-adjust.store'), $this->payload(['direction' => 'sub', 'amount' => 1_000_001]))->assertSessionHasErrors('amount');

        // Dua pengurangan yang masing-masing muat, tetapi tidak keduanya: Approve kedua ditolak buku besar dan dokumen tetap Diajukan.
        $first = $this->submitted(['direction' => 'sub', 'amount' => 700_000]);
        $second = $this->submitted(['direction' => 'sub', 'amount' => 700_000]);
        $this->act($first, 'approve', $this->boss)->assertSessionHasNoErrors();
        $this->act($second, 'approve', $this->boss)->assertSessionHasErrors('amount');
        $this->assertSame(['approved', 'approval'], [$first->fresh()->status, $second->fresh()->status]);
        $this->assertSame(300_000.0, $this->cash->currentBalance());
    }

    public function test_cancelling_an_approved_adjustment_reverses_the_row_and_it_shows_on_the_cash_card(): void
    {
        $doc = $this->submitted(['direction' => 'sub', 'amount' => 250_000]);
        $this->act($doc, 'approve', $this->boss);
        $row = CashMovement::where('doc_type', 'cash_adjust')->firstOrFail();
        $this->assertSame(-250_000.0, (float) $row->amount);

        $this->actingAs($this->staff)->get(route('cash-card.index', ['cash_account_id' => $this->cash->id, 'from' => '2026-11-01', 'to' => '2026-11-30']))
            ->assertOk()->assertSee('Selisih hitung fisik')->assertSee('Penyesuaian');

        $this->act($doc, 'cancel', $this->boss)->assertStatus(422);   // alasan wajib
        $this->act($doc, 'cancel', $this->boss, ['_reason' => 'Salah hitung'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $doc->fresh()->status);
        $this->assertSame(1_000_000.0, $this->cash->currentBalance());
        $this->assertSame(1, CashMovement::where('reversal_of', $row->id)->count());
        $this->assertSame(-250_000.0, (float) $row->fresh()->amount);   // baris asal utuh
    }

    public function test_reject_needs_a_reason_and_writes_nothing(): void
    {
        $doc = $this->submitted();
        $this->act($doc, 'reject', $this->boss)->assertStatus(422);
        $this->act($doc, 'reject', $this->boss, ['_reason' => 'Tidak sesuai'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $doc->fresh()->status);
        $this->assertSame(0, CashMovement::where('doc_type', 'cash_adjust')->count());
    }

    public function test_screens_open_and_the_module_is_guarded_by_group_rights(): void
    {
        $this->actingAs($this->staff)->get(route('cash-adjust.index'))->assertOk();
        $this->get(route('cash-adjust.create'))->assertOk();

        $none = User::factory()->create(['group_id' => UserGroup::create(['code' => 'NONE', 'name' => 'Tanpa'])->id]);
        $this->actingAs($none)->get(route('cash-adjust.index'))->assertForbidden();

        $ro = UserGroup::create(['code' => 'RO', 'name' => 'RO']);
        $ro->syncAccess(['KSA' => 'ro']);
        $reader = User::factory()->create(['group_id' => $ro->id]);
        $this->actingAs($reader)->get(route('cash-adjust.index'))->assertOk();
        $this->post(route('cash-adjust.store'), $this->payload())->assertForbidden();
    }
}
