<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\CashIn;
use App\Models\CashSource;
use App\Models\User;
use App\Models\UserGroup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga Sumber Dana (master m_cash_source: 7 contoh awal, tambah JSON 201, nama kembar ditolak, hapus tidak merusak Uang Masuk lama), saklar Kas Tujuan Tunai / Rekening bank di Uang Masuk
 *                (Tunai = kas tunai aktif otomatis, bank = rekening terpilih, tambah-cepat rekening bank oleh pengguna Uang Masuk) dan laporan Penerimaan (Masuk Tunai, Masuk Bank, kelompok per rekening).
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class CashSourceBankTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private CashAccount $tunai;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 08:00:00');
        $group = UserGroup::create(['code' => 'KASG', 'name' => 'Kas']);
        $group->syncAccess(['KSM' => 'rw', 'KSK' => 'rw', 'R-KIN' => 'rw']);
        $this->staff = User::factory()->create(['group_id' => $group->id]);
        $this->tunai = CashAccount::create(['name' => 'Kas Tunai', 'type' => 'cash', 'opening_balance' => 0]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_seven_example_sources_exist_and_users_add_and_delete_them(): void
    {
        $this->assertSame(7, CashSource::count());
        $this->assertTrue(CashSource::where('name', 'Donatur Bapak Hadi')->exists());
        $this->actingAs($this->staff);

        $json = $this->postJson(route('cash-sources.store'), ['name' => 'Donatur Ibu Sari'])->assertCreated()->json();
        $this->assertSame(['id', 'name'], array_keys($json));
        $this->postJson(route('cash-sources.store'), ['name' => 'Donatur Ibu Sari'])->assertUnprocessable();   // nama kembar

        $this->put(route('cash-sources.update', $json['id']), ['_action' => 'delete'])->assertRedirect(route('cash-sources.index'));
        $this->assertFalse(CashSource::where('name', 'Donatur Ibu Sari')->exists());
    }

    public function test_sources_follow_the_cash_in_module_rights(): void
    {
        $none = UserGroup::create(['code' => 'NOKAS', 'name' => 'Tanpa kas']);
        $none->syncAccess(['KSK' => 'rw']);
        $ro = UserGroup::create(['code' => 'KASRO', 'name' => 'Kas RO']);
        $ro->syncAccess(['KSM' => 'ro']);

        $this->actingAs(User::factory()->create(['group_id' => $none->id]))->get(route('cash-sources.index'))->assertForbidden();
        $reader = User::factory()->create(['group_id' => $ro->id]);
        $this->actingAs($reader)->get(route('cash-sources.index'))->assertOk();
        $this->actingAs($reader)->postJson(route('cash-sources.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($reader)->postJson(route('cash-in.bank-account'), ['bank_name' => 'BNI', 'account_no' => '1'])->assertForbidden();
    }

    public function test_cash_in_stores_the_source_id_and_a_used_source_is_deactivated_not_deleted(): void
    {
        $source = CashSource::where('name', 'Donatur Bapak Hadi')->firstOrFail();
        $this->actingAs($this->staff)->post(route('cash-in.store'), ['doc_date' => '2026-11-02', 'cash_kind' => 'cash', 'source_name' => 'Donatur Bapak Hadi', 'amount' => 1000])->assertSessionHasNoErrors();
        $this->assertSame($source->id, CashIn::latest('id')->firstOrFail()->source_id);

        $this->put(route('cash-sources.update', $source), ['_action' => 'delete'])->assertRedirect(route('cash-sources.index'));
        $this->assertFalse($source->fresh()->is_active);   // sudah dipakai: dinonaktifkan, bukan dihapus
        $this->get(route('cash-in.create'))->assertOk()->assertDontSee('value="Donatur Bapak Hadi"', false);
        $this->put(route('cash-sources.update', $source), ['_action' => 'activate'])->assertRedirect();
        $this->assertTrue($source->fresh()->is_active);
    }

    public function test_deleting_a_source_keeps_old_cash_ins_readable(): void
    {
        $source = CashSource::create(['name' => 'Sumber Lama']);
        $cashIn = CashIn::create(['doc_date' => '2026-11-02', 'cash_account_id' => $this->tunai->id, 'source_name' => 'Sumber Lama', 'amount' => 1000, 'status' => 'draft', 'created_by' => $this->staff->id]);
        $this->actingAs($this->staff)->put(route('cash-sources.update', $source), ['_action' => 'delete']);

        $this->get(route('cash-in.show', $cashIn))->assertOk()->assertSee('Sumber Lama', false);
    }

    public function test_tunai_switch_uses_the_active_cash_account_and_bank_uses_the_chosen_account(): void
    {
        $bank = CashAccount::create(['name' => 'BCA · 123', 'type' => 'bank', 'opening_balance' => 0]);
        $this->actingAs($this->staff);
        $base = ['doc_date' => '2026-11-02', 'source_name' => 'Donatur umum', 'amount' => 2_000_000];

        $this->post(route('cash-in.store'), $base + ['cash_kind' => 'cash'])->assertSessionHasNoErrors();
        $this->assertSame($this->tunai->id, CashIn::latest('id')->first()->cash_account_id);

        $this->post(route('cash-in.store'), $base + ['cash_kind' => 'bank', 'cash_account_id' => $bank->id])->assertSessionHasNoErrors();
        $this->assertSame($bank->id, CashIn::latest('id')->first()->cash_account_id);

        $this->post(route('cash-in.store'), $base + ['cash_kind' => 'bank'])->assertSessionHasErrors('cash_account_id');   // bank tanpa rekening dipilih
    }

    public function test_bank_account_quick_add_returns_201_selects_nothing_else_and_rejects_duplicates(): void
    {
        $this->actingAs($this->staff);
        $payload = ['bank_name' => 'BNI', 'account_no' => '555-001', 'account_holder' => 'PT Uji'];

        $json = $this->postJson(route('cash-in.bank-account'), $payload)->assertCreated()->json();
        $account = CashAccount::find($json['id']);
        $this->assertSame(['bank', 'BNI', '555-001', '0.00'], [$account->type, $account->bank_name, $account->account_no, (string) $account->opening_balance]);
        $this->assertSame('BNI · 555-001 · PT Uji', $json['name']);

        $this->postJson(route('cash-in.bank-account'), $payload)->assertUnprocessable();
        $this->postJson(route('cash-in.bank-account'), ['bank_name' => 'BNI'])->assertUnprocessable();   // no. rekening wajib
    }

    private function jsonPair(string $key, mixed $value): string
    {
        $encoded = is_string($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;   // atribut data-dl2-config memakai Unicode apa adanya

        return htmlspecialchars('"'.$key.'":'.$encoded, ENT_QUOTES);
    }

    public function test_receipts_report_splits_cash_and_each_bank_account(): void
    {
        $bca = CashAccount::create(['name' => 'BCA · 111', 'type' => 'bank', 'opening_balance' => 0]);
        $mandiri = CashAccount::create(['name' => 'Mandiri · 222', 'type' => 'bank', 'opening_balance' => 0]);
        foreach ([[$this->tunai, 1_000_000], [$bca, 4_000_000], [$mandiri, 2_500_000]] as [$account, $amount]) {
            CashIn::create(['doc_no' => 'UM-'.$account->id, 'doc_date' => '2026-11-02', 'cash_account_id' => $account->id, 'source_name' => 'Donatur umum', 'amount' => $amount, 'status' => 'approved', 'created_by' => $this->staff->id]);
        }

        $res = $this->actingAs($this->staff)->get(route('reports.cash-receipts', ['from' => '2026-11-01', 'to' => '2026-11-30']))->assertOk();

        // Angka dirender JS dari JSON data-config: periksa pasangan kunci:nilai mentah (lihat CashReportTest).
        foreach (['Masuk Tunai', 'Masuk Bank', 'Tunai'] as $text) {
            $res->assertSee($text, false);
        }
        foreach ([['channel', 'Kas Tunai'], ['channel', 'Rekening bank: BCA · 111'], ['channel', 'Rekening bank: Mandiri · 222'], ['amount', 1000000], ['amount', 4000000], ['amount', 2500000]] as [$k, $v]) {
            $res->assertSee($this->jsonPair($k, $v), false);
        }
        $res->assertSee(htmlspecialchars('"value":1000000', ENT_QUOTES), false);   // Masuk Tunai
        $res->assertSee(htmlspecialchars('"value":6500000', ENT_QUOTES), false);   // Masuk Bank (4.000.000 + 2.500.000)
    }
}
