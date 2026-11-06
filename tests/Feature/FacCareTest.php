<?php

namespace Tests\Feature;

use App\Models\FacAsset;
use App\Models\FacCategory;
use App\Models\FacOrder;
use App\Models\FacPlace;
use App\Models\FacPlan;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\FacCare;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga GA Perawatan Gedung dan Aset: master (kode aset otomatis dan permanen, gedung / ruang, kategori), jatuh tempo jadwal berkala per periode dan statusnya, siklus Perintah Kerja (Draf > Confirm > Approve
 *                hanya Supervisor bukan pembuat), biaya hanya dari tiket Disetujui, penutupan jadwal dan kondisi aset saat Approve, hak Grup per modul, semua layar terbuka, dan data contoh.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class FacCareTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('ga');
        $this->admin = User::factory()->administrator()->create();
    }

    private function asset(array $over = []): FacAsset
    {
        $cat = FacCategory::firstOrCreate(['code' => 'LST'], ['name' => 'Listrik', 'icon' => 'fa-bolt']);

        return FacAsset::create($over + ['asset_code' => FacAsset::nextCode(), 'name' => 'Genset', 'category_id' => $cat->id, 'cond' => 'rusak']);
    }

    // Pengguna dengan Grup berhak modul GA (modul GA tidak masuk bawaan peran); $role = nama state factory (supervisor, adminData) atau kosong.
    private function user(array $access = ['FAT' => 'rw', 'FAM' => 'rw'], ?string $role = null): User
    {
        $group = UserGroup::create(['code' => 'G'.UserGroup::count(), 'name' => 'GA uji']);
        $group->syncAccess($access);
        $factory = User::factory();

        return ($role ? $factory->{$role}() : $factory)->create(['group_id' => $group->id]);
    }

    private function draft(User $by, array $over = []): FacOrder
    {
        return FacOrder::create($over + ['title' => 'Genset mati', 'priority' => 'high', 'status' => 'draft', 'reported_at' => today()->subDays(2), 'handler' => 'internal', 'technician' => 'Andi', 'created_by' => $by->id]);
    }

    public function test_asset_code_is_automatic_and_permanent(): void
    {
        $this->actingAs($this->admin);
        $cat = FacCategory::create(['code' => 'BGN', 'name' => 'Bangunan', 'icon' => 'fa-building']);
        $this->post(route('fac-assets.store'), ['name' => 'Pintu', 'category_id' => $cat->id, 'cond' => 'baik', 'photo' => UploadedFile::fake()->image('a.png')])->assertRedirect();
        $this->post(route('fac-assets.store'), ['name' => 'Jendela', 'category_id' => $cat->id, 'cond' => 'baik', 'asset_code' => 'HACK'])->assertRedirect();
        $this->assertSame(['AST-0001', 'AST-0002'], FacAsset::orderBy('id')->pluck('asset_code')->all());
        Storage::disk('ga')->assertExists(FacAsset::first()->photo_path);

        $a = FacAsset::first();
        $this->put(route('fac-assets.update', $a), ['name' => 'Pintu baru', 'category_id' => $cat->id, 'cond' => 'perlu', 'asset_code' => 'X'])->assertRedirect();
        $this->assertSame(['AST-0001', 'Pintu baru', 'perlu'], [$a->fresh()->asset_code, $a->fresh()->name, $a->fresh()->cond]);
        $this->post(route('fac-assets.store'), ['name' => 'Salah', 'category_id' => $cat->id, 'cond' => 'ngawur'])->assertSessionHasErrors('cond');
    }

    public function test_an_asset_with_an_open_ticket_cannot_be_deactivated_and_is_never_deleted(): void
    {
        $this->actingAs($this->admin);
        $a = $this->asset();
        $this->draft($this->admin, ['asset_id' => $a->id]);
        $this->put(route('fac-assets.update', $a), ['_action' => 'deactivate'])->assertSessionHasErrors('name');
        $this->postJson(route('fac-assets.toggle', $a))->assertStatus(422);
        $this->assertTrue($a->fresh()->is_active);
    }

    public function test_places_have_two_levels_and_an_occupied_place_cannot_be_deactivated(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('fac-places.store'), ['name' => 'Gedung A'])->assertRedirect();
        $a = FacPlace::first();
        $this->assertSame('GA', $a->code);
        $this->post(route('fac-places.store'), ['name' => 'Ruang Server', 'parent_id' => $a->id])->assertRedirect();
        $room = FacPlace::firstWhere('name', 'Ruang Server');
        $this->assertSame('Gedung A › Ruang Server', $room->path());
        $this->post(route('fac-places.store'), ['name' => 'Cabang', 'parent_id' => $room->id])->assertSessionHasErrors('parent_id');   // ruang tidak bisa jadi induk

        $this->putJson(route('fac-places.update', $a), ['_action' => 'deactivate'])->assertSessionHasErrors('name');
        $this->asset(['place_id' => $room->id, 'cond' => 'baik']);
        $this->postJson(route('fac-places.toggle', $room))->assertStatus(422);
    }

    public function test_next_due_follows_the_period_and_the_status_is_computed(): void
    {
        $plan = fn (string $period, ?string $last, string $start = '2026-01-01') => new FacPlan(['period' => $period, 'start_at' => $start, 'last_done_at' => $last]);
        $this->assertSame('2026-01-01', FacCare::nextDue($plan('monthly', null))->toDateString());   // belum pernah dikerjakan = tanggal mulai
        $this->assertSame('2026-03-08', FacCare::nextDue($plan('weekly', '2026-03-01'))->toDateString());
        $this->assertSame('2026-02-28', FacCare::nextDue($plan('monthly', '2026-01-31'))->toDateString());   // akhir bulan tidak meluap
        $this->assertSame('2026-07-15', FacCare::nextDue($plan('quarterly', '2026-04-15'))->toDateString());
        $this->assertSame('2027-03-01', FacCare::nextDue($plan('yearly', '2026-03-01'))->toDateString());

        $a = FacPlan::create(['title' => 'A', 'period' => 'monthly', 'start_at' => today()->subYear(), 'last_done_at' => today()->subMonth()->subDays(5), 'asset_id' => $this->asset()->id]);   // lewat 5 hari
        $b = FacPlan::create(['title' => 'B', 'period' => 'monthly', 'start_at' => today()->subYear(), 'last_done_at' => today()->subDays(20), 'asset_id' => $this->asset()->id]);   // sekitar 10 hari lagi
        $c = FacPlan::create(['title' => 'C', 'period' => 'yearly', 'start_at' => today()->subYear(), 'last_done_at' => today()->subDays(10), 'asset_id' => $this->asset()->id]);
        $over = FacCare::overview(collect([$a, $b, $c]));
        $this->assertSame(['terlambat', 'segera', 'aman'], [$over[$a->id]['status'], $over[$b->id]['status'], $over[$c->id]['status']]);
        $this->assertLessThan(0, $over[$a->id]['days']);
    }

    public function test_plan_needs_exactly_one_target(): void
    {
        $this->actingAs($this->admin);
        $a = $this->asset();
        $base = ['title' => 'Cek', 'period' => 'monthly', 'start_at' => today()->toDateString()];
        $this->post(route('fac-plans.store'), $base)->assertSessionHasErrors('asset_id');
        $this->post(route('fac-plans.store'), $base + ['asset_id' => $a->id, 'place_id' => FacPlace::create(['code' => 'X', 'name' => 'X'])->id])->assertSessionHasErrors('asset_id');
        $this->post(route('fac-plans.store'), $base + ['asset_id' => $a->id])->assertRedirect();
        $this->assertSame(1, FacPlan::count());
    }

    public function test_order_lifecycle_confirm_then_supervisor_approves_and_closes_the_plan_and_asset(): void
    {
        $entry = $this->user();
        $sup = $this->user(role: 'supervisor');
        $asset = $this->asset();
        $plan = FacPlan::create(['title' => 'Servis', 'period' => 'monthly', 'start_at' => today()->subYear(), 'last_done_at' => today()->subMonths(2), 'asset_id' => $asset->id]);
        $o = $this->draft($entry, ['asset_id' => $asset->id, 'plan_id' => $plan->id]);
        $payload = ['title' => 'Genset mati', 'priority' => 'high', 'reported_at' => $o->reported_at->toDateString(), 'handler' => 'internal', 'technician' => 'Andi', 'asset_id' => $asset->id, 'plan_id' => $plan->id, 'labor_cost' => 500000, 'material_cost' => 1500000];

        // Confirm tanpa tanggal selesai ditolak, nomor belum lahir.
        $this->actingAs($entry)->put(route('fac-orders.update', $o), $payload + ['_action' => 'confirm'])->assertSessionHasErrors('finished_at');
        $this->assertNull($o->fresh()->no);

        $this->put(route('fac-orders.update', $o), $payload + ['_action' => 'confirm', 'finished_at' => today()->toDateString()])->assertRedirect();
        $o->refresh();
        $this->assertSame(['approval', 'WO-'.today()->format('ym').'-0001'], [$o->status, $o->no]);
        $this->assertSame(2000000.0, $o->total());

        // Pembuat (Data Entry) tidak bisa menyetujui; Supervisor bisa; dokumen terkunci setelah Confirm.
        $this->put(route('fac-orders.update', $o), ['_action' => 'approve'])->assertForbidden();
        $this->put(route('fac-orders.update', $o), $payload + ['_action' => 'save'])->assertStatus(422);
        $this->actingAs($sup)->put(route('fac-orders.update', $o), ['_action' => 'approve'])->assertRedirect();
        $o->refresh();
        $this->assertSame(['approved', $sup->id], [$o->status, $o->approved_by]);
        $this->assertSame(today()->toDateString(), $plan->fresh()->last_done_at->toDateString());   // jadwal maju
        $this->assertSame('baik', $asset->fresh()->cond);   // Rusak > Baik karena tak ada tiket terbuka lain
        $this->put(route('fac-orders.update', $o), ['_action' => 'approve'])->assertStatus(422);   // dua kali tidak melompati siklus
    }

    public function test_supervisor_cannot_approve_own_order_and_reject_needs_a_reason(): void
    {
        $sup = $this->user(role: 'supervisor');
        $other = $this->user(role: 'supervisor');
        $o = $this->draft($sup, ['status' => 'approval', 'no' => 'WO-0001', 'finished_at' => today()]);
        $this->actingAs($sup)->put(route('fac-orders.update', $o), ['_action' => 'approve'])->assertForbidden();
        $this->actingAs($other)->put(route('fac-orders.update', $o), ['_action' => 'reject'])->assertStatus(422);
        $this->put(route('fac-orders.update', $o), ['_action' => 'reject', '_reason' => 'Biaya terlalu tinggi'])->assertRedirect();
        $this->assertSame(['rejected', 'Biaya terlalu tinggi'], [$o->fresh()->status, $o->fresh()->reason]);
    }

    public function test_admin_data_role_cannot_approve(): void
    {
        $o = $this->draft($this->user(), ['status' => 'approval', 'no' => 'WO-0002', 'finished_at' => today()]);
        $this->actingAs($this->user(role: 'adminData'))->put(route('fac-orders.update', $o), ['_action' => 'approve'])->assertForbidden();
        $this->assertSame('approval', $o->fresh()->status);
    }

    public function test_confirm_requires_the_vendor_or_the_technician_and_an_asset_or_place(): void
    {
        $this->actingAs($this->admin);
        $o = $this->draft($this->admin);
        $base = ['title' => 'X', 'priority' => 'normal', 'reported_at' => today()->toDateString(), 'finished_at' => today()->toDateString(), '_action' => 'confirm', 'asset_id' => $this->asset()->id];
        $this->put(route('fac-orders.update', $o), $base + ['handler' => 'vendor'])->assertSessionHasErrors('vendor_id');
        $this->put(route('fac-orders.update', $o), $base + ['handler' => 'internal'])->assertSessionHasErrors('technician');
        $this->put(route('fac-orders.update', $o), array_diff_key($base, ['asset_id' => 1]) + ['handler' => 'internal', 'technician' => 'A'])->assertSessionHasErrors('asset_id');
        $this->post(route('fac-orders.store'), ['title' => 'Tanpa lokasi', 'priority' => 'normal', 'reported_at' => today()->toDateString(), 'handler' => 'internal'])->assertSessionHasErrors('asset_id');
    }

    public function test_anyone_with_the_module_reports_a_breakdown_with_photos_and_only_the_owner_edits_the_draft(): void
    {
        $writer = $this->user(['FAT' => 'rw']);
        $asset = $this->asset();
        $this->actingAs($writer)->post(route('fac-orders.store'), [
            'title' => 'Lampu mati', 'priority' => 'low', 'reported_at' => today()->toDateString(), 'handler' => 'vendor', 'asset_id' => $asset->id, 'photo_before' => UploadedFile::fake()->image('b.jpg'),
        ])->assertRedirect();
        $o = FacOrder::first();
        $this->assertSame(['draft', $writer->id, null], [$o->status, $o->created_by, $o->no]);
        Storage::disk('ga')->assertExists($o->photo_before_path);
        $this->get(route('fac-orders.file', [$o, 'before']))->assertOk();
        $this->get(route('fac-orders.file', [$o, 'after']))->assertNotFound();

        $this->actingAs($this->user(['FAT' => 'rw']))->put(route('fac-orders.update', $o), ['_action' => 'delete'])->assertForbidden();   // bukan pembuat
        $this->actingAs($writer)->put(route('fac-orders.update', $o), ['_action' => 'delete'])->assertRedirect(route('fac-orders.index'));
        $this->assertSame(0, FacOrder::count());
        Storage::disk('ga')->assertMissing($o->photo_before_path);
    }

    public function test_costs_count_only_approved_orders_by_finish_month(): void
    {
        $u = User::factory()->create();
        $mk = fn (string $status, float $labor, float $mat, $finished) => $this->draft($u, ['status' => $status, 'labor_cost' => $labor, 'material_cost' => $mat, 'finished_at' => $finished, 'no' => 'WO-'.random_int(1000, 9999)]);
        $mk('approved', 100000, 400000, today());
        $mk('approved', 50000, 0, today()->subMonthsNoOverflow(2));
        $mk('approval', 999999, 0, today());
        $mk('draft', 999999, 0, null);
        $mk('rejected', 999999, 0, today());

        $this->assertSame(500000.0, FacCare::costInRange(today()->startOfMonth(), today()->endOfMonth()));
        $trend = FacCare::costByMonth();
        $this->assertCount(6, $trend);
        $this->assertSame([500000.0, 100000.0, 400000.0], [$trend[5]['value'], $trend[5]['tenaga'], $trend[5]['material']]);
        $this->assertSame(50000.0, $trend[3]['value']);
        $this->assertSame(1, FacCare::dashboard()['waiting']);
    }

    public function test_group_rights_guard_each_module(): void
    {
        $viewer = $this->user(['FAM' => 'ro']);
        $this->actingAs($viewer)->get(route('fac-care.index'))->assertOk();
        $this->get(route('fac-orders.index'))->assertForbidden();
        $this->get(route('fac-assets.index'))->assertForbidden();

        $entry = $this->user(['FAC' => 'rw', 'FAA' => 'rw']);   // Kategori butuh Supervisor
        $this->actingAs($entry)->get(route('fac-categories.index'))->assertForbidden();
        $this->actingAs($entry)->get(route('fac-assets.index'))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('fac-care.index'))->assertForbidden();   // tanpa Grup: modul GA tidak otomatis
    }

    public function test_every_screen_opens_and_the_report_totals_approved_costs(): void
    {
        $this->actingAs($this->admin);
        $asset = $this->asset(['place_id' => FacPlace::create(['code' => 'GA', 'name' => 'Gedung A'])->id]);
        $plan = FacPlan::create(['title' => 'Servis', 'period' => 'monthly', 'start_at' => today(), 'asset_id' => $asset->id]);
        $o = $this->draft($this->admin, ['asset_id' => $asset->id, 'place_id' => $asset->place_id, 'status' => 'approved', 'no' => 'WO-1', 'labor_cost' => 250000, 'material_cost' => 750000, 'finished_at' => today()]);

        foreach (['fac-care.index', 'fac-setup.index', 'fac-orders.index', 'fac-orders.create', 'fac-assets.index', 'fac-assets.create', 'fac-places.index', 'fac-places.create', 'fac-categories.index', 'fac-categories.create', 'fac-plans.index', 'fac-plans.create'] as $name) {
            $this->get(route($name))->assertOk();
        }
        $this->get(route('fac-care.show', $asset))->assertOk()->assertSee('AST-0001')->assertSee('1.000.000');
        $this->get(route('fac-assets.show', $asset))->assertOk()->assertSee('Lapor Kerusakan');
        $this->get(route('fac-orders.show', $o))->assertOk()->assertSee('WO-1');
        $this->get(route('fac-plans.show', $plan))->assertOk()->assertSee('Buat Tiket dari Jadwal');
        $this->get(route('fac-orders.create', ['plan' => $plan->id]))->assertOk()->assertSee('Servis');
        $this->get(route('fac-orders.create', ['asset' => $asset->id]))->assertOk();
        $rows = $this->get(route('reports.fac-cost'))->assertOk()->assertSee('Laporan Biaya Perawatan')->viewData('rows');   // baris laporan dirender komponen di klien: periksa datanya
        $this->assertSame([['WO-1', 'AST-0001 · Genset', 250000.0, 750000.0, 1000000.0]], array_map(fn ($r) => [$r['no'], $r['asset'], $r['labor'], $r['material'], $r['total']], $rows));
        $this->assertSame([], $this->get(route('reports.fac-cost', ['category' => 99999]))->assertOk()->viewData('rows'));
        $this->assertCount(1, $this->get(route('reports.fac-cost', ['category' => $asset->category_id, 'building' => $asset->place_id]))->viewData('rows'));
        $this->get(route('opening', 'ga'))->assertOk()->assertSee('Perawatan Gedung dan Aset');
        $this->get(route('home'))->assertOk()->assertSee('Gedung dan Aset');
    }

    public function test_sample_data_is_complete_and_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $counts = fn () => [FacPlace::count(), FacCategory::count(), FacAsset::count(), FacPlan::count(), FacOrder::count()];
        $first = $counts();
        $this->assertSame([11, 7, 26, 9, 13], $first);
        $this->assertEqualsCanonicalizing(['draft', 'approval', 'approved', 'rejected', 'cancelled'], FacOrder::distinct()->pluck('status')->all());
        $by = collect(FacCare::overview(FacPlan::all()))->countBy('status');
        $this->assertTrue($by->has('aman') && $by->has('segera') && $by->has('terlambat'));
        $this->assertGreaterThan(0, FacCare::costInRange(today()->subMonths(6), today()));

        $this->seed(DatabaseSeeder::class);
        $this->assertSame($first, $counts());
        $this->assertSame(26, FacAsset::distinct()->count('asset_code'));
    }
}
