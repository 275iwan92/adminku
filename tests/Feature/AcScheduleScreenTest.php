<?php

namespace Tests\Feature;

use App\Models\AcOrder;
use App\Models\User;
use App\Services\AcCare;
use App\Support\Constants;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AcFixtures;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga layar Penjadwalan AC: kotak ringkasan, kalender minggu dengan isi / kapasitas dan hari penuh, daftar AC paling terlambat dulu, Jadwalkan yang dipilih (menolak melebihi kapasitas di server),
 *                Susun otomatis, pemilih kapasitas, hak Grup (hanya-baca tidak melihat tombol dan ditolak menulis), masukan buruk tidak 500, dan jumlah kueri tidak naik menurut jumlah AC. Hari ini dibekukan 2026-11-02 (Senin).
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class AcScheduleScreenTest extends TestCase
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

    public function test_the_screen_shows_the_summary_the_week_and_the_most_overdue_unit_first(): void
    {
        $vendor = $this->acVendor();
        $late = $this->acUnit(null, null, ['last_wash_at' => '2026-06-01']);   // jatuh tempo 01-09-2026: lewat 62 hari
        $less = $this->acUnit(null, null, ['last_wash_at' => '2026-07-15']);   // lewat 19 hari
        $soon = $this->acUnit(null, null, ['last_wash_at' => '2026-08-20']);   // 20-11-2026: 18 hari lagi
        $safe = $this->acUnit(null, 5);
        $this->acOrder($vendor, [$this->acLate()], '2026-11-03', $this->admin);   // terjadwal minggu ini (juga terlambat tapi terjadwal)

        $res = $this->actingAs($this->admin)->get(route('ac-schedule.index'));
        $res->assertOk()->assertSee('Penjadwalan AC')->assertSee('Terlambat dicuci')->assertSee('Terjadwal minggu ini')->assertSee('Total AC');
        $res->assertSeeInOrder([$late->asset_code, $less->asset_code, $soon->asset_code]);
        $res->assertDontSee($safe->asset_code);   // Aman: tidak perlu dibersihkan
        $res->assertSee('lewat 62 hari')->assertSee('18 hari lagi')->assertSee('Cuci rutin');
        $res->assertSee('Sen 02-11')->assertSee('Sab 07-11')->assertSee('0/3')->assertSee('1/3')->assertSee('Bukan hari kunjungan', false);   // Sabtu tutup

        $all = $this->get(route('ac-schedule.index', ['show' => 'semua']))->assertOk();
        $all->assertSee($safe->asset_code);
        $this->get(route('ac-schedule.index', ['area' => 99999]))->assertOk()->assertSee('Tidak ada AC yang perlu dibersihkan');
    }

    public function test_the_default_date_is_the_first_open_day_with_room_and_a_full_day_is_marked(): void
    {
        $vendor = $this->acVendor();
        Constants::set('ga.ac.capacity', 2, 'ga');
        $this->acOrder($vendor, [$this->acLate(), $this->acLate()], '2026-11-02', $this->admin);   // Senin penuh

        $res = $this->actingAs($this->admin)->get(route('ac-schedule.index'))->assertOk();
        $res->assertSee('is-full', false)->assertSee('Penuh');
        $res->assertSee('name="date" value="2026-11-03"', false);   // Selasa: hari pertama dengan slot kosong
        $res->assertSee('Sisa kapasitas hari itu')->assertSee('2 dari 2 AC');

        $this->get(route('ac-schedule.index', ['date' => '2026-11-04', 'week' => '2026-11-02']))->assertOk()->assertSee('name="date" value="2026-11-04"', false)->assertSee('Rabu, 04-11-2026');
        $this->get(route('ac-schedule.index', ['week' => '2026-11-09']))->assertOk()->assertSee('Sen 09-11')->assertSee('name="date" value="2026-11-09"', false);
    }

    public function test_scheduling_the_selected_units_creates_a_draft_and_refuses_beyond_the_capacity_on_the_server(): void
    {
        $vendor = $this->acVendor();
        [$a, $b, $c, $d] = [$this->acLate(), $this->acLate(), $this->acLate(), $this->acLate()];
        $this->actingAs($this->admin);

        $this->post(route('ac-schedule.store'), ['vendor_id' => $vendor->id, 'date' => '2026-11-03', 'units' => [$a->id, $b->id]])
            ->assertRedirect(route('ac-schedule.index', ['vendor' => $vendor->id, 'date' => '2026-11-03']))
            ->assertSessionHas('status', '2 AC dijadwalkan (Preventif) pada Selasa, 03-11-2026 (pesanan ACV-2611-0001, Draf). Kirim pemberitahuan dari Kunjungan AC.');
        $this->assertSame(['draft', 2], [AcOrder::first()->status, AcOrder::first()->lines()->count()]);

        // Layar tidak bisa dipakai menembus kapasitas: server menolak walau tombolnya ditekan.
        $this->post(route('ac-schedule.store'), ['vendor_id' => $vendor->id, 'date' => '2026-11-03', 'units' => [$c->id, $d->id]])
            ->assertSessionHasErrors(['date' => 'Tanggal 03-11-2026 hanya muat 1 AC lagi (kapasitas 3 AC per hari), Anda memilih 2 AC.']);
        $this->assertSame(2, AcCare::used($vendor->id, '2026-11-03'));
        $this->post(route('ac-schedule.store'), ['vendor_id' => $vendor->id, 'date' => '2026-11-03', 'units' => [$a->id]])->assertSessionHasErrors('units');   // sudah terjadwal
        $this->post(route('ac-schedule.store'), ['vendor_id' => $vendor->id, 'date' => '2026-11-07', 'units' => [$c->id]])->assertSessionHasErrors('date');   // Sabtu
        $this->post(route('ac-schedule.store'), ['vendor_id' => $vendor->id, 'date' => '2026-11-03', 'units' => []])->assertSessionHasErrors(['units' => 'Pilih minimal satu AC yang akan dijadwalkan.']);
        $this->post(route('ac-schedule.store'), ['vendor_id' => $vendor->id, 'units' => [$c->id]])->assertSessionHasErrors(['date' => 'Pilih tanggal kunjungan di kalender.']);
        $this->post(route('ac-schedule.store'), ['vendor_id' => 99999, 'date' => '2026-11-04', 'units' => [$c->id]])->assertSessionHasErrors('vendor_id');
        $this->assertSame(1, AcOrder::count());

        $this->get(route('ac-schedule.index', ['date' => '2026-11-03']))->assertSee('ACV-2611-0001');   // kolom Jadwal menampilkan nomor pesanan
    }

    public function test_auto_schedule_fills_the_days_and_reports_the_result(): void
    {
        $vendor = $this->acVendor();
        Constants::set('ga.ac.capacity', 2, 'ga');
        foreach (range(1, 5) as $i) {
            $this->acLate();
        }
        $this->actingAs($this->admin);

        $this->post(route('ac-schedule.auto'), ['vendor_id' => $vendor->id, 'date' => '2026-11-02'])
            ->assertSessionHas('status', 'Disusun otomatis: 5 AC dalam 3 kunjungan (02-11-2026 sampai 04-11-2026), status Draf. Kirim pemberitahuan dari Kunjungan AC.');
        $this->assertSame([2, 2, 1], AcOrder::orderBy('visit_date')->get()->map(fn ($o) => $o->lines()->count())->all());

        $this->post(route('ac-schedule.auto'), ['vendor_id' => $vendor->id])
            ->assertSessionHas('status', 'Tidak ada AC yang perlu dijadwalkan: semua berstatus Aman atau sudah terjadwal.');
        $this->assertSame(3, AcOrder::count());
    }

    public function test_the_capacity_picker_saves_the_setting_and_rejects_nonsense(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('ac-schedule.capacity'), ['capacity' => 2])->assertSessionHas('status');
        $this->assertSame(2, AcCare::capacity());
        $this->post(route('ac-schedule.capacity'), ['capacity' => 0])->assertSessionHasErrors('capacity');
        $this->post(route('ac-schedule.capacity'), ['capacity' => 'x'])->assertSessionHasErrors('capacity');
        $this->post(route('ac-schedule.capacity'), ['capacity' => ['2']])->assertSessionHasErrors('capacity');
        $this->assertSame(2, AcCare::capacity());
    }

    public function test_a_read_only_group_sees_the_screen_without_the_buttons_and_cannot_write(): void
    {
        $vendor = $this->acVendor();
        $u = $this->acLate();
        $ro = $this->acUser(['GAS' => 'ro']);

        $this->actingAs($ro)->get(route('ac-schedule.index'))->assertOk()->assertSee($u->asset_code)->assertDontSee('Jadwalkan yang dipilih')->assertDontSee('Susun otomatis')->assertDontSee('Simpan kapasitas');
        $this->post(route('ac-schedule.store'), ['vendor_id' => $vendor->id, 'date' => '2026-11-03', 'units' => [$u->id]])->assertForbidden();
        $this->post(route('ac-schedule.auto'), ['vendor_id' => $vendor->id])->assertForbidden();
        $this->post(route('ac-schedule.capacity'), ['capacity' => 2])->assertForbidden();

        $rw = $this->acUser(['GAS' => 'rw']);
        $this->actingAs($rw)->get(route('ac-schedule.index'))->assertOk()->assertSee('Jadwalkan yang dipilih')->assertSee('Susun otomatis');
        $this->actingAs($this->acUser(['GAK' => 'rw']))->get(route('ac-schedule.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('ac-schedule.index'))->assertForbidden();
    }

    public function test_without_a_vendor_the_screen_asks_for_one(): void
    {
        $this->actingAs($this->admin)->get(route('ac-schedule.index'))->assertOk()->assertSee('Belum ada vendor AC aktif');
    }

    public function test_bad_query_values_never_break_the_screen(): void
    {
        $this->acVendor();
        $this->actingAs($this->admin);
        foreach (['date=zzz', 'week=2026-13-45', 'vendor=abc', 'area=-1', 'show[]=1', 'date[]=x&week[]=y', 'date=2026-02-30', 'week=1900-01-01', 'date=9999-12-31'] as $q) {
            $this->get(route('ac-schedule.index').'?'.$q)->assertOk();
        }
    }

    public function test_the_number_of_queries_does_not_grow_with_the_number_of_units(): void
    {
        $this->acVendor();
        $this->actingAs($this->admin);
        $measure = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('ac-schedule.index', ['show' => 'semua']))->assertOk();

            return count(DB::getQueryLog());
        };
        foreach (range(1, 3) as $i) {
            $this->acLate();
        }
        $this->get(route('ac-schedule.index'));   // pemanasan: view dikompilasi
        $few = $measure();

        foreach (range(1, 25) as $i) {
            $this->acLate($this->acRoom(null, 'Ruang '.$i));
        }
        $many = $measure();

        $this->assertSame($few, $many, 'jumlah kueri tidak boleh naik menurut jumlah AC');
        $this->assertLessThan(30, $many);
    }
}
