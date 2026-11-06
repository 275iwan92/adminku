<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\CashIn;
use App\Models\CashMovement;
use App\Models\CashOut;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\CashLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga buku besar kas (CashLedger) dan Kartu Saldo: saldo = jumlah baris, saldo awal = baris pertama, Approve menulis baris, pembatalan menulis baris pembalik (baris asal utuh),
 *                saldo tidak boleh negatif, migrasi data lama (backfill) idempoten, dan hak akses layar Kartu Saldo.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class CashLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $boss;

    private CashAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $group = UserGroup::create(['code' => 'KASG', 'name' => 'Kas']);
        $group->syncAccess(['KSM' => 'rw', 'KSK' => 'rw', 'KSB' => 'ro']);
        $this->staff = User::factory()->create(['group_id' => $group->id]);
        $this->boss = User::factory()->supervisor()->create(['group_id' => $group->id]);
        $this->bank = CashAccount::create(['name' => 'Bank BCA', 'type' => 'bank', 'opening_balance' => 1_000_000]);
    }

    private function ledger(): CashLedger
    {
        return app(CashLedger::class);
    }

    private function approvedIn(float $amount = 500_000): CashIn
    {
        $in = CashIn::create(['doc_no' => 'UM-T-'.(CashIn::count() + 1), 'doc_date' => now()->toDateString(), 'cash_account_id' => $this->bank->id, 'source_name' => 'Donatur', 'amount' => $amount, 'status' => 'approved', 'created_by' => $this->staff->id]);
        $this->ledger()->postIn($in, $this->boss);

        return $in;
    }

    public function test_opening_balance_is_the_first_ledger_row_and_balance_is_the_sum(): void
    {
        $first = CashMovement::query()->where('cash_account_id', $this->bank->id)->orderBy('id')->first();
        $this->assertSame('opening', $first->type);
        $this->assertSame(1_000_000.0, (float) $first->amount);

        $this->approvedIn(500_000);
        $this->assertSame(1_500_000.0, $this->bank->currentBalance());
        $this->assertSame(1_500_000.0, (float) CashMovement::where('cash_account_id', $this->bank->id)->sum('amount'));
    }

    public function test_cash_account_with_zero_opening_still_gets_a_first_row_and_editing_opening_appends_a_row(): void
    {
        $cash = CashAccount::create(['name' => 'Kas Tunai', 'type' => 'cash', 'opening_balance' => 0]);
        $this->assertSame(1, CashMovement::where('cash_account_id', $cash->id)->where('type', 'opening')->count());

        $cash->update(['opening_balance' => 250_000]);
        $this->assertSame(250_000.0, $cash->currentBalance());
        $this->assertSame(2, CashMovement::where('cash_account_id', $cash->id)->where('type', 'opening')->count());   // baris lama tidak diubah
        $this->assertSame(0.0, (float) CashMovement::where('cash_account_id', $cash->id)->orderBy('id')->first()->amount);
    }

    public function test_posting_the_same_document_twice_writes_one_row(): void
    {
        $in = $this->approvedIn();
        $this->ledger()->postIn($in, $this->boss);

        $this->assertSame(1, CashMovement::where('doc_type', 'cash_in')->where('doc_id', $in->id)->count());
    }

    public function test_cancelling_writes_a_reversing_row_and_keeps_the_original(): void
    {
        $in = $this->approvedIn(500_000);
        $original = CashMovement::where('doc_type', 'cash_in')->where('doc_id', $in->id)->firstOrFail();

        $this->actingAs($this->boss)->put(route('cash-in.update', $in), ['_action' => 'cancel', '_reason' => 'Salah input'])->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $in->fresh()->status);
        $this->assertSame(1_000_000.0, $this->bank->currentBalance());
        $reversal = CashMovement::where('reversal_of', $original->id)->firstOrFail();
        $this->assertSame(-500_000.0, (float) $reversal->amount);
        $this->assertSame(500_000.0, (float) $original->fresh()->amount);   // baris asal tidak berubah

        $this->ledger()->reverse('cash_in', $in->id, $this->boss);   // pembatalan kedua tidak membalik ulang
        $this->assertSame(1, CashMovement::where('reversal_of', $original->id)->count());
    }

    public function test_cancelling_a_cash_in_is_refused_when_it_would_make_the_balance_negative(): void
    {
        $in = $this->approvedIn(500_000);
        $out = CashOut::create(['doc_no' => 'UK-T-1', 'doc_date' => now()->toDateString(), 'cash_account_id' => $this->bank->id, 'amount' => 1_200_000, 'purpose' => 'Sewa', 'status' => 'approved', 'created_by' => $this->staff->id]);
        $this->ledger()->postOut($out, $this->boss);   // saldo 300.000

        $this->actingAs($this->boss)->put(route('cash-in.update', $in), ['_action' => 'cancel', '_reason' => 'Salah'])->assertSessionHasErrors();
        $this->assertSame('approved', $in->fresh()->status);
        $this->assertSame(300_000.0, $this->bank->currentBalance());
    }

    public function test_cash_out_over_the_balance_is_refused_by_the_ledger(): void
    {
        $out = CashOut::create(['doc_no' => 'UK-T-2', 'doc_date' => now()->toDateString(), 'cash_account_id' => $this->bank->id, 'amount' => 1_000_001, 'purpose' => 'Sewa', 'status' => 'approved', 'created_by' => $this->staff->id]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->ledger()->postOut($out, $this->boss);
    }

    public function test_backfill_builds_rows_from_old_approved_documents_and_is_safe_to_repeat(): void
    {
        // Keadaan sebelum buku besar: dokumen final dan saldo awal tanpa baris.
        CashMovement::query()->delete();
        $in = CashIn::create(['doc_no' => 'UM-OLD', 'doc_date' => '2026-10-01', 'cash_account_id' => $this->bank->id, 'source_name' => 'Lama', 'amount' => 700_000, 'status' => 'approved', 'created_by' => $this->staff->id]);
        $out = CashOut::create(['doc_no' => 'UK-OLD', 'doc_date' => '2026-10-02', 'cash_account_id' => $this->bank->id, 'amount' => 200_000, 'purpose' => 'Lama', 'status' => 'approved', 'created_by' => $this->staff->id]);
        CashIn::create(['doc_no' => 'UM-DRAF', 'doc_date' => '2026-10-03', 'cash_account_id' => $this->bank->id, 'source_name' => 'Draf', 'amount' => 999, 'status' => 'approval', 'created_by' => $this->staff->id]);

        $made = $this->ledger()->backfill();

        $this->assertSame(3, $made);   // saldo awal + 1 masuk + 1 keluar; dokumen belum disetujui tidak ikut
        $this->assertSame(1_500_000.0, $this->bank->currentBalance());
        $this->assertSame(0, $this->ledger()->backfill());
        $this->assertSame(3, CashMovement::count());
        $this->assertSame('opening', CashMovement::orderBy('id')->first()->type);
        $this->assertSame([$in->id], CashMovement::where('doc_type', 'cash_in')->pluck('doc_id')->all());
        $this->assertSame([$out->id], CashMovement::where('doc_type', 'cash_out')->pluck('doc_id')->all());
    }

    public function test_cash_card_shows_running_balance_and_needs_the_module(): void
    {
        $this->approvedIn(500_000);

        $this->actingAs($this->staff)->get(route('cash-card.index', ['cash_account_id' => $this->bank->id, 'from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()]))
            ->assertOk()->assertSee('Kartu Saldo Kas')->assertSee('Saldo awal')->assertSee('Donatur')
            ->assertSee(htmlspecialchars('"saldo":1500000', ENT_QUOTES), false);

        $other = User::factory()->create(['group_id' => UserGroup::create(['code' => 'X', 'name' => 'X'])->id]);
        $this->actingAs($other)->get(route('cash-card.index'))->assertForbidden();
    }
}
