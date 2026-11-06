<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\FacAsset;
use App\Models\FacCategory;
use App\Models\FacHandover;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\FacCustody;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga penggunaan / penyerahan aset GA: Serah Terima (pinjam, bawa pulang, pengembalian, hilang) dengan siklus Draf > Confirm > Approve Supervisor (bukan pembuat), efek pada posisi dan pemegang aset, aturan isian,
 *                satu dokumen terbuka per aset, daftar monitor (di luar kantor, terlambat, hilang, pemegang yang sudah keluar), Opname Aset dan temuannya, input massal aset, laporan per pemegang, dan semua layar terbuka.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class FacCustodyTest extends TestCase
{
    use RefreshDatabase;

    private Department $dept;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dept = Department::create(['code' => 'D1', 'name' => 'Pemasaran']);
    }

    private function emp(array $over = []): Employee
    {
        return Employee::create($over + ['code' => 'E-'.random_int(1000, 9999), 'name' => 'Budi', 'gender' => 'L', 'department_id' => $this->dept->id, 'employment' => 'tetap', 'join_date' => '2021-02-03']);
    }

    private function asset(array $over = []): FacAsset
    {
        $cat = FacCategory::firstOrCreate(['code' => 'ELK'], ['name' => 'Elektronik', 'icon' => 'fa-computer']);

        return FacAsset::create($over + ['asset_code' => FacAsset::nextCode(), 'name' => 'Laptop', 'category_id' => $cat->id, 'monitored' => true]);
    }

    private function user(string $role = ''): User
    {
        $group = UserGroup::create(['code' => 'G'.UserGroup::count(), 'name' => 'GA uji']);
        $group->syncAccess(['FAH' => 'rw', 'FAO' => 'rw', 'FAM' => 'rw', 'FAP' => 'ro']);

        return ($role ? User::factory()->{$role}() : User::factory())->create(['group_id' => $group->id]);
    }

    private function loan(FacAsset $a, Employee $e, array $over = []): array
    {
        return $over + ['type' => 'loan', 'asset_id' => $a->id, 'employee_id' => $e->id, 'doc_date' => today()->toDateString(), 'expected_return' => today()->addDays(3)->toDateString(), 'condition_out' => 'baik', 'purpose' => 'Kerja'];
    }

    private function cycle(User $maker, User $approver, array $payload, ?FacHandover $existing = null): FacHandover
    {
        $this->actingAs($maker);
        if (! $existing) {
            $this->post(route('fac-handovers.store'), $payload)->assertRedirect();
            $existing = FacHandover::latest('id')->first();
        }
        $this->put(route('fac-handovers.update', $existing), $payload + ['_action' => 'confirm'])->assertRedirect();
        $this->actingAs($approver)->put(route('fac-handovers.update', $existing), ['_action' => 'approve'])->assertRedirect();

        return $existing->fresh();
    }

    public function test_loan_return_cycle_moves_position_holder_and_condition(): void
    {
        [$maker, $spv] = [$this->user(), $this->user('supervisor')];
        $a = $this->asset();
        $e = $this->emp();

        $h = $this->cycle($maker, $spv, $this->loan($a, $e, ['type' => 'home']));
        $this->assertSame(['approved', 'ST-'.today()->format('ym').'-0001'], [$h->status, $h->no]);
        $a->refresh();
        $this->assertSame(['home', $e->id, today()->addDays(3)->toDateString()], [$a->position, $a->holder_employee_id, $a->due_back->toDateString()]);
        $this->assertFalse($a->overdue());

        $this->cycle($maker, $spv, ['type' => 'return', 'asset_id' => $a->id, 'employee_id' => $e->id, 'doc_date' => today()->toDateString(), 'condition_out' => 'rusak', 'damage_note' => 'Layar retak']);
        $a->refresh();
        $this->assertSame(['repair', null, null, 'rusak'], [$a->position, $a->holder_employee_id, $a->due_back, $a->cond]);   // kembali rusak = dalam perbaikan
        $this->assertSame(2, $a->handovers()->where('status', 'approved')->count());   // riwayat pemegang
    }

    public function test_only_a_supervisor_who_is_not_the_maker_approves_and_documents_lock(): void
    {
        [$maker, $spv] = [$this->user(), $this->user('supervisor')];
        $a = $this->asset();
        $payload = $this->loan($a, $this->emp());
        $this->actingAs($maker)->post(route('fac-handovers.store'), $payload)->assertRedirect();
        $h = FacHandover::first();
        $this->put(route('fac-handovers.update', $h), $payload + ['_action' => 'confirm'])->assertRedirect();

        $this->put(route('fac-handovers.update', $h), ['_action' => 'approve'])->assertForbidden();
        $this->put(route('fac-handovers.update', $h), $payload + ['_action' => 'save'])->assertStatus(422);
        $this->actingAs($this->user('adminData'))->put(route('fac-handovers.update', $h), ['_action' => 'approve'])->assertForbidden();
        $this->actingAs($spv)->put(route('fac-handovers.update', $h), ['_action' => 'reject'])->assertStatus(422);   // alasan wajib
        $this->put(route('fac-handovers.update', $h), ['_action' => 'reject', '_reason' => 'Belum ada izin atasan'])->assertRedirect();
        $this->assertSame(['rejected', 'office'], [$h->fresh()->status, $a->fresh()->position]);   // ditolak: aset tidak berubah
    }

    public function test_field_rules_and_one_open_document_per_asset(): void
    {
        $this->actingAs($this->user());
        $a = $this->asset();
        $e = $this->emp();
        $base = $this->loan($a, $e);

        $this->post(route('fac-handovers.store'), array_diff_key($base, ['employee_id' => 1]))->assertSessionHasErrors('employee_id');
        $this->post(route('fac-handovers.store'), ['type' => 'home', 'employee_id' => null, 'department_id' => $this->dept->id] + $base)->assertSessionHasErrors('employee_id');   // bawa pulang atas nama karyawan
        $this->post(route('fac-handovers.store'), ['expected_return' => null] + $base)->assertSessionHasErrors('expected_return');
        $this->post(route('fac-handovers.store'), ['expected_return' => today()->subDay()->toDateString()] + $base)->assertSessionHasErrors('expected_return');
        $this->post(route('fac-handovers.store'), ['condition_out' => null] + $base)->assertSessionHasErrors('condition_out');
        $this->post(route('fac-handovers.store'), ['type' => 'lost', 'purpose' => ''] + $base)->assertSessionHasErrors('purpose');
        $this->post(route('fac-handovers.store'), ['type' => 'return', 'condition_out' => null] + $base)->assertSessionHasErrors('condition_out');
        $this->assertSame(0, FacHandover::count());

        $this->post(route('fac-handovers.store'), $base)->assertRedirect();
        $this->post(route('fac-handovers.store'), $base)->assertSessionHasErrors('asset_id');   // masih ada dokumen terbuka
        $this->assertSame(1, FacHandover::count());
    }

    public function test_an_asset_that_is_out_cannot_be_lent_again_and_a_returned_one_cannot_be_returned(): void
    {
        [$maker, $spv] = [$this->user(), $this->user('supervisor')];
        $a = $this->asset();
        $e = $this->emp();
        $this->cycle($maker, $spv, $this->loan($a, $e));

        $this->actingAs($maker)->post(route('fac-handovers.store'), $this->loan($a, $e))->assertRedirect();
        $h = FacHandover::latest('id')->first();
        $this->put(route('fac-handovers.update', $h), $this->loan($a, $e) + ['_action' => 'confirm'])->assertSessionHasErrors('asset_id');   // sedang dipinjam
        $this->assertNull($h->fresh()->no);

        $b = $this->asset(['name' => 'Kalkulator']);
        $this->post(route('fac-handovers.store'), ['type' => 'return', 'asset_id' => $b->id, 'doc_date' => today()->toDateString(), 'condition_out' => 'baik'])->assertRedirect();
        $this->put(route('fac-handovers.update', FacHandover::latest('id')->first()), ['type' => 'return', 'asset_id' => $b->id, 'doc_date' => today()->toDateString(), 'condition_out' => 'baik', '_action' => 'confirm'])->assertSessionHasErrors('asset_id');
    }

    public function test_lost_report_marks_the_asset_and_the_monitor_lists_overdue_lost_and_left_holders(): void
    {
        [$maker, $spv] = [$this->user(), $this->user('supervisor')];
        $e = $this->emp(['name' => 'Rina']);
        $late = $this->asset(['name' => 'Laptop A', 'position' => 'loan', 'holder_employee_id' => $e->id, 'due_back' => today()->subDays(4)]);
        $fine = $this->asset(['name' => 'Laptop B', 'position' => 'home', 'holder_employee_id' => $e->id, 'due_back' => today()->addDays(2)]);
        $lost = $this->asset(['name' => 'Kalkulator']);
        $this->asset(['name' => 'Di kantor']);

        $this->cycle($maker, $spv, ['type' => 'lost', 'asset_id' => $lost->id, 'doc_date' => today()->toDateString(), 'purpose' => 'Tertinggal di taksi']);
        $this->assertSame('lost', $lost->fresh()->position);

        $this->assertTrue($late->fresh()->overdue());
        $this->assertSame(['out' => 2, 'overdue' => 1, 'lost' => 1, 'repair' => 0], FacCustody::counts());
        $this->assertSame([$late->id, $fine->id], FacCustody::outside()->pluck('id')->all());   // terlambat paling atas
        $this->assertSame(0, FacCustody::leftHolders()->count());
        $e->update(['is_active' => false, 'exit_date' => today()]);
        $this->assertSame(['Rina'], FacCustody::leftHolders()->pluck('name')->all());   // karyawan keluar masih memegang aset

        $this->actingAs($this->user())->get(route('fac-care.index'))->assertOk()->assertSee('terlambat dikembalikan')->assertSee('sudah keluar masih memegang aset: Rina')->assertSee('Laptop A');
    }

    public function test_stock_check_records_findings_until_a_later_check_finds_the_asset(): void
    {
        $this->actingAs($this->user());
        $e = $this->emp();
        $a = $this->asset(['name' => 'Laptop A', 'position' => 'home', 'holder_employee_id' => $e->id, 'due_back' => today()]);
        $b = $this->asset(['name' => 'Kalkulator', 'position' => 'loan', 'holder_employee_id' => $e->id, 'due_back' => today()]);
        $this->asset(['name' => 'Bukan milik Budi']);

        $this->get(route('fac-checks.create', ['employee' => $e->id]))->assertOk()->assertSee('Laptop A')->assertDontSee('Bukan milik Budi');
        $this->post(route('fac-checks.store'), ['employee_id' => $e->id, 'checked_at' => today()->toDateString(), 'asset' => [$a->id, $b->id], 'found' => [$b->id]])->assertRedirect();
        $this->assertSame([$a->id], FacCustody::findings()->pluck('id')->all());
        $this->assertSame($a->position, $a->fresh()->position);   // opname tidak mengubah posisi
        $this->get(route('fac-checks.index'))->assertOk();
        $this->get(route('fac-checks.show', DB::table('g_fac_check')->value('id')))->assertOk()->assertSee('Tidak ditemukan');

        $this->post(route('fac-checks.store'), ['employee_id' => $e->id, 'checked_at' => today()->toDateString(), 'asset' => [$a->id], 'found' => [$a->id]])->assertRedirect();
        $this->assertSame(0, FacCustody::findings()->count());   // ditemukan pada pemeriksaan berikutnya
        $this->post(route('fac-checks.store'), ['checked_at' => today()->toDateString(), 'asset' => [$a->id]])->assertStatus(422);   // orang atau ruang wajib
    }

    public function test_bulk_units_create_separate_assets_and_the_monitored_flag_is_kept(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $cat = FacCategory::create(['code' => 'ATK', 'name' => 'ATK tidak habis pakai', 'icon' => 'fa-calculator']);
        $this->post(route('fac-assets.store'), ['name' => 'Kalkulator', 'category_id' => $cat->id, 'cond' => 'baik', 'monitored' => '1', 'copies' => 3])->assertRedirect(route('fac-assets.index'));
        $this->assertSame(['AST-0001', 'AST-0002', 'AST-0003'], FacAsset::orderBy('id')->pluck('asset_code')->all());
        $this->assertSame(['Kalkulator #1', 'Kalkulator #2', 'Kalkulator #3'], FacAsset::orderBy('id')->pluck('name')->all());
        $this->assertSame(3, FacAsset::where('monitored', true)->count());
        $this->post(route('fac-assets.store'), ['name' => 'Terlalu banyak', 'category_id' => $cat->id, 'cond' => 'baik', 'copies' => 51])->assertSessionHasErrors('copies');
    }

    public function test_holder_report_and_every_new_screen_open(): void
    {
        $admin = User::factory()->administrator()->create();
        $e = $this->emp(['name' => 'Rina']);
        $a = $this->asset(['name' => 'Laptop A', 'position' => 'loan', 'holder_employee_id' => $e->id, 'due_back' => today()->subDays(2)]);
        $this->asset(['name' => 'Laptop B', 'position' => 'home', 'holder_employee_id' => $e->id, 'due_back' => today()->addDay()]);
        $this->asset(['name' => 'Di kantor']);
        $h = FacHandover::create(['type' => 'loan', 'asset_id' => $a->id, 'employee_id' => $e->id, 'doc_date' => today()->subDays(5), 'expected_return' => today()->subDays(2), 'condition_out' => 'baik', 'status' => 'approved', 'no' => 'ST-1', 'created_by' => $admin->id]);

        $this->actingAs($admin);
        $rows = $this->get(route('reports.fac-holders'))->assertOk()->viewData('rows');
        $this->assertSame(['Laptop A', 'Laptop B'], array_column($rows, 'asset'));
        $this->assertSame(['Terlambat 2 hari', 'Sesuai jadwal'], array_column($rows, 'status'));
        $this->assertSame(['Laptop A'], array_column($this->get(route('reports.fac-holders', ['late' => 1]))->viewData('rows'), 'asset'));

        foreach (['fac-handovers.index', 'fac-handovers.create', 'fac-checks.index', 'fac-checks.create'] as $name) {
            $this->get(route($name))->assertOk();
        }
        $this->get(route('fac-handovers.create', ['asset' => $a->id, 'type' => 'return']))->assertOk()->assertSee('Rina');
        $this->get(route('fac-handovers.show', $h))->assertOk()->assertSee('ST-1');
        $this->get(route('fac-assets.show', $a))->assertOk()->assertSee('TERLAMBAT')->assertSee('Kembalikan');
        $this->get(route('fac-care.show', $a))->assertOk()->assertSee('Riwayat pemegang')->assertSee('ST-1');
        $this->get(route('fac-assets.index'))->assertOk()->assertSee('Dipinjam: Rina');
        $this->get(route('home'))->assertOk()->assertSee('Di luar kantor');
    }
}
