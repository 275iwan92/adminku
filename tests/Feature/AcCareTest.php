<?php

namespace Tests\Feature;

use App\Models\AcOrder;
use App\Models\AcOrderLine;
use App\Models\AcUnit;
use App\Models\User;
use App\Services\AcCare;
use App\Services\AcVisits;
use App\Support\Constants;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\AcFixtures;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga perhitungan dan penjadwalan GA Perawatan AC: jatuh tempo cuci rutin (3 bulan) dan servis besar (12 bulan) yang bisa diatur, servis besar mencakup cuci, status Aman / Segera / Terlambat,
 *                kapasitas harian PER VENDOR dan hari / jam yang diizinkan ditegakkan di server, satu AC satu pesanan terbuka, dan susun otomatis (paling terlambat dulu, hari demi hari). Hari ini dibekukan 2026-11-02 (Senin).
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class AcCareTest extends TestCase
{
    use AcFixtures, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 08:00:00');
        Constants::forget();
        $this->admin = User::factory()->administrator()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Constants::forget();
        parent::tearDown();
    }

    private function due(AcUnit $u): array
    {
        return AcCare::overview(collect([$u->fresh()]))[$u->id];
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

    public function test_status_follows_the_distance_to_the_due_date(): void
    {
        $at = fn (string $lastWash) => $this->due($this->acUnit(null, null, ['last_wash_at' => $lastWash, 'last_major_at' => '2026-01-01']));

        $late = $at('2026-07-01');   // jatuh tempo 01-10-2026: lewat 32 hari
        $this->assertSame(['terlambat', -32, 'lewat 32 hari', 'Terlambat'], [$late['status'], $late['days'], $late['due_text'], $late['label']]);
        $today = $at('2026-08-02');   // jatuh tempo hari ini
        $this->assertSame(['segera', 0, 'hari ini'], [$today['status'], $today['days'], $today['due_text']]);
        $edge = $at('2026-08-03');   // 03-11-2026 = 1 hari lagi
        $this->assertSame(['segera', 1], [$edge['status'], $edge['days']]);
        $limit = $at('2026-09-02');   // 02-12-2026 = tepat 30 hari: masih Segera
        $this->assertSame(['segera', 30], [$limit['status'], $limit['days']]);
        $safe = $at('2026-09-03');   // 31 hari
        $this->assertSame(['aman', 31], [$safe['status'], $safe['days']]);
    }

    public function test_the_wash_interval_the_major_interval_and_the_soon_window_come_from_settings(): void
    {
        $u = $this->acUnit(null, null, ['last_wash_at' => '2026-09-02', 'last_major_at' => '2026-01-15']);
        $this->assertSame('2026-12-02', $this->due($u)['next_wash']->toDateString());
        Constants::set('ga.ac.wash_months', 6, 'ga');
        $this->assertSame('2027-03-02', $this->due($u)['next_wash']->toDateString());
        Constants::set('ga.ac.major_months', 18, 'ga');
        $this->assertSame('2027-07-15', $this->due($u)['next_major']->toDateString());
        Constants::set('ga.ac.wash_months', 3, 'ga');
        Constants::set('ga.ac.soon_days', 5, 'ga');
        $this->assertSame('aman', $this->due($u)['status']);   // 30 hari lagi, ambang 5 hari
    }

    public function test_a_major_service_counts_as_a_wash_and_the_nearer_due_date_decides(): void
    {
        // Servis besar terakhir 13 bulan lalu (jatuh tempo kemarin... 12 bulan = lewat), cuci terakhir 1 bulan lalu.
        $u = $this->acUnit(null, null, ['last_wash_at' => '2026-10-01', 'last_major_at' => '2025-10-01']);
        $o = $this->due($u);
        $this->assertSame(['terlambat', '2026-10-01', 'major'], [$o['status'], $o['due']->toDateString(), $o['job']]);   // servis besar sudah lewat: itu yang menentukan

        $vendor = $this->acVendor();
        $order = $this->acOrder($vendor, [$u], '2026-11-03');
        AcVisits::send($order, $this->admin);
        AcVisits::confirm($order, $this->admin);
        Carbon::setTestNow('2026-11-03 10:00:00');   // hari kunjungan
        AcVisits::complete($order, '2026-11-03', $this->admin);
        $o = $this->due($u);
        $this->assertSame('2026-11-03', $o['last_major']->toDateString());
        $this->assertSame('2026-11-03', $o['last_wash']->toDateString(), 'servis besar mencakup cuci');
        $this->assertSame(['aman', '2027-02-03', '2027-11-03'], [$o['status'], $o['next_wash']->toDateString(), $o['next_major']->toDateString()]);
    }

    public function test_the_job_is_a_major_service_when_it_falls_close_to_the_wash(): void
    {
        $near = $this->due($this->acUnit(null, null, ['last_wash_at' => '2026-08-02', 'last_major_at' => '2025-11-20']));   // cuci jatuh tempo hari ini, servis besar 18-11-2026 (16 hari lagi)
        $this->assertSame('major', $near['job']);
        $far = $this->due($this->acUnit(null, null, ['last_wash_at' => '2026-08-02', 'last_major_at' => '2026-03-01']));   // servis besar masih 4 bulan lagi
        $this->assertSame('wash', $far['job']);
    }

    public function test_without_any_history_the_install_date_and_then_the_registration_date_are_the_base(): void
    {
        $installed = $this->due($this->acUnit(null, null, ['last_wash_at' => null, 'last_major_at' => null, 'installed_at' => '2026-06-01']));
        $this->assertSame(['terlambat', '2026-09-01'], [$installed['status'], $installed['next_wash']->toDateString()]);
        $fresh = $this->due($this->acUnit(null, null, ['last_wash_at' => null, 'last_major_at' => null, 'installed_at' => null]));
        $this->assertSame(['aman', '2027-02-02'], [$fresh['status'], $fresh['next_wash']->toDateString()]);   // didaftarkan hari ini
    }

    public function test_open_orders_are_reported_per_unit_and_only_done_lines_move_the_due_date(): void
    {
        $u = $this->acLate();
        $this->assertNull($this->due($u)['open']);
        $order = $this->acOrder($this->acVendor(), [$u], '2026-11-04');
        $open = $this->due($u)['open'];
        $this->assertSame([$order->no, '2026-11-04', 'draft'], [$open['no'], $open['date']->toDateString(), $open['status']]);
        $this->assertSame('terlambat', $this->due($u)['status'], 'belum selesai: jatuh tempo tidak bergeser');
    }

    public function test_scheduling_is_refused_in_the_past_on_a_closed_day_and_outside_the_hours(): void
    {
        $vendor = $this->acVendor();
        $u = $this->acLate();
        $this->fails(fn () => AcCare::schedule($vendor, '2026-11-01', null, [$u->id], $this->admin), 'date', 'tidak boleh di masa lalu');
        $this->fails(fn () => AcCare::schedule($vendor, '2026-11-07', null, [$u->id], $this->admin), 'date', 'Hari Sabtu tidak diizinkan');
        $this->fails(fn () => AcCare::schedule($vendor, '2026-11-08', null, [$u->id], $this->admin), 'date', 'Senin, Selasa, Rabu, Kamis, Jumat');
        $this->fails(fn () => AcCare::schedule($vendor, '2026-11-03', '08:59', [$u->id], $this->admin), 'time', 'antara 09.00 dan 16.00');
        $this->fails(fn () => AcCare::schedule($vendor, '2026-11-03', '16:01', [$u->id], $this->admin), 'time', 'antara 09.00 dan 16.00');
        $this->fails(fn () => AcCare::schedule($vendor, 'bukan-tanggal', null, [$u->id], $this->admin), 'date', 'tidak valid');
        $this->fails(fn () => AcCare::schedule($vendor, '2026-11-03', null, [], $this->admin), 'units', 'Pilih minimal satu AC');
        $this->assertSame(0, AcOrder::count());

        $this->assertSame('09:00', AcCare::schedule($vendor, '2026-11-03', null, [$u->id], $this->admin)->timeText());   // jam bawaan
        $this->assertSame('2026-11-02', AcCare::schedule($vendor, '2026-11-02', '16:00', [$this->acLate()->id], $this->admin)->visit_date->toDateString(), 'hari ini, jam batas akhir: sah');
    }

    public function test_the_allowed_days_and_hours_can_be_changed_in_settings(): void
    {
        $vendor = $this->acVendor();
        Constants::set('ga.ac.days', '1,6', 'ga');
        Constants::set('ga.ac.start_time', '08:00', 'ga');
        Constants::set('ga.ac.end_time', '12:00', 'ga');
        $this->assertSame('2026-11-07', AcCare::schedule($vendor, '2026-11-07', null, [$this->acLate()->id], $this->admin)->visit_date->toDateString());   // Sabtu kini boleh
        $this->fails(fn () => AcCare::schedule($vendor, '2026-11-03', null, [$this->acLate()->id], $this->admin), 'date', 'Hari Selasa tidak diizinkan');
        $this->fails(fn () => AcCare::schedule($vendor, '2026-11-09', '13:00', [$this->acLate()->id], $this->admin), 'time', 'antara 08.00 dan 12.00');
        Constants::set('ga.ac.days', 'xx', 'ga');
        $this->assertSame([1, 2, 3, 4, 5], AcCare::visitDays(), 'isi tidak sah = bawaan Senin sampai Jumat');
    }

    public function test_daily_capacity_is_enforced_per_vendor_with_a_clear_message(): void
    {
        $vendor = $this->acVendor();
        $other = $this->acVendor('CV Dingin Contoh', ['phone' => '0812-9999-0000']);
        [$a, $b, $c, $d] = [$this->acLate(), $this->acLate(), $this->acLate(), $this->acLate()];

        AcCare::schedule($vendor, '2026-11-03', null, [$a->id, $b->id], $this->admin);
        $this->fails(fn () => AcCare::schedule($vendor, '2026-11-03', null, [$c->id, $d->id], $this->admin), 'date', 'hanya muat 1 AC lagi');
        $this->assertSame(2, AcCare::used($vendor->id, '2026-11-03'));

        $this->assertSame(3, AcCare::schedule($vendor, '2026-11-03', null, [$c->id], $this->admin)->lines()->count());   // masuk ke pesanan Draf yang sama
        $this->fails(fn () => AcCare::schedule($vendor, '2026-11-03', null, [$d->id], $this->admin), 'date', 'sudah penuh');
        $this->assertSame(1, AcOrder::count(), 'AC ditambahkan ke pesanan Draf vendor itu pada tanggal yang sama');

        AcCare::schedule($other, '2026-11-03', null, [$d->id], $this->admin);   // kapasitas dihitung per vendor
        $this->assertSame([3, 1], [AcCare::used($vendor->id, '2026-11-03'), AcCare::used($other->id, '2026-11-03')]);

        Constants::set('ga.ac.capacity', 2, 'ga');   // pemilih kapasitas 2 AC
        $e = $this->acLate();
        $this->fails(fn () => AcCare::schedule($vendor, '2026-11-04', null, [$e->id, $this->acLate()->id, $this->acLate()->id], $this->admin), 'date', 'kapasitas 2 AC');
    }

    public function test_cancelling_frees_the_capacity_and_the_units(): void
    {
        $vendor = $this->acVendor();
        $units = [$this->acLate(), $this->acLate(), $this->acLate()];
        $order = $this->acOrder($vendor, $units, '2026-11-03');
        $x = $this->acLate();
        $this->fails(fn () => AcCare::schedule($vendor, '2026-11-03', null, [$x->id], $this->admin), 'date', 'sudah penuh');
        AcVisits::cancel($order, 'Vendor berhalangan', $this->admin);
        $this->assertSame(0, AcCare::used($vendor->id, '2026-11-03'));
        $this->assertSame(1, AcCare::schedule($vendor, '2026-11-03', null, [$units[0]->id], $this->admin)->lines()->count());
    }

    public function test_a_unit_cannot_have_two_open_orders_and_inactive_units_are_refused(): void
    {
        $vendor = $this->acVendor();
        $u = $this->acLate();
        $first = $this->acOrder($vendor, [$u], '2026-11-03');
        $this->fails(fn () => AcCare::schedule($vendor, '2026-11-04', null, [$u->id], $this->admin), 'units', $u->fresh()->load('room')->label().' sudah terjadwal pada 03-11-2026 ('.$first->no.')');
        $this->assertSame(1, AcOrderLine::count());

        $off = $this->acLate(null, ['is_active' => false]);
        $this->fails(fn () => AcCare::schedule($vendor, '2026-11-04', null, [$off->id], $this->admin), 'units', 'tidak ditemukan atau sudah nonaktif');
        $this->fails(fn () => AcCare::schedule($vendor, '2026-11-04', null, [99999], $this->admin), 'units', 'tidak ditemukan');

        $inactiveVendor = $this->acVendor('CV Mati', ['is_active' => false]);
        $this->fails(fn () => AcCare::schedule($inactiveVendor, '2026-11-04', null, [$this->acLate()->id], $this->admin), 'vendor_id', 'tidak aktif');

        AcVisits::cancel($first, 'Salah jadwal', $this->admin);   // setelah dibatalkan AC boleh dijadwalkan lagi
        $this->assertSame('2026-11-04', AcCare::schedule($vendor, '2026-11-04', null, [$u->id], $this->admin)->visit_date->toDateString());
    }

    public function test_order_numbers_run_per_month_and_the_job_follows_the_visit_date(): void
    {
        $vendor = $this->acVendor();
        $o1 = $this->acOrder($vendor, [$this->acUnit(null, null, ['last_wash_at' => '2026-08-20', 'last_major_at' => '2025-12-10'])], '2026-11-03');
        $o2 = $this->acOrder($vendor, [$this->acUnit(null, null, ['last_wash_at' => '2026-08-20', 'last_major_at' => '2025-12-10'])], '2026-11-12');
        $this->assertSame(['ACV-2611-0001', 'ACV-2611-0002'], [$o1->no, $o2->no]);
        // Servis besar jatuh tempo 10-12-2026: dari kunjungan 03-11 masih lebih dari 30 hari (cuci), dari kunjungan 12-11 sudah berdekatan (servis besar).
        $this->assertSame(['wash', 'major'], [$o1->lines->first()->job, $o2->lines->first()->job]);
    }

    public function test_auto_plan_fills_day_by_day_most_overdue_first_and_skips_closed_days(): void
    {
        $vendor = $this->acVendor();
        Constants::set('ga.ac.capacity', 2, 'ga');
        $units = [];
        foreach ([40, 5, 20, 90, 10] as $over) {   // hari lewat jatuh tempo (cuci terakhir 3 bulan + $over hari lalu)
            $units[$over] = $this->acUnit(null, null, ['last_wash_at' => today()->subMonths(3)->subDays($over)]);
        }
        $safe = $this->acUnit(null, 10);   // aman: tidak ikut
        $booked = $this->acLate();
        $this->acOrder($vendor, [$booked], '2026-11-03');   // sudah terjadwal: tidak ikut, tapi memakai 1 slot hari itu

        $result = AcCare::autoPlan($vendor, '2026-11-02', $this->admin);

        $this->assertSame(5, $result['units']);
        $plan = collect($result['orders'])->mapWithKeys(fn ($o) => [$o->visit_date->toDateString() => $o->fresh()->lines()->orderBy('id')->pluck('unit_id')->all()])->all();
        // Senin 02: dua paling terlambat (90, 40); Selasa 03: tinggal 1 slot (sudah ada 1 AC); Rabu 04: dua berikutnya.
        $this->assertSame([$units[90]->id, $units[40]->id], $plan['2026-11-02']);
        $this->assertSame(2, AcCare::used($vendor->id, '2026-11-03'));
        $this->assertEqualsCanonicalizing([$booked->id, $units[20]->id], $plan['2026-11-03']);
        $this->assertSame([$units[10]->id, $units[5]->id], $plan['2026-11-04']);
        $this->assertNotContains($safe->id, collect($plan)->flatten()->all());

        $again = AcCare::autoPlan($vendor, '2026-11-02', $this->admin);
        $this->assertSame(0, $again['units'], 'dijalankan ulang: semua yang perlu sudah terjadwal');
        $this->assertSame([], $again['orders']);
    }

    public function test_auto_plan_skips_the_weekend_and_never_starts_in_the_past(): void
    {
        $vendor = $this->acVendor();
        Constants::set('ga.ac.capacity', 1, 'ga');
        $this->acLate();
        $this->acLate();
        $result = AcCare::autoPlan($vendor, '2026-11-06', $this->admin);   // Jumat, lalu Sabtu dan Minggu dilewati
        $this->assertSame(['2026-11-06', '2026-11-09'], collect($result['orders'])->map(fn ($o) => $o->visit_date->toDateString())->all());

        $old = AcCare::autoPlan($vendor, '2020-01-01', $this->admin);   // masa lalu: mulai hari ini; tidak ada yang perlu lagi
        $this->assertSame(0, $old['units']);
        $this->acLate();
        $this->assertSame('2026-11-02', AcCare::autoPlan($vendor, '2020-01-01', $this->admin)['orders'][0]->visit_date->toDateString());
    }

    public function test_the_calendar_load_counts_lines_per_day_without_cancelled_orders(): void
    {
        $vendor = $this->acVendor();
        $this->acOrder($vendor, [$this->acLate(), $this->acLate()], '2026-11-03');
        $this->acOrder($vendor, [$this->acLate()], '2026-11-05');
        AcVisits::cancel($this->acOrder($vendor, [$this->acLate()], '2026-11-06'), 'Batal', $this->admin);
        $this->assertSame(['2026-11-03' => 2, '2026-11-05' => 1], AcCare::load($vendor->id, Carbon::parse('2026-11-02'), Carbon::parse('2026-11-07')));
    }

    public function test_a_unit_with_an_open_order_cannot_be_deactivated(): void
    {
        $u = $this->acLate();
        $order = $this->acOrder($this->acVendor(), [$u], '2026-11-03');
        $this->actingAs($this->admin);

        $this->postJson(route('ac-units.toggle', $u))->assertStatus(422)->assertJsonPath('message', 'AC ini masih terjadwal pada 03-11-2026 ('.$order->no.'). Batalkan atau selesaikan kunjungannya dulu.');
        $this->put(route('ac-units.update', $u), ['_action' => 'deactivate'])->assertSessionHasErrors('brand');
        $this->assertTrue($u->fresh()->is_active);

        AcVisits::cancel($order, 'Batal', $this->admin);
        $this->postJson(route('ac-units.toggle', $u))->assertOk()->assertJson(['active' => false]);
    }

    public function test_the_codes_are_derived_from_names(): void
    {
        $this->assertSame('RM', AcCare::codeFrom('Ruang Meeting'));
        $this->assertSame('KL1', AcCare::codeFrom('Kantor Lt. 1'));
        $this->assertSame('GUD', AcCare::codeFrom('Gudang'));
        $this->assertSame('X', AcCare::codeFrom('---'));
    }
}
