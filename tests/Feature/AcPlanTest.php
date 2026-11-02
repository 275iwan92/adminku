<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AcVisits;
use App\Support\Constants;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AcFixtures;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga layar Denah AC: pilih area, denah skematis dari persegi ruang atau gambar unggahan (perbandingan ukuran, tanpa persegi), titik AC pada posisi persen (atau tengah ruang bila kosong) berwarna
 *                menurut status, kartu detail saat titik diklik (kode ruang · kode aset, cuci/servis, jadwal, riwayat dan foto), tombol Jadwalkan yang mencentang AC itu di Penjadwalan, gambar denah privat, hak Grup, dan jumlah kueri tetap.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class AcPlanTest extends TestCase
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

    public function test_the_schematic_plan_draws_the_rooms_and_the_dots_in_their_status_colours(): void
    {
        $room = $this->acRoom(null, 'Ruang Meeting', ['x' => 10, 'y' => 20, 'w' => 30, 'h' => 20]);
        $late = $this->acLate($room, ['pos_x' => 25, 'pos_y' => 40]);
        $soon = $this->acUnit($room, null, ['last_wash_at' => '2026-08-10', 'pos_x' => 30, 'pos_y' => 35]);
        $safe = $this->acUnit($room, 5);   // tanpa posisi: tengah ruang (10 + 15, 20 + 10)

        $res = $this->actingAs($this->admin)->get(route('ac-plan.index'))->assertOk();
        $res->assertSee('Denah AC')->assertSee('Denah skematis (asumsi)')->assertSee('AC di area ini');
        $res->assertSee('left: 10%; top: 20%; width: 30%; height: 20%', false)->assertSee('Ruang Meeting');
        $res->assertSee('dl-floorplan__pin is-danger', false)->assertSee('style="left: 25%; top: 40%"', false);
        $res->assertSee('dl-floorplan__pin is-warn', false)->assertSee('style="left: 30%; top: 35%"', false);
        $res->assertSee('dl-floorplan__pin is-success', false)->assertSee('style="left: 25%; top: 30%"', false);
        $res->assertSee('aspect-ratio: 100 / 60', false);
        $res->assertSee('href="'.e(route('ac-plan.index', ['area' => $room->area_id, 'unit' => $late->id])).'"', false)->assertSee($safe->asset_code);
        $res->assertSee('Klik sebuah titik AC pada denah');
        $this->assertNotNull($soon);
    }

    public function test_the_plan_is_the_devlib_floor_plan_with_numbered_coloured_pins_legend_and_the_selected_card(): void
    {
        $room = $this->acRoom(null, 'Ruang Meeting');
        $late = $this->acLate($room);
        $safe = $this->acUnit($room, 5);

        $res = $this->actingAs($this->admin)->get(route('ac-plan.index'))->assertOk();
        $res->assertSee('data-dl-floorplan', false)->assertSee('class="dl-floorplan__plan"', false)->assertSee('dl-floorplan__legend', false)->assertSee('dl-floorplan__summary', false);
        $res->assertSee('>'.substr($late->asset_code, -2).'</a>', false);
        $this->assertMatchesRegularExpression('/aria-label="[^"]*'.preg_quote($safe->asset_code, '/').'[^"]*, Aman"/u', $res->getContent());   // raw() menyisipkan penanda tak terlihat
        $this->assertMatchesRegularExpression('/aria-label="[^"]*'.preg_quote($late->asset_code, '/').'[^"]*, Terlambat"/u', $res->getContent());
        $res->assertDontSee('is-selected', false)->assertDontSee('Kartu AC ·');

        $res = $this->get(route('ac-plan.index', ['unit' => $late->id]))->assertOk();
        $this->assertSame(1, substr_count($res->getContent(), 'dl-floorplan__pin is-danger is-selected'));
        $res->assertSee('data-selected="'.$late->id.'"', false)->assertSee('aria-current="true"', false)->assertSee('Kartu AC · ', false)->assertSee($late->asset_code);
        $this->assertStringContainsString('dl-floorplan__detail', $res->getContent());
    }

    public function test_the_old_plan_css_is_gone(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringNotContainsString('sstock-plan', $css);
        foreach (glob(resource_path('views/ac-plan/*.php')) as $view) {
            $this->assertStringNotContainsString('sstock-plan', file_get_contents($view));
        }
    }

    public function test_clicking_a_dot_opens_the_card_with_codes_dates_schedule_and_history(): void
    {
        Constants::set('ga.ac.capacity', 3, 'ga');
        $room = $this->acRoom(null, 'Ruang Meeting', ['pic_name' => 'Budi', 'pic_phone' => '0812-3456-7001']);
        $u = $this->acUnit($room, null, ['last_wash_at' => '2026-07-01', 'last_major_at' => '2026-01-15', 'brand' => 'Panasonic', 'model' => 'CS-YN12', 'capacity_pk' => 1.5]);

        $card = $this->actingAs($this->admin)->get(route('ac-plan.index', ['unit' => $u->id]))->assertOk();
        $card->assertSee('Kartu AC · RM · '.$u->asset_code, false)->assertSee('Panasonic CS-YN12 1,5 PK')->assertSee('Kantor Lt. 1')->assertSee('Budi');
        $card->assertSee('01-07-2026')->assertSee('01-10-2026')->assertSee('lewat 32 hari')   // cuci terakhir, berikutnya
            ->assertSee('15-01-2027')   // servis besar berikutnya
            ->assertSee('Belum terjadwal')->assertSee('Jadwalkan')->assertSee('Terlambat');
        $card->assertSee('Belum ada pekerjaan selesai tercatat');
        $card->assertSee(e(route('ac-schedule.index', ['pick' => $u->id, 'show' => 'semua'])), false);

        // Terjadwal: kartu menunjuk kunjungannya, tombol Jadwalkan hilang.
        $order = $this->acOrder($this->acVendor(), [$u], '2026-11-03', $this->admin);
        $this->get(route('ac-plan.index', ['unit' => $u->id]))->assertSee($order->no)->assertSee('03-11-2026')->assertSee('Buka kunjungan')->assertDontSee('label="Jadwalkan"', false);

        // Selesai dengan foto: masuk riwayat dengan tautan foto.
        AcVisits::send($order, $this->admin);
        AcVisits::confirm($order, $this->admin);
        AcVisits::attachPhoto($order->lines()->first(), 'before', UploadedFile::fake()->image('sebelum.jpg'));
        Carbon::setTestNow('2026-11-03 12:00:00');
        AcVisits::complete($order, '2026-11-03', $this->admin);
        $res = $this->get(route('ac-plan.index', ['unit' => $u->id]))->assertOk();
        $res->assertSee('Riwayat dan foto')->assertSee('03-11-2026')->assertSee('sebelum.jpg')->assertSee('Cuci rutin berikutnya')->assertSee('03-02-2027');
        $res->assertDontSee('Belum ada pekerjaan selesai tercatat');
    }

    public function test_an_uploaded_plan_replaces_the_schematic_and_keeps_its_aspect_ratio(): void
    {
        $area = $this->acArea('Gudang');
        $this->actingAs($this->admin)->post(route('ac-areas.update', $area), ['_method' => 'PUT', 'name' => 'Gudang', 'code' => $area->code, 'plan' => UploadedFile::fake()->image('denah.png', 1200, 600)])->assertRedirect();
        $room = $this->acRoom($area, 'Kantor Gudang');
        $this->acLate($room, ['pos_x' => 60, 'pos_y' => 45]);

        $res = $this->get(route('ac-plan.index', ['area' => $area->id]))->assertOk();
        $res->assertSee('aspect-ratio: 1200 / 600', false)->assertSee('src="'.route('ac-plan.image', $area).'"', false)->assertSee('Denah gambar unggahan');
        $res->assertDontSee('dl-floorplan__room', false)->assertSee('style="left: 60%; top: 45%"', false);
        $this->get(route('ac-plan.image', $area))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('ac-plan.image', $this->acArea('Workshop')))->assertNotFound();   // tanpa gambar
    }

    public function test_the_area_picker_switches_the_plan_and_unknown_areas_fall_back_to_the_first(): void
    {
        $a = $this->acArea('Kantor Lt. 1');
        $b = $this->acArea('Workshop');
        $this->acLate($this->acRoom($a, 'Ruang A'));
        $inB = $this->acLate($this->acRoom($b, 'Ruang B'));
        $this->actingAs($this->admin);

        $this->get(route('ac-plan.index', ['area' => $b->id]))->assertOk()->assertSee($inB->asset_code)->assertSee('Ruang B');
        $this->get(route('ac-plan.index', ['area' => 99999]))->assertOk()->assertSee('Ruang A')->assertDontSee('Ruang B');
        $this->get(route('ac-plan.index', ['area' => $a->id, 'unit' => $inB->id]))->assertOk()->assertDontSee('Kartu AC')->assertSee('Klik sebuah titik');   // AC area lain: tidak dibuka
        $this->get(route('ac-plan.index', ['unit' => $inB->id]))->assertOk()->assertSee('Kartu AC · RB · '.$inB->asset_code, false)->assertSee('Ruang B');   // tanpa area: area milik AC itu
        foreach (['area=abc', 'unit=-5', 'area[]=1', 'unit=99999999999999999999'] as $q) {
            $this->get(route('ac-plan.index').'?'.$q)->assertOk();
        }
    }

    public function test_without_any_area_the_screen_says_so_and_inactive_units_have_no_dot(): void
    {
        $this->actingAs($this->admin)->get(route('ac-plan.index'))->assertOk()->assertSee('Belum ada area');
        $room = $this->acRoom();
        $off = $this->acLate($room, ['is_active' => false]);
        $this->get(route('ac-plan.index'))->assertOk()->assertDontSee($off->asset_code);
    }

    public function test_the_schedule_button_checks_that_unit_in_the_schedule_screen(): void
    {
        $this->acVendor();
        $u = $this->acUnit(null, 5);   // Aman: biasanya tidak tampil di daftar
        $this->actingAs($this->admin)->get(route('ac-schedule.index'))->assertDontSee($u->asset_code);
        $res = $this->get(route('ac-schedule.index', ['pick' => $u->id]))->assertOk()->assertSee($u->asset_code);
        $this->assertMatchesRegularExpression('/<input type="checkbox" name="units\[\]" value="'.$u->id.'"[^>]* checked/', $res->getContent());
    }

    public function test_group_rights_decide_who_sees_the_plan_the_image_and_the_schedule_button(): void
    {
        $area = $this->acArea();
        $u = $this->acLate($this->acRoom($area));
        $this->actingAs($this->admin)->post(route('ac-areas.update', $area), ['_method' => 'PUT', 'name' => $area->name, 'code' => $area->code, 'plan' => UploadedFile::fake()->image('d.png', 400, 300)]);

        $ro = $this->acUser(['GAD' => 'ro']);
        $this->actingAs($ro)->get(route('ac-plan.index', ['unit' => $u->id]))->assertOk()->assertDontSee('Jadwalkan')->assertDontSee(route('ac-units.show', $u), false)->assertDontSee('Buka kunjungan');
        $this->get(route('ac-plan.image', $area))->assertOk();
        $this->actingAs($this->acUser(['GAD' => 'ro', 'GAS' => 'rw']))->get(route('ac-plan.index', ['unit' => $u->id]))->assertSee('Jadwalkan');
        $this->actingAs($this->acUser(['GAS' => 'rw']))->get(route('ac-plan.index'))->assertForbidden();
        $this->get(route('ac-plan.image', $area))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('ac-plan.index'))->assertForbidden();
    }

    public function test_the_plan_stays_under_the_query_budget_whatever_the_number_of_units(): void
    {
        $this->actingAs($this->admin);
        $room = $this->acRoom();
        $this->acLate($room);
        $measure = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('ac-plan.index'))->assertOk();

            return count(DB::getQueryLog());
        };
        $this->get(route('ac-plan.index'));   // pemanasan
        $few = $measure();
        foreach (range(1, 30) as $i) {
            $this->acLate($this->acRoom($room->area, 'Ruang '.$i));
        }
        $many = $measure();
        $this->assertSame($few, $many);
        $this->assertLessThan(30, $many);
    }
}
