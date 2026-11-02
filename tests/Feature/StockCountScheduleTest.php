<?php

namespace Tests\Feature;

use App\Models\Constant;
use App\Models\StockCount;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\CountSchedule;
use App\Support\Constants;
use App\Support\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga jadwal Stok Opname dan pengingat Beranda: hitungan tanggal berikutnya (mingguan, tanggal, akhir bulan termasuk Februari), status warna (jauh, H-3, hari H, terlambat),
 *                tampil hanya untuk yang punya modul SO, tombol Mulai hanya Supervisor, opname berjalan, belum pernah ada, ubah jadwal hanya Supervisor, lencana menu, tabel sys_constant aman bila kosong.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class StockCountScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $sup;

    private User $entry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sup = User::factory()->supervisor()->create();
        $this->entry = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeCount(string $status, string $date, array $over = []): StockCount
    {
        static $n = 0;

        return StockCount::create($over + ['doc_no' => 'SO-TEST-'.++$n, 'count_date' => $date, 'status' => $status, 'cutoff_at' => now(), 'created_by' => $this->sup->id]);
    }

    private function cfg(string $freq, int $weekday = 1, string $day = 'last'): array
    {
        return ['frequency' => $freq, 'weekday' => $weekday, 'day_of_month' => $day];
    }

    public function test_next_date_monthly_end_of_month_including_february_and_leap_years(): void
    {
        $cfg = $this->cfg('monthly');

        $this->assertSame('2026-01-31', CountSchedule::next(Carbon::parse('2026-01-15'), $cfg)->toDateString());
        $this->assertSame('2026-02-28', CountSchedule::next(Carbon::parse('2026-01-31'), $cfg)->toDateString());   // strictly after
        $this->assertSame('2028-02-29', CountSchedule::next(Carbon::parse('2028-01-31'), $cfg)->toDateString());   // tahun kabisat
        $this->assertSame('2026-12-31', CountSchedule::next(Carbon::parse('2026-12-01'), $cfg)->toDateString());
        $this->assertSame('2027-01-31', CountSchedule::next(Carbon::parse('2026-12-31'), $cfg)->toDateString());
    }

    public function test_next_date_monthly_fixed_day_clamps_to_short_months(): void
    {
        $this->assertSame('2026-01-15', CountSchedule::next(Carbon::parse('2026-01-10'), $this->cfg('monthly', 1, '15'))->toDateString());
        $this->assertSame('2026-02-15', CountSchedule::next(Carbon::parse('2026-01-15'), $this->cfg('monthly', 1, '15'))->toDateString());
        $this->assertSame('2026-02-28', CountSchedule::next(Carbon::parse('2026-02-10'), $this->cfg('monthly', 1, '31'))->toDateString());   // Februari tidak punya tanggal 31
        $this->assertSame('2026-02-28', CountSchedule::next(Carbon::parse('2026-01-31'), $this->cfg('monthly', 1, '31'))->toDateString());
        $this->assertSame('2028-02-29', CountSchedule::next(Carbon::parse('2028-02-01'), $this->cfg('monthly', 1, '30'))->toDateString());
    }

    public function test_next_date_weekly_is_the_next_chosen_weekday_after_the_anchor(): void
    {
        $cfg = $this->cfg('weekly', 1);   // Senin

        $this->assertSame('2026-10-26', CountSchedule::next(Carbon::parse('2026-10-19'), $cfg)->toDateString());   // Senin -> Senin berikutnya
        $this->assertSame('2026-10-26', CountSchedule::next(Carbon::parse('2026-10-25'), $cfg)->toDateString());   // Minggu -> besok
        $this->assertSame('2026-10-23', CountSchedule::next(Carbon::parse('2026-10-22'), $this->cfg('weekly', 5))->toDateString());   // Kamis -> Jumat
    }

    public function test_status_tones_far_three_days_today_and_late(): void
    {
        $this->makeCount('done', '2026-09-30');   // bulanan akhir bulan (bawaan): berikutnya 2026-10-31
        $tone = fn (string $today) => collect(CountSchedule::state(Carbon::parse($today)))->only(['tone', 'detail', 'days_to'])->all();

        $this->assertEquals(['tone' => 'neutral', 'detail' => 'Dijadwalkan 11 hari lagi', 'days_to' => 11], $tone('2026-10-20'));
        $this->assertSame('neutral', $tone('2026-10-27')['tone']);   // H-4 masih biasa
        $this->assertSame('warn', $tone('2026-10-28')['tone']);      // H-3
        $this->assertEquals(['tone' => 'warn', 'detail' => 'Stok Opname dijadwalkan hari ini', 'days_to' => 0], $tone('2026-10-31'));
        $this->assertEquals(['tone' => 'danger', 'detail' => 'Terlambat 2 hari', 'days_to' => -2], $tone('2026-11-02'));
    }

    public function test_next_counts_from_the_last_done_count_and_ignores_cancelled_and_running_ones(): void
    {
        $this->makeCount('done', '2026-08-31');
        $this->makeCount('cancelled', '2026-09-30');
        $state = CountSchedule::state(Carbon::parse('2026-10-10'));

        $this->assertSame(['2026-08-31', '2026-09-30'], [$state['last']->toDateString(), $state['next']->toDateString()]);   // dibatalkan tidak dihitung
        $this->assertSame('Terlambat 10 hari', $state['detail']);
        $this->assertStringContainsString('Stok Opname terakhir: 31-08-2026 (40 hari lalu). Berikutnya: 30-09-2026.', $state['headline']);
    }

    public function test_never_counted_uses_the_schedule_date_and_the_default_schedule(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');
        $default = CountSchedule::state();
        $this->assertSame(['2026-10-31', 'Bulanan, akhir bulan', null], [$default['next']->toDateString(), $default['schedule'], $default['last']]);   // jadwal belum pernah disimpan: bawaan, dari hari ini
        $this->assertStringContainsString('Belum pernah ada Stok Opname. Jadwal: Bulanan, akhir bulan. Berikutnya: 31-10-2026.', $default['headline']);

        CountSchedule::save(['frequency' => 'weekly', 'weekday' => 3]);   // Rabu; jadwal dibuat 2026-10-10 (Sabtu)
        Carbon::setTestNow('2026-10-20 09:00:00');
        $weekly = CountSchedule::state();
        $this->assertSame(['2026-10-14', 'Mingguan, hari Rabu', 'danger'], [$weekly['next']->toDateString(), $weekly['schedule'], $weekly['tone']]);   // dihitung dari tanggal jadwal dibuat
    }

    public function test_constants_helper_is_safe_when_empty_and_round_trips(): void
    {
        $this->assertSame('x', Constants::get('opname.frequency.nothing', 'x'));
        Constants::set('opname.weekday', 4, 'opname');
        $this->assertSame('4', Constants::get('opname.weekday'));
        Constants::set('opname.weekday', 6, 'opname');
        $this->assertSame('6', Constants::get('opname.weekday'));
        $this->assertSame(1, Constant::where('key', 'opname.weekday')->count());   // key unik: ditimpa, bukan ganda
    }

    public function test_home_reminder_shows_for_users_with_the_module_and_the_start_button_only_for_supervisor(): void
    {
        $this->makeCount('done', '2026-09-30');
        Carbon::setTestNow('2026-11-02 08:00:00');

        $this->actingAs($this->entry)->get(route('stock.home'))->assertOk()->assertSee('Stok Opname terakhir: 30-09-2026')->assertSee('Terlambat 2 hari')->assertSee('Buka Stok Opname')->assertDontSee('Mulai Opname');
        $this->actingAs($this->sup)->get(route('stock.home'))->assertOk()->assertSee('Stok Opname terakhir: 30-09-2026')->assertSee('Mulai Opname');

        $group = UserGroup::create(['code' => 'GD', 'name' => 'Gudang']);
        $group->syncAccess(['ISS' => 'rw']);
        $this->actingAs(User::factory()->create(['group_id' => $group->id]))->get(route('stock.home'))->assertOk()->assertDontSee('Stok Opname terakhir');   // tanpa modul SO: tanpa pengingat
    }

    public function test_home_reminder_when_never_counted_and_when_a_count_is_running(): void
    {
        $this->actingAs($this->entry)->get(route('stock.home'))->assertOk()->assertSee('Belum pernah ada Stok Opname')->assertSee('Jadwal: Bulanan, akhir bulan');

        $running = $this->makeCount('counting', today()->toDateString());
        $running->lines()->createMany([]);
        $this->get(route('stock.home'))->assertOk()->assertSee("Stok Opname {$running->doc_no} sedang berjalan")->assertSee('terisi 0 dari 0')->assertDontSee('Belum pernah ada')->assertDontSee('Mulai Opname');
    }

    public function test_only_supervisor_changes_the_schedule(): void
    {
        $this->actingAs($this->entry)->post(route('stock-counts.schedule'), ['frequency' => 'weekly', 'weekday' => 2])->assertForbidden();
        $this->assertNull(Constants::get('opname.frequency'));

        $this->actingAs($this->sup)->post(route('stock-counts.schedule'), ['frequency' => 'weekly', 'weekday' => 2])->assertRedirect(route('stock-counts.index'));
        $this->assertSame(['weekly', '2', 'last'], [Constants::get('opname.frequency'), Constants::get('opname.weekday'), Constants::get('opname.day_of_month')]);
        $this->post(route('stock-counts.schedule'), ['frequency' => 'monthly', 'day_of_month' => '15'])->assertRedirect();
        $this->assertSame('Bulanan, tanggal 15', CountSchedule::describe());
        $this->post(route('stock-counts.schedule'), ['frequency' => 'yearly'])->assertSessionHasErrors('frequency');
        $this->post(route('stock-counts.schedule'), ['frequency' => 'monthly', 'day_of_month' => '40'])->assertSessionHasErrors('day_of_month');

        $this->get(route('stock-counts.index'))->assertOk()->assertSee('Jadwal opname: Bulanan, tanggal 15')->assertSee('Simpan Jadwal');
        $this->actingAs($this->entry)->get(route('stock-counts.index'))->assertOk()->assertSee('Jadwal opname: Bulanan, tanggal 15')->assertDontSee('Simpan Jadwal');
    }

    public function test_menu_badge_marks_a_running_or_late_count(): void
    {
        $so = fn (User $u) => collect(Menu::modules($u))->flatMap(fn ($m) => $m['children'])->firstWhere('code', 'SO');

        $this->makeCount('done', now()->toDateString());   // baru saja: tanpa lencana
        $this->assertArrayNotHasKey('badge', $so($this->entry));

        StockCount::query()->delete();
        $this->makeCount('done', '2026-01-31');   // sudah lama: terlambat
        $this->assertSame(['!', 'danger'], [$so($this->entry)['badge'], $so($this->entry)['badgeTone']]);

        $running = $this->makeCount('counting', today()->toDateString());
        $this->assertSame(['●', 'partial'], [$so($this->entry)['badge'], $so($this->entry)['badgeTone']]);
        $this->assertStringContainsString($running->doc_no, $so($this->entry)['badgeTitle']);
    }
}
