<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\User;
use App\Models\UserGroup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga Uang Keluar (modul KSK): wajib ditaut ke satu Uang Masuk Disetujui, kas asal ikut otomatis kas tujuan Uang Masuk itu, jumlah tidak boleh melebihi sisa saldo
 *                (dicek saat simpan DAN diulang saat Approve supaya dua Uang Keluar tidak memakai sisa yang sama), siklus sama Uang Masuk, nomor UK-YYMM-0001, dan hak akses modul.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class CashOutTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $boss;

    private CashAccount $account;

    private CashIn $cashIn;   // Uang Masuk Disetujui, Rp 5.000.000, siap ditaut

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 08:00:00');
        $group = UserGroup::create(['code' => 'KASG', 'name' => 'Kas']);
        $group->syncAccess(['KSM' => 'rw', 'KSK' => 'rw']);
        $this->staff = User::factory()->create(['group_id' => $group->id]);
        $this->boss = User::factory()->supervisor()->create(['group_id' => $group->id]);
        $this->account = CashAccount::create(['name' => 'Bank BCA', 'type' => 'bank', 'opening_balance' => 0]);

        $this->actingAs($this->staff)->post(route('cash-in.store'), ['doc_date' => '2026-11-02', 'cash_account_id' => $this->account->id, 'source_name' => 'Institusi A', 'amount' => 5_000_000]);
        $this->cashIn = CashIn::latest('id')->firstOrFail();
        $this->put(route('cash-in.update', $this->cashIn), ['_action' => 'confirm', 'doc_date' => '2026-11-02', 'cash_account_id' => $this->account->id, 'source_name' => 'Institusi A', 'amount' => 5_000_000]);
        $this->actingAs($this->boss)->put(route('cash-in.update', $this->cashIn), ['_action' => 'approve']);
        $this->cashIn = $this->cashIn->fresh();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function payload(array $over = []): array
    {
        return $over + ['doc_date' => '2026-11-03', 'cash_in_id' => $this->cashIn->id, 'amount' => 2_000_000, 'purpose' => 'Sewa tempat kegiatan'];
    }

    private function create(array $over = [], ?User $by = null): CashOut
    {
        $this->actingAs($by ?? $this->staff)->post(route('cash-out.store'), $this->payload($over))->assertSessionHasNoErrors();

        return CashOut::latest('id')->firstOrFail();
    }

    private function act(CashOut $cashOut, string $action, User $by, array $extra = [])
    {
        return $this->actingAs($by)->put(route('cash-out.update', $cashOut), ['_action' => $action] + $extra);
    }

    public function test_sequential_numbers_per_month_and_cash_account_follows_the_linked_cash_in(): void
    {
        $cashOut = $this->create();
        $this->assertNull($cashOut->doc_no);
        $this->act($cashOut, 'confirm', $this->staff, $this->payload())->assertSessionHasNoErrors();
        $this->assertSame('UK-2611-0001', $cashOut->fresh()->doc_no);
        $this->assertSame($this->account->id, $cashOut->fresh()->cashAccount()->id);
    }

    public function test_amount_cannot_exceed_the_remaining_balance_of_the_linked_cash_in(): void
    {
        $this->actingAs($this->staff)->post(route('cash-out.store'), $this->payload(['amount' => 5_000_001]))->assertSessionHasErrors('amount');
        $this->post(route('cash-out.store'), $this->payload(['amount' => 5_000_000]))->assertSessionHasNoErrors();   // pas sisa: boleh
        $this->assertSame(1, CashOut::count());
    }

    public function test_cash_in_must_already_be_approved(): void
    {
        $this->actingAs($this->staff)->post(route('cash-in.store'), ['doc_date' => '2026-11-02', 'cash_account_id' => $this->account->id, 'source_name' => 'Institusi B', 'amount' => 1_000_000]);
        $draftCashIn = CashIn::latest('id')->firstOrFail();

        $this->post(route('cash-out.store'), $this->payload(['cash_in_id' => $draftCashIn->id, 'amount' => 100_000]))->assertSessionHasErrors('cash_in_id');
    }

    public function test_approve_rechecks_the_remaining_balance_so_two_payouts_cannot_both_drain_the_same_cash_in(): void
    {
        $a = $this->create(['amount' => 3_000_000]);   // keduanya diajukan dulu (belum terpakai sampai disetujui)
        $b = $this->create(['amount' => 3_000_000]);
        $this->act($a, 'confirm', $this->staff, $this->payload(['amount' => 3_000_000]));
        $this->act($b, 'confirm', $this->staff, $this->payload(['amount' => 3_000_000]));

        $this->act($a, 'approve', $this->boss)->assertRedirect();   // sisa 5jt, pakai 3jt -> sisa jadi 2jt
        $this->act($b, 'approve', $this->boss)->assertStatus(422);  // sisa cuma 2jt, b minta 3jt -> ditolak walau lolos validasi saat simpan
        $this->assertSame(['approved', 'approval'], [$a->fresh()->status, $b->fresh()->status]);
    }

    public function test_submit_then_the_creator_cannot_approve_but_a_supervisor_can(): void
    {
        $cashOut = $this->create(by: $this->boss);
        $this->act($cashOut, 'confirm', $this->boss, $this->payload())->assertSessionHasNoErrors();

        $this->act($cashOut, 'approve', $this->boss)->assertForbidden();   // pembuat sendiri
        $this->act($cashOut, 'approve', $this->staff)->assertForbidden();  // bukan Supervisor
        $other = User::factory()->supervisor()->create(['group_id' => $this->staff->group_id]);
        $this->act($cashOut, 'approve', $other)->assertRedirect();
        $this->assertSame(['approved', $other->id], [$cashOut->fresh()->status, $cashOut->fresh()->approved_by]);
    }

    public function test_reject_needs_a_reason_and_duplicate_makes_a_new_draft(): void
    {
        $cashOut = $this->create();
        $this->act($cashOut, 'confirm', $this->staff, $this->payload());

        $this->act($cashOut, 'reject', $this->boss)->assertStatus(422);
        $this->act($cashOut, 'reject', $this->boss, ['_reason' => 'Bukti kurang lengkap'])->assertRedirect();
        $this->assertSame(['rejected', 'Bukti kurang lengkap'], [$cashOut->fresh()->status, $cashOut->fresh()->reason]);

        $this->act($cashOut, 'duplicate', $this->staff)->assertRedirect();
        $copy = CashOut::latest('id')->first();
        $this->assertSame(['draft', null], [$copy->status, $copy->doc_no]);
    }

    public function test_locked_after_submit_cancel_needs_a_reason_and_only_drafts_are_deleted(): void
    {
        $cashOut = $this->create();
        $this->act($cashOut, 'confirm', $this->staff, $this->payload());

        $this->act($cashOut, 'save', $this->staff, $this->payload(['purpose' => 'ubah']))->assertStatus(422);
        $this->act($cashOut, 'delete', $this->staff)->assertStatus(422);
        $this->act($cashOut, 'cancel', $this->staff)->assertStatus(422);
        $this->act($cashOut, 'cancel', $this->staff, ['_reason' => 'Batal dipakai'])->assertRedirect();
        $this->assertSame('cancelled', $cashOut->fresh()->status);

        $draft = $this->create();
        $this->act($draft, 'delete', $this->staff)->assertRedirect(route('cash-out.index'));
        $this->assertNull(CashOut::find($draft->id));
    }

    // Tanpa tautan Uang Masuk: Kas Asal dipilih sendiri (saklar Tunai memakai kas tunai aktif) dan batasnya saldo kas = saldo awal + masuk disetujui - keluar disetujui.
    public function test_without_a_link_the_cash_balance_is_the_limit_starting_from_the_opening_balance(): void
    {
        $tunai = CashAccount::create(['name' => 'Kas Tunai', 'type' => 'cash', 'opening_balance' => 1_000_000]);
        $free = ['cash_in_id' => null, 'cash_kind' => 'cash'];

        $this->actingAs($this->staff)->post(route('cash-out.store'), $this->payload($free + ['amount' => 1_500_000]))->assertSessionHasErrors('amount');   // saldo kurang
        $this->post(route('cash-out.store'), $this->payload($free + ['amount' => 600_000]))->assertSessionHasNoErrors();
        $out = CashOut::latest('id')->firstOrFail();
        $this->assertSame([$tunai->id, null], [$out->cash_account_id, $out->cash_in_id]);

        $this->act($out, 'confirm', $this->staff, $this->payload($free + ['amount' => 600_000]))->assertSessionHasNoErrors();
        $this->act($out, 'approve', $this->boss)->assertRedirect();
        $this->assertSame(400_000.0, $tunai->fresh()->currentBalance());
        $this->post(route('cash-out.store'), $this->payload($free + ['amount' => 500_000]))->assertSessionHasErrors('amount');   // sisa saldo kas 400.000
    }

    // Tautan Uang Masuk mengunci Kas Asal ke kas Uang Masuk itu, apa pun pilihan saklar / rekening yang dikirim.
    public function test_a_cash_in_link_locks_the_cash_account(): void
    {
        $other = CashAccount::create(['name' => 'Kas Tunai', 'type' => 'cash', 'opening_balance' => 9_000_000]);
        $this->actingAs($this->staff)->post(route('cash-out.store'), $this->payload(['cash_kind' => 'cash', 'cash_account_id' => $other->id]))->assertSessionHasNoErrors();

        $this->assertSame($this->account->id, CashOut::latest('id')->firstOrFail()->cash_account_id);   // kas Uang Masuk tertaut, bukan kas yang dikirim
    }

    // Dua Uang Keluar tanpa tautan yang masing-masing muat di saldo kas (700.000 dari 1.000.000) tidak boleh disetujui keduanya: Approve kedua dicek ulang terhadap saldo kas.
    public function test_two_approvals_cannot_both_spend_the_same_cash_balance(): void
    {
        $tunai = CashAccount::create(['name' => 'Kas Tunai', 'type' => 'cash', 'opening_balance' => 1_000_000]);
        $free = ['cash_in_id' => null, 'cash_kind' => 'cash', 'amount' => 700_000];
        $this->actingAs($this->staff);
        $first = $this->create($free);
        $second = $this->create($free);   // keduanya lolos saat simpan: Approve belum terjadi
        $this->act($first, 'confirm', $this->staff, $this->payload($free))->assertSessionHasNoErrors();
        $this->act($second, 'confirm', $this->staff, $this->payload($free))->assertSessionHasNoErrors();

        $this->act($first, 'approve', $this->boss)->assertRedirect();
        $this->act($second, 'approve', $this->boss)->assertStatus(422);
        $this->assertSame(['approved', 'approval'], [$first->fresh()->status, $second->fresh()->status]);
        $this->assertSame(300_000.0, $tunai->fresh()->currentBalance());
    }

    // Approve menulis baris keluar ke buku besar kas; pembatalan menulis baris pembalik; daftar tetap terbuka untuk Uang Keluar tanpa tautan.
    public function test_approve_posts_to_the_cash_ledger_and_cancel_reverses_it(): void
    {
        $tunai = CashAccount::create(['name' => 'Kas Tunai', 'type' => 'cash', 'opening_balance' => 1_000_000]);
        $free = ['cash_in_id' => null, 'cash_kind' => 'cash', 'amount' => 300_000];
        $out = $this->actingAs($this->staff)->create($free);
        $this->act($out, 'confirm', $this->staff, $this->payload($free))->assertSessionHasNoErrors();
        $this->assertSame(0, \App\Models\CashMovement::where('doc_type', 'cash_out')->count());   // belum disetujui: belum ada baris
        $this->act($out, 'approve', $this->boss)->assertRedirect();

        $row = \App\Models\CashMovement::where('doc_type', 'cash_out')->where('doc_id', $out->id)->firstOrFail();
        $this->assertSame([$tunai->id, -300_000.0], [$row->cash_account_id, (float) $row->amount]);
        $this->actingAs($this->boss)->get(route('cash-out.index'))->assertOk();

        $this->act($out, 'cancel', $this->boss, ['_reason' => 'Salah catat'])->assertSessionHasNoErrors();
        $this->assertSame(1_000_000.0, $tunai->fresh()->currentBalance());
        $this->assertSame(1, \App\Models\CashMovement::where('reversal_of', $row->id)->count());
    }

    public function test_a_saved_attachment_is_linked_on_the_form_even_when_the_receipt_is_very_tall(): void
    {
        Storage::fake('kas');
        $file = UploadedFile::fake()->image('nota.png', 600, 3000);   // nota supermarket panjang
        $cashOut = $this->create();
        $this->actingAs($this->staff)->put(route('cash-out.update', $cashOut), $this->payload() + ['_action' => 'save', 'attachment' => $file])->assertSessionHasNoErrors();

        $this->get(route('cash-out.show', $cashOut))->assertOk()
            ->assertSee(route('cash-out.file', $cashOut), false)->assertSee('Lampiran bukti (PNG)', false);
        $this->get(route('cash-out.file', $cashOut))->assertOk();
    }

    public function test_screens_open_and_the_module_is_guarded_by_group_rights(): void
    {
        $cashOut = $this->create();
        $this->actingAs($this->staff)->get(route('cash-out.index'))->assertOk()->assertSee('(draf)', false)->assertSee('Sewa tempat kegiatan', false);
        $this->act($cashOut, 'confirm', $this->staff, $this->payload());
        $this->get(route('cash-out.index'))->assertOk()->assertSee('UK-2611-0001', false);
        $this->get(route('cash-out.create'))->assertOk()->assertSee('Ditaut Ke Uang Masuk', false);
        $this->get(route('cash-out.show', $cashOut))->assertOk()->assertSee('Sewa tempat kegiatan', false);

        $this->actingAs(User::factory()->supervisor()->create())->get(route('cash-out.index'))->assertForbidden();

        $reader = UserGroup::create(['code' => 'KAS-RO', 'name' => 'Baca']);
        $reader->syncAccess(['KSK' => 'ro']);
        $this->actingAs(User::factory()->create(['group_id' => $reader->id]))->get(route('cash-out.index'))->assertOk();
        $this->post(route('cash-out.store'), $this->payload())->assertForbidden();
    }
}
