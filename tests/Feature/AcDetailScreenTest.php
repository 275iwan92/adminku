<?php

namespace Tests\Feature;

use App\Models\AcOrderLine;
use App\Models\AcUnit;
use App\Models\User;
use App\Services\AcVisits;
use App\Support\Constants;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AcFixtures;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga layar Detail AC (ac-care.show): dibuka dari baris Monitoring, memuat status, jadwal, riwayat perawatan (temuan, jasa, foto), spare part dan biaya 12 bulan, tombol mengikuti hak Grup,
 *                dan tautan kunjungan / foto hanya bagi yang berhak GAV. Hari ini dibekukan 2026-11-02.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class AcDetailScreenTest extends TestCase
{
    use AcFixtures, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 08:00:00');
        Constants::forget();
        Storage::fake('ga');
        Mail::fake();
        $this->admin = User::factory()->administrator()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Constants::forget();
        parent::tearDown();
    }

    private function serviced(AcUnit $unit): void
    {
        $order = $this->acOrder($this->acVendor(), [$unit], '2026-11-02', $this->admin);
        AcVisits::send($order, $this->admin);
        AcVisits::confirm($order, $this->admin);
        AcVisits::recordResult($order->lines()->first(), '2026-11-02', ['findings' => 'Filter kotor', 'action' => 'Cuci jet', 'labor_cost' => 150000], [['name' => 'Kapasitor fan', 'qty' => 1, 'cost' => 120000]], $this->admin);
    }

    public function test_the_monitoring_row_links_to_the_detail_screen(): void
    {
        $unit = $this->acLate();

        $this->actingAs($this->admin)->get(route('ac-care.index'))->assertOk()->assertSee('ac-care\/{id}', false);
        $this->actingAs($this->admin)->get(route('ac-care.show', $unit))->assertOk()->assertSee($unit->asset_code)->assertSee('Terlambat')->assertSee('Belum ada perawatan selesai')->assertSee('Jadwalkan Kunjungan')->assertSee('Ubah Data AC');
    }

    public function test_the_detail_shows_history_parts_and_year_cost(): void
    {
        $unit = $this->acLate();
        $this->serviced($unit);

        $this->actingAs($this->admin)->get(route('ac-care.show', $unit))->assertOk()
            ->assertSee('Filter kotor')->assertSee('Cuci jet')->assertSee('Kapasitor fan')->assertSee('IDR 270.000')->assertSee('IDR 120.000');
    }

    public function test_buttons_and_links_follow_group_access(): void
    {
        $unit = $this->acLate();
        $this->serviced($unit);
        $order = AcOrderLine::where('unit_id', $unit->id)->first()->order;

        $viewer = $this->acUser(['GAM' => 'ro']);
        $this->actingAs($viewer)->get(route('ac-care.show', $unit))->assertOk()->assertDontSee('Ubah Data AC')->assertDontSee('Jadwalkan Kunjungan')->assertDontSee(route('ac-visits.show', $order), false);

        $visits = $this->acUser(['GAM' => 'ro', 'GAV' => 'ro']);
        $this->actingAs($visits)->get(route('ac-care.show', $unit))->assertOk()->assertSee(route('ac-visits.show', $order), false);

        $this->actingAs($this->acUser(['GVM' => 'ro']))->get(route('ac-care.show', $unit))->assertForbidden();
    }

    public function test_an_open_visit_hides_the_schedule_button(): void
    {
        $unit = $this->acLate();
        $order = $this->acOrder($this->acVendor(), [$unit], '2026-11-03', $this->admin);

        $this->actingAs($this->admin)->get(route('ac-care.show', $unit))->assertOk()->assertSee($order->no)->assertDontSee('Jadwalkan Kunjungan');
    }
}
