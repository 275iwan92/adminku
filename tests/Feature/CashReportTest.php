<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Models\User;
use App\Models\UserGroup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga 3 laporan Kas: Arus Kas (saldo awal per kas, baris Disetujui kronologis, saldo berjalan dan saldo akhir benar), Penerimaan dan Pengeluaran (dikelompokkan per Sumber Dana,
 *                terpakai / sisa benar, status bawaan Disetujui), dan hak akses modul laporan.
 *                Angka dan baris tabel dirender lewat JS dari JSON di atribut data-config (bukan teks berformat di HTML server); tes mencari pasangan kunci:nilai JSON mentah itu, dengan tanda kutip
 *                yang sudah di-escape Blade ({{ }}) jadi &quot; di HTML. Teks sumber dana juga tampil di dropdown filter terlepas dari periode/status, jadi tes memeriksa pasangan JSON per baris, bukan teks polos.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class CashReportTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private CashAccount $bca;

    protected function setUp(): void
    {
        parent::setUp();
        $group = UserGroup::create(['code' => 'KASG', 'name' => 'Kas']);
        $group->syncAccess(['KSM' => 'rw', 'KSK' => 'rw', 'R-KFLOW' => 'rw', 'R-KIN' => 'rw', 'R-KOUT' => 'rw']);
        $this->staff = User::factory()->create(['group_id' => $group->id]);
        $this->bca = CashAccount::create(['name' => 'Bank BCA', 'type' => 'bank', 'opening_balance' => 1_000_000]);
    }

    // Uang Masuk / Uang Keluar langsung lewat Eloquent (status final): siklus Confirm/Approve sudah diuji CashInTest / CashOutTest, laporan hanya perlu data berstatus akhir.
    private function cashIn(array $over = []): CashIn
    {
        return CashIn::create($over + ['doc_no' => 'UM-TEST-'.CashIn::count(), 'doc_date' => '2026-11-02', 'cash_account_id' => $this->bca->id, 'source_name' => 'Institusi A', 'amount' => 5_000_000, 'status' => 'approved', 'created_by' => $this->staff->id]);
    }

    private function cashOut(CashIn $cashIn, array $over = []): CashOut
    {
        return CashOut::create($over + ['doc_no' => 'UK-TEST-'.CashOut::count(), 'doc_date' => '2026-11-03', 'cash_in_id' => $cashIn->id, 'cash_account_id' => $cashIn->cash_account_id, 'amount' => 1_000_000, 'purpose' => 'Sewa', 'status' => 'approved', 'created_by' => $this->staff->id]);
    }

    // Pasangan kunci:nilai JSON mentah di dalam atribut data-config, setelah di-escape Blade ("->&quot;).
    private function jsonPair(string $key, mixed $value): string
    {
        $encoded = is_string($value) ? json_encode($value) : (string) $value;

        return htmlspecialchars('"'.$key.'":'.$encoded, ENT_QUOTES);
    }

    public function test_cash_flow_shows_opening_running_and_closing_balance(): void
    {
        $cashIn = $this->cashIn(['amount' => 5_000_000]);
        $this->cashOut($cashIn, ['amount' => 2_000_000, 'purpose' => 'Sewa tempat']);

        $response = $this->actingAs($this->staff)->get(route('reports.cash-flow', ['from' => '2026-11-01', 'to' => '2026-11-30']));

        $response->assertOk()
            ->assertSee($this->jsonPair('saldo', 1000000), false)   // saldo awal (opening_balance)
            ->assertSee($this->jsonPair('saldo', 6000000), false)   // saldo awal + masuk (1jt + 5jt)
            ->assertSee($this->jsonPair('saldo', 4000000), false)   // dikurangi keluar 2jt -> saldo akhir
            ->assertSee($this->jsonPair('uraian', 'Institusi A'), false)
            ->assertSee($this->jsonPair('uraian', 'Sewa tempat'), false);
    }

    public function test_receipts_report_groups_by_source_with_used_and_remaining(): void
    {
        $cashIn = $this->cashIn(['source_name' => 'Donatur Budi', 'amount' => 3_000_000]);
        $this->cashOut($cashIn, ['amount' => 1_200_000]);

        $response = $this->actingAs($this->staff)->get(route('reports.cash-receipts', ['from' => '2026-11-01', 'to' => '2026-11-30']));

        $response->assertOk()
            ->assertSee($this->jsonPair('source_name', 'Donatur Budi'), false)
            ->assertSee($this->jsonPair('amount', 3000000), false)
            ->assertSee($this->jsonPair('used', 1200000), false)
            ->assertSee($this->jsonPair('remaining', 1800000), false);
    }

    public function test_receipts_report_defaults_to_approved_status_only(): void
    {
        $this->cashIn(['source_name' => 'Terlihat', 'status' => 'approved']);
        $this->cashIn(['source_name' => 'Draf Tersembunyi', 'status' => 'draft', 'doc_no' => null]);

        // "Draf Tersembunyi" juga tampil di dropdown sumber dana terlepas dari status, jadi periksa baris JSON (source_name : status), bukan teks polos.
        $response = $this->actingAs($this->staff)->get(route('reports.cash-receipts', ['from' => '2026-11-01', 'to' => '2026-11-30']));
        $response->assertOk()
            ->assertSee($this->jsonPair('source_name', 'Terlihat'), false)
            ->assertDontSee($this->jsonPair('status', 'Draf'), false);

        $all = $this->get(route('reports.cash-receipts', ['from' => '2026-11-01', 'to' => '2026-11-30', 'status' => '']));
        $all->assertOk()
            ->assertSee($this->jsonPair('source_name', 'Terlihat'), false)
            ->assertSee($this->jsonPair('source_name', 'Draf Tersembunyi'), false)
            ->assertSee($this->jsonPair('status', 'Draf'), false);
    }

    public function test_disbursements_report_groups_by_source_and_filters_by_cash_account(): void
    {
        $mandiri = CashAccount::create(['name' => 'Bank Mandiri', 'type' => 'bank', 'opening_balance' => 0]);
        $umBca = $this->cashIn(['source_name' => 'Institusi A']);
        $this->cashOut($umBca, ['purpose' => 'Sewa tempat kegiatan', 'amount' => 1_500_000]);
        $umMandiri = $this->cashIn(['cash_account_id' => $mandiri->id, 'source_name' => 'Institusi B']);
        $this->cashOut($umMandiri, ['purpose' => 'ATK', 'amount' => 700_000]);

        $this->actingAs($this->staff)->get(route('reports.cash-disbursements', ['from' => '2026-11-01', 'to' => '2026-11-30']))
            ->assertOk()
            ->assertSee($this->jsonPair('source_name', 'Institusi A'), false)->assertSee($this->jsonPair('purpose', 'Sewa tempat kegiatan'), false)
            ->assertSee($this->jsonPair('source_name', 'Institusi B'), false)->assertSee($this->jsonPair('purpose', 'ATK'), false);

        $this->get(route('reports.cash-disbursements', ['from' => '2026-11-01', 'to' => '2026-11-30', 'cash_account_id' => $this->bca->id]))
            ->assertOk()
            ->assertSee($this->jsonPair('purpose', 'Sewa tempat kegiatan'), false)
            ->assertDontSee($this->jsonPair('purpose', 'ATK'), false);
    }

    public function test_reports_are_guarded_by_module_group_rights(): void
    {
        $this->actingAs(User::factory()->supervisor()->create())->get(route('reports.cash-flow'))->assertForbidden();
        $this->actingAs(User::factory()->supervisor()->create())->get(route('reports.cash-receipts'))->assertForbidden();
        $this->actingAs(User::factory()->supervisor()->create())->get(route('reports.cash-disbursements'))->assertForbidden();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // Uang Keluar tanpa tautan Uang Masuk memakai Kas Asal sendiri: Arus Kas mengurangi kas itu dan Laporan Pengeluaran memisahkan Tunai dan tiap rekening bank.
    public function test_unlinked_cash_out_flows_through_its_own_account_in_both_reports(): void
    {
        $tunai = CashAccount::create(['name' => 'Kas Tunai', 'type' => 'cash', 'opening_balance' => 2_000_000]);
        $this->cashOut($this->cashIn(), ['amount' => 1_000_000, 'purpose' => 'Sewa']);   // lewat BCA (tertaut)
        CashOut::create(['doc_no' => 'UK-FREE', 'doc_date' => '2026-11-03', 'cash_in_id' => null, 'cash_account_id' => $tunai->id, 'amount' => 250_000, 'purpose' => 'Konsumsi', 'status' => 'approved', 'created_by' => $this->staff->id]);

        $flow = $this->actingAs($this->staff)->get(route('reports.cash-flow', ['from' => '2026-11-01', 'to' => '2026-11-30']))->assertOk();
        $flow->assertSee($this->jsonPair('saldo', 1750000), false);   // Kas Tunai: 2.000.000 - 250.000 tanpa Uang Masuk apa pun

        $out = $this->get(route('reports.cash-disbursements', ['from' => '2026-11-01', 'to' => '2026-11-30']))->assertOk();
        foreach ([['channel', 'Kas Tunai'], ['channel', 'Rekening bank: Bank BCA'], ['source_name', 'Tanpa Uang Masuk (saldo kas)'], ['cash_in', 'Tanpa tautan']] as [$k, $v]) {
            $out->assertSee($this->jsonPair($k, $v), false);
        }
        $out->assertSee(htmlspecialchars('"value":250000', ENT_QUOTES), false)->assertSee(htmlspecialchars('"value":1000000', ENT_QUOTES), false);   // Keluar Tunai, Keluar Bank
    }
}
