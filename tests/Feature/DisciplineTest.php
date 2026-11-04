<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Discipline;
use App\Models\Employee;
use App\Models\User;
use App\Models\UserGroup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga Kedisiplinan (HR, HRD): nomor surat otomatis urut per tahun dan jenis, SP berlaku 6 bulan (bawaan) dan PHK wajib tanggal efektif, saran jenis berikutnya dan peringatan loncat,
 *                status Aktif / Diganti / Kedaluwarsa / Dicabut, cabut dengan alasan, surat cetak (NIK disamarkan), tautan ke Karyawan (tab Disiplin, kolom SP aktif, Nonaktifkan dari PHK), dan hak modul.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class DisciplineTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Employee $emp;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 08:00:00');
        $this->admin = User::factory()->administrator()->create();
        $dept = Department::create(['code' => 'GDG', 'name' => 'Gudang']);
        $this->emp = Employee::create(['code' => 'E-0001', 'name' => 'Budi Santoso', 'gender' => 'L', 'department_id' => $dept->id, 'employment' => 'tetap', 'join_date' => '2021-01-01', 'nik_ktp' => '3273010101010001', 'position' => 'Staf gudang']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function issue(string $type, array $over = [])
    {
        return $this->actingAs($this->admin)->post(route('disciplines.store'), $over + ['employee_id' => $this->emp->id, 'type' => $type, 'issued_on' => '2026-11-02', 'reason' => 'Terlambat berulang', 'signer_name' => 'Pak Hadi', 'signer_title' => 'Manajer HRD']);
    }

    public function test_numbers_are_sequential_per_year_and_type_and_sp_gets_six_months(): void
    {
        $this->issue('sp1')->assertRedirect();
        $this->issue('sp1');
        $this->issue('sp2');
        $this->issue('sp1', ['issued_on' => '2027-01-05']);

        $this->assertSame(['001/SP1/XI/2026', '002/SP1/XI/2026', '001/SP2/XI/2026', '001/SP1/I/2027'], Discipline::orderBy('id')->pluck('doc_no')->all());
        $this->assertSame('2027-05-02', Discipline::first()->until_on->format('Y-m-d'));
    }

    public function test_phk_needs_an_effective_date_and_does_not_deactivate_the_employee(): void
    {
        $this->issue('phk')->assertSessionHasErrors('until_on');
        $this->issue('phk', ['until_on' => '2026-11-30'])->assertRedirect();
        $this->assertTrue($this->emp->refresh()->is_active);
        $this->actingAs($this->admin)->get(route('employees.show', ['employee' => $this->emp, 'exit' => 'laid_off']))->assertOk()->assertSee('PHK');
    }

    public function test_required_fields_and_active_employee_only(): void
    {
        $this->issue('sp1', ['reason' => ''])->assertSessionHasErrors('reason');
        $this->issue('sp1', ['signer_name' => ''])->assertSessionHasErrors('signer_name');
        $this->emp->update(['is_active' => false]);
        $this->issue('sp1')->assertSessionHasErrors('employee_id');
        $this->assertSame(0, Discipline::count());
    }

    public function test_suggests_the_next_step_and_warns_when_skipping(): void
    {
        $page = $this->actingAs($this->admin)->get(route('disciplines.create', ['employee' => $this->emp->id]))->assertOk();
        $this->assertSame('sp1', $page->viewData('suggest'));

        $this->issue('sp1');
        $this->assertSame('sp2', $this->get(route('disciplines.create', ['employee' => $this->emp->id]))->viewData('suggest'));

        $this->issue('sp3')->assertSessionHas('status', fn ($s) => str_contains($s, 'SP 2'));   // loncat: tetap terbit, dengan peringatan
        $this->assertSame(2, Discipline::count());
    }

    public function test_states_active_superseded_expired_and_revoked(): void
    {
        $this->issue('sp1', ['issued_on' => '2026-01-10']);   // berlaku s/d 2026-07-10: kedaluwarsa
        $this->issue('sp1', ['issued_on' => '2026-10-01']);
        $this->issue('sp2', ['issued_on' => '2026-11-02']);
        $all = Discipline::where('employee_id', $this->emp->id)->orderBy('id')->get();

        $this->assertSame(['Kedaluwarsa', 'Diganti', 'Aktif'], $all->map(fn ($d) => $d->stateLabel($all))->all());
        $rows = $this->get(route('employees.index'))->assertOk()->viewData('rows');
        $this->assertSame('SP 2', $rows[0]['sp']);

        $sp2 = $all->last();
        $this->post(route('disciplines.revoke', $sp2), ['revoke_reason' => ''])->assertSessionHasErrors('revoke_reason');
        $this->post(route('disciplines.revoke', $sp2), ['revoke_reason' => 'Salah ketik'])->assertRedirect();
        $all = Discipline::where('employee_id', $this->emp->id)->orderBy('id')->get();
        $this->assertSame('Dicabut', $all->last()->stateLabel($all));
        $this->assertSame('Aktif', $all[1]->stateLabel($all));   // SP 1 kembali aktif setelah SP 2 dicabut
        $this->post(route('disciplines.revoke', $sp2), ['revoke_reason' => 'Lagi'])->assertStatus(422);
    }

    public function test_letter_prints_with_masked_nik_and_screens_open(): void
    {
        $this->issue('sp1');
        $d = Discipline::firstOrFail();
        $this->actingAs($this->admin);
        $this->get(route('disciplines.index'))->assertOk();
        $this->get(route('disciplines.show', $d))->assertOk()->assertSee('001/SP1/XI/2026');
        $this->get(route('disciplines.create'))->assertOk();
        $this->get(route('employees.show', $this->emp))->assertOk()->assertSee('001/SP1/XI/2026');

        $print = $this->get(route('disciplines.print', $d))->assertOk()->assertSee('Surat Peringatan I')->assertSee('3273 **** **** 0001')->assertSee('Pak Hadi');
        $this->assertStringNotContainsString('3273010101010001', $print->getContent());
        $this->assertSame('Surat Pemutusan Hubungan Kerja', Discipline::LETTERS['phk']);
    }

    public function test_access_follows_the_group(): void
    {
        $this->actingAs(User::factory()->supervisor()->create())->get(route('disciplines.index'))->assertForbidden();

        $group = UserGroup::create(['code' => 'HRG', 'name' => 'HR']);
        $group->syncAccess(['HRD' => 'ro']);
        $reader = User::factory()->supervisor()->create(['group_id' => $group->id]);
        $this->actingAs($reader)->get(route('disciplines.index'))->assertOk();
        $this->post(route('disciplines.store'), ['employee_id' => $this->emp->id, 'type' => 'sp1', 'issued_on' => '2026-11-02', 'reason' => 'x', 'signer_name' => 'a', 'signer_title' => 'b'])->assertForbidden();

        $group->syncAccess(['HRD' => 'rw']);
        $this->actingAs($reader->fresh())->post(route('disciplines.store'), ['employee_id' => $this->emp->id, 'type' => 'sp1', 'issued_on' => '2026-11-02', 'reason' => 'x', 'signer_name' => 'a', 'signer_title' => 'b'])->assertRedirect();
    }

    public function test_index_splits_this_month_cards_from_earlier_months_with_colour_per_type(): void
    {
        $this->issue('sp1', ['issued_on' => '2026-10-15'])->assertRedirect();
        $this->issue('sp2', ['issued_on' => '2026-11-02'])->assertRedirect();

        $res = $this->get(route('disciplines.index'))->assertOk()->assertSee('Bulan ini')->assertSee('Bulan sebelumnya');
        $current = $res->viewData('current');
        $history = $res->viewData('history');

        $this->assertCount(1, $current);
        $this->assertSame('SP 2', $current[0]['type']);
        $this->assertCount(1, $history);
        $this->assertSame('SP 1', $history[0]['type']);
        $this->assertStringStartsWith('2026-10 ', $history[0]['month']);   // urut kronologis di filter Bulan
        $this->assertNotSame($current[0]['hue'], $history[0]['hue']);       // tiap jenis punya warna sendiri
        $this->assertSame('tangerine', $current[0]['tone']);
        $this->assertSame('yellow', $history[0]['tone']);
    }
}
