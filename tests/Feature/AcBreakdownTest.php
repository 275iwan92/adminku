<?php

namespace Tests\Feature;

use App\Models\AcOrder;
use App\Models\User;
use App\Services\AcCare;
use App\Services\AcVisits;
use App\Support\Constants;
use App\Support\Menu;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\AcFixtures;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga Perawatan AC satu menu (Monitoring + Setup), Penjadwalan bertipe Preventif / Breakdown dan Catat hasil per AC: menu GA ringkas (butir tersembunyi tetap tampil bagi pengguna tanpa panel), Breakdown
 *                (keluhan wajib, Mendesak boleh di luar kapasitas dan hari kunjungan dengan alasan, tidak menggeser jatuh tempo preventif), hasil per AC dengan spare-part dan pesanan otomatis Selesai bila semua AC tercatat.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class AcBreakdownTest extends TestCase
{
    use AcFixtures, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 08:00:00');   // Senin
        Constants::forget();
        Storage::fake('ga');
        Mail::fake();
        $this->admin = User::factory()->administrator()->create(['name' => 'Sari GA']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Constants::forget();
        parent::tearDown();
    }

    private function fails(callable $fn, string $key, string $contains): void
    {
        try {
            $fn();
        } catch (ValidationException $e) {
            $this->assertStringContainsString($contains, $e->errors()[$key][0] ?? '(tidak ada galat untuk '.$key.')');

            return;
        }
        $this->fail('Seharusnya ditolak: '.$contains);
    }

    private function breakdown(array $units, string $date = '2026-11-02', array $extra = []): AcOrder
    {
        return AcCare::schedule($this->acVendor(), $date, '10:00', array_map(fn ($u) => $u->id, $units), $this->admin, $extra + ['type' => 'breakdown', 'complaint' => 'Tidak dingin, menetes air', 'priority' => 'normal']);
    }

    public function test_the_ga_menu_has_one_item_per_module_but_users_without_the_panel_keep_their_screens(): void
    {
        $labels = array_column(collect(Menu::layers($this->admin))->firstWhere('code', 'GA')['children'], 'label');
        $this->assertSame(['Perawatan Kendaraan', 'Perawatan AC', 'Perawatan Gedung'], $labels);

        // Sopir hanya Catat Odometer, petugas tanpa panel AC: tetap melihat layar yang diizinkan.
        $driver = $this->acUser(['GVO' => 'rw']);
        $this->assertSame(['Catat Odometer'], array_column(collect(Menu::layers($driver))->firstWhere('code', 'GA')['children'], 'label'));
        $staff = $this->acUser(['GAS' => 'rw', 'GAM' => 'ro']);
        $this->assertSame(['Perawatan AC'], array_column(collect(Menu::layers($staff))->firstWhere('code', 'GA')['children'], 'label'));
    }

    public function test_monitoring_lists_active_units_with_status_and_setup_tiles_follow_access(): void
    {
        $this->acLate();
        $broken = $this->acUnit(null, 5);
        $this->breakdown([$broken], '2026-11-02', ['priority' => 'urgent', 'override' => 'AC ruang server mati']);

        $this->actingAs($this->admin)->get(route('ac-care.index'))->assertOk()->assertSee('AC aktif')->assertSee('Kerusakan terbuka')->assertSee('Rusak')->assertSee('Terlambat');
        $this->actingAs($this->admin)->get(route('ac-setup.index'))->assertOk()->assertSee('Denah AC')->assertSee('Vendor AC')->assertSee('Pengaturan');

        $viewer = $this->acUser(['GAM' => 'ro', 'GAK' => 'ro']);
        $this->actingAs($viewer)->get(route('ac-setup.index'))->assertOk()->assertSee('Master AC')->assertDontSee('Vendor AC');
        $this->actingAs($viewer)->get(route('ac-schedule.index'))->assertForbidden();
        $this->actingAs($this->acUser(['GVM' => 'ro']))->get(route('ac-care.index'))->assertForbidden();
    }

    public function test_breakdown_requires_a_complaint_and_urgent_needs_a_reason(): void
    {
        $unit = $this->acUnit(null, 5);
        $this->fails(fn () => $this->breakdown([$unit], '2026-11-03', ['complaint' => '']), 'complaint', 'Keluhan wajib');
        $this->fails(fn () => $this->breakdown([$unit], '2026-11-03', ['priority' => 'urgent']), 'override_reason', 'butuh alasan');
    }

    public function test_urgent_breakdown_may_exceed_capacity_and_visit_days_but_normal_and_preventive_may_not(): void
    {
        $vendor = $this->acVendor();
        foreach (range(1, 3) as $i) {
            $this->acLate();   // dijadwalkan preventif memenuhi kapasitas Selasa
        }
        AcCare::schedule($vendor, '2026-11-03', null, \App\Models\AcUnit::query()->pluck('id')->all(), $this->admin);
        $unit = $this->acUnit(null, 5);

        $this->fails(fn () => $this->breakdown([$unit], '2026-11-03'), 'date', 'sudah penuh');   // Biasa tetap kena kapasitas
        $this->fails(fn () => $this->breakdown([$unit], '2026-11-08'), 'date', 'tidak diizinkan');   // Minggu

        $o = $this->breakdown([$unit], '2026-11-03', ['priority' => 'urgent', 'override' => 'AC ruang server mati']);
        $this->assertSame(['breakdown', 'urgent', 'AC ruang server mati'], [$o->type, $o->priority, $o->override_reason]);
        $this->assertSame('fix', $o->lines()->first()->job);
        $this->assertSame(4, AcCare::used($vendor->id, '2026-11-03'));
        $sunday = $this->breakdown([$this->acUnit(null, 5)], '2026-11-08', ['priority' => 'urgent', 'override' => 'Bocor ke ruang arsip']);
        $this->assertSame('2026-11-08', $sunday->visit_date->toDateString());
    }

    public function test_breakdown_is_its_own_order_and_does_not_join_a_preventive_draft(): void
    {
        $a = $this->acLate();
        $preventive = AcCare::schedule($this->acVendor(), '2026-11-04', null, [$a->id], $this->admin);
        $b = $this->acUnit(null, 5);
        $o = $this->breakdown([$b], '2026-11-04');
        $this->assertNotSame($preventive->id, $o->id);
        $this->assertSame(1, $preventive->lines()->count());
    }

    public function test_results_are_recorded_per_unit_with_parts_and_the_order_completes_when_all_are_recorded(): void
    {
        $a = $this->acUnit(null, 5);
        $b = $this->acUnit(null, 5);
        $o = $this->breakdown([$a, $b], '2026-11-02');
        $this->fails(fn () => AcVisits::recordResult($o->lines()->first(), '2026-11-02', [], [], $this->admin), 'status', 'berstatus Draf');
        AcVisits::send($o, $this->admin);
        AcVisits::confirm($o, $this->admin);
        [$l1, $l2] = $o->lines()->orderBy('id')->get()->all();

        $this->fails(fn () => AcVisits::recordResult($l1, '2026-11-03', [], [], $this->admin), 'done_at', 'setelah hari ini');
        $this->fails(fn () => AcVisits::recordResult($l1, '2026-11-01', [], [], $this->admin), 'done_at', 'sebelum tanggal kunjungan');

        $r = AcVisits::recordResult($l1, '2026-11-02', ['findings' => 'Freon ditambah', 'cause' => 'Kapasitor lemah', 'action' => 'Ganti kapasitor', 'labor_cost' => '200000'], [
            ['name' => 'Kapasitor 35 uF', 'qty' => '1', 'cost' => '150000'], ['name' => '', 'qty' => '5', 'cost' => null], ['name' => 'Pipa kuras', 'qty' => null, 'cost' => null],
        ], $this->admin);
        $this->assertSame('confirmed', $r->status);   // AC kedua belum tercatat
        $l1 = $l1->fresh('parts');
        $this->assertSame(['Kapasitor lemah', 200000.0, 2], [$l1->cause, $l1->labor_cost, $l1->parts->count()]);
        $this->assertSame(1.0, $l1->parts->firstWhere('name', 'Pipa kuras')->qty);

        AcVisits::recordResult($l1, '2026-11-02', ['findings' => 'Diperbaiki ulang'], [['name' => 'Kapasitor 40 uF', 'qty' => '1', 'cost' => '175000']], $this->admin);   // catat ulang menggantikan
        $this->assertSame(['Kapasitor 40 uF'], $l1->fresh('parts')->parts->pluck('name')->all());

        $done = AcVisits::recordResult($l2, '2026-11-02', ['findings' => 'Baik'], [], $this->admin);
        $this->assertSame('done', $done->status);
        $this->fails(fn () => AcVisits::recordResult($l2, '2026-11-02', [], [], $this->admin), 'status', 'berstatus Selesai');
    }

    public function test_a_breakdown_repair_does_not_move_the_preventive_due_date(): void
    {
        $unit = $this->acUnit(null, 80);   // cuci 80 hari lalu: jatuh tempo ±10 hari lagi
        $before = AcCare::overview(collect([$unit]))[$unit->id]['next_wash']->toDateString();
        $o = $this->breakdown([$unit], '2026-11-02');
        AcVisits::send($o, $this->admin);
        AcVisits::confirm($o, $this->admin);
        AcVisits::recordResult($o->lines()->first(), '2026-11-02', ['findings' => 'Baik'], [], $this->admin);

        $this->assertSame($before, AcCare::overview(collect([$unit->fresh()]))[$unit->id]['next_wash']->toDateString());
        $this->assertSame([1], array_values(AcCare::breakdownStats()['year']));
    }

    public function test_the_breakdown_message_names_the_complaint_and_urgent_orders_can_be_rescheduled_to_any_day(): void
    {
        $o = $this->breakdown([$this->acUnit(null, 5)], '2026-11-02', ['priority' => 'urgent', 'override' => 'AC ruang server mati']);
        $m = AcVisits::vendorMessage($o, 'Sari GA');
        $this->assertStringContainsString('MENDESAK', $m['subject']);
        $this->assertStringContainsString('Keluhan      : Tidak dingin, menetes air', $m['text']);
        $this->assertStringContainsString('spare-part', $m['text']);

        AcVisits::reschedule($o, '2026-11-08', '10:00', 'Vendor baru bisa Minggu', $this->admin);   // Minggu
        $this->assertSame('2026-11-08', $o->fresh()->visit_date->toDateString());
    }

    public function test_the_schedule_screen_posts_a_breakdown_and_the_visit_screen_records_the_result(): void
    {
        $unit = $this->acUnit(null, 5);
        $vendor = $this->acVendor();
        $this->actingAs($this->admin)->get(route('ac-schedule.index', ['type' => 'breakdown']))->assertOk()->assertSee('Keluhan')->assertSee($unit->asset_code);
        $this->post(route('ac-schedule.store'), ['vendor_id' => $vendor->id, 'type' => 'breakdown', 'date' => '2026-11-02', 'time' => '10:00', 'units' => [$unit->id], 'complaint' => 'Bocor', 'priority' => 'normal'])
            ->assertRedirect()->assertSessionHas('status');
        $o = AcOrder::firstOrFail();
        $this->assertSame('breakdown', $o->type);
        $this->post(route('ac-schedule.store'), ['vendor_id' => $vendor->id, 'type' => 'breakdown', 'date' => '2026-11-03', 'units' => [$this->acUnit(null, 5)->id], 'complaint' => ''])->assertSessionHasErrors('complaint');

        AcVisits::send($o, $this->admin);
        AcVisits::confirm($o, $this->admin);
        $this->get(route('ac-visits.show', $o))->assertOk()->assertSee('Catat hasil per AC')->assertSee('Bocor');
        $this->post(route('ac-visits.result', $o), ['line_id' => $o->lines()->first()->id, 'done_at' => '2026-11-02', 'findings' => 'Baik', 'cause' => 'Pipa tersumbat', 'parts' => [['name' => 'Pipa kuras', 'qty' => '1', 'cost' => '50000']]])
            ->assertRedirect(route('ac-visits.show', $o))->assertSessionHas('status');
        $this->assertSame('done', $o->fresh()->status);
        $this->get(route('ac-visits.show', $o))->assertSee('Pipa kuras');
        $this->get(route('ac-visits.index'))->assertSee('Breakdown');
    }

    public function test_the_vehicle_setup_hub_replaces_the_master_menu_items(): void
    {
        $this->actingAs($this->admin)->get(route('fleet.index'))->assertOk()->assertSee('Setup');
        $this->get(route('fleet.setup'))->assertOk()->assertSee('Master Kendaraan')->assertSee('Jenis Kendaraan')->assertSee('Pengaturan Kendaraan');
        $this->get(route('ac-setup.index'))->assertOk()->assertSee('Pengaturan AC');
    }
}
