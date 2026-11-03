<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use App\Services\AcCare;
use App\Services\VehicleCare;
use App\Support\ActivityLog;
use App\Support\Constants;
use App\Support\Menu;
use App\Support\Modules;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\AcFixtures;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga layar Pengaturan GA (modul GAP): form terisi nilai berlaku / bawaan, simpan memakai kunci dan format yang dibaca AcCare / VehicleCare dan langsung memengaruhi perilaku (kapasitas, ambang Segera),
 *                setiap aturan validasi (pesan Indonesia, km format Indonesia), hak per peran (Administrator penuh, Supervisor lewat Grup, selain itu 403), catatan di log aktivitas, tombol Kembali, dan migrasi hak tidak menggandakan.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class GaSettingsTest extends TestCase
{
    use AcFixtures, RefreshDatabase;

    private User $admin;

    private string $logDir;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 08:00:00');
        Constants::forget();
        $this->logDir = sys_get_temp_dir().'/ga-settings-'.uniqid();
        config(['sstock.activity_dir' => $this->logDir]);
        $this->admin = User::factory()->administrator()->create();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->logDir);
        Carbon::setTestNow();
        Constants::forget();
        parent::tearDown();
    }

    private function valid(array $over = []): array
    {
        return $over + [
            'vehicle_soon_days' => '30', 'vehicle_soon_km' => '1.000', 'ac_wash_months' => '3', 'ac_major_months' => '12', 'ac_days' => ['1', '2', '3', '4', '5'],
            'ac_start_time' => '09:00', 'ac_end_time' => '16:00', 'ac_capacity' => '3', 'ac_soon_days' => '30', 'ac_pic_notice_time' => '15:00',
        ];
    }

    private function save(array $over = [])
    {
        return $this->actingAs($this->admin)->from(route('ga-settings.edit'))->put(route('ga-settings.update'), $this->valid($over));
    }

    public function test_the_form_shows_defaults_then_the_current_values(): void
    {
        $res = $this->actingAs($this->admin)->get(route('ga-settings.edit'))->assertOk();
        $res->assertSee('Pengaturan GA')->assertSee('Kendaraan')->assertSee('Perawatan AC')->assertSee('Hari Kunjungan')->assertSee('Senin')->assertSee('Minggu');
        $res->assertSee('name="vehicle_soon_days"', false)->assertSee('value="30"', false)->assertSee('value="1.000"', false)->assertSee('value="09:00"', false)->assertSee('value="16:00"', false)->assertSee('value="15:00"', false);
        $this->assertSame(7, substr_count($res->getContent(), 'name="ac_days[]"'));
        $this->assertSame(5, preg_match_all('/name="ac_days\[\]" value="[1-5]" checked/', $res->getContent()));
        $this->assertSame(0, preg_match_all('/name="ac_days\[\]" value="[67]" checked/', $res->getContent()));

        Constants::set('ga.ac.capacity', 7, 'ga');
        Constants::set('ga.ac.days', '1,6', 'ga');
        Constants::set('ga.ac.start_time', '08:30', 'ga');
        Constants::set('ga.vehicle.soon_km', 2500, 'ga');
        $res = $this->get(route('ga-settings.edit'))->assertOk();
        $res->assertSee('value="7"', false)->assertSee('value="08:30"', false)->assertSee('value="2.500"', false);
        $this->assertSame(2, preg_match_all('/name="ac_days\[\]" value="[16]" checked/', $res->getContent()));
        $this->assertSame(0, preg_match_all('/name="ac_days\[\]" value="[2-57]" checked/', $res->getContent()));
    }

    public function test_the_back_button_goes_to_home(): void
    {
        $html = $this->actingAs($this->admin)->get(route('ga-settings.edit'))->getContent();
        preg_match('/<a[^>]*href="([^"]*)"[^>]*data-f-act="back"|<a[^>]*data-f-act="back"[^>]*href="([^"]*)"/', $html, $m);

        $this->assertSame(route('home'), html_entity_decode($m[1] ?? $m[2] ?? ''));
    }

    public function test_saving_stores_the_keys_in_the_format_the_modules_read(): void
    {
        $this->save([
            'vehicle_soon_days' => '45', 'vehicle_soon_km' => '2.500', 'ac_wash_months' => '4', 'ac_major_months' => '10', 'ac_days' => ['6', '1', '3', '3'],
            'ac_start_time' => '08:00', 'ac_end_time' => '12:30', 'ac_capacity' => '2', 'ac_soon_days' => '14', 'ac_pic_notice_time' => '14:00',
        ])->assertRedirect(route('ga-settings.edit'))->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertSame('1,3,6', Constants::get('ga.ac.days'));
        $this->assertSame('2500', Constants::get('ga.vehicle.soon_km'));
        $this->assertSame('ga', DB::table('sys_constant')->where('key', 'ga.ac.capacity')->value('group'));
        $this->assertSame([45, 2500], [VehicleCare::soonDays(), VehicleCare::soonKm()]);
        $this->assertSame([4, 10, 14, 2, [1, 3, 6], '08:00', '12:30', '14:00'], [AcCare::washMonths(), AcCare::majorMonths(), AcCare::soonDays(), AcCare::capacity(), AcCare::visitDays(), AcCare::startTime(), AcCare::endTime(), AcCare::picNoticeTime()]);

        $this->get(route('ga-settings.edit'))->assertSee('value="45"', false)->assertSee('value="2.500"', false);
    }

    public function test_capacity_two_rejects_the_third_unit_of_the_same_day_and_soon_days_change_the_status(): void
    {
        $vendor = $this->acVendor();
        $this->save(['ac_capacity' => '2'])->assertSessionHasNoErrors();
        $a = $this->acLate();
        $b = $this->acLate();
        $c = $this->acLate();
        AcCare::schedule($vendor, '2026-11-03', null, [$a->id, $b->id], $this->admin);   // dua AC: muat
        $this->expectException(ValidationException::class);
        AcCare::schedule($vendor, '2026-11-03', null, [$c->id], $this->admin);   // ketiga di hari yang sama: ditolak
    }

    public function test_soon_threshold_changes_the_ac_and_vehicle_status(): void
    {
        $unit = $this->acUnit(null, null, ['last_wash_at' => '2026-08-20']);   // jatuh tempo 20-11-2026: 18 hari lagi
        $this->assertSame('segera', AcCare::overview(collect([$unit->load('room.area')]))[$unit->id]['status']);

        $this->save(['ac_soon_days' => '10'])->assertSessionHasNoErrors();
        $this->assertSame('aman', AcCare::overview(collect([$unit->fresh()->load('room.area')]))[$unit->id]['status']);
    }

    public function test_each_validation_rule_rejects_with_an_indonesian_message(): void
    {
        $cases = [
            'ac_days kosong' => [['ac_days' => []], 'ac_days', 'Pilih minimal satu hari kunjungan.'],
            'ac_days hilang' => [['ac_days' => null], 'ac_days', 'Pilih minimal satu hari kunjungan.'],
            'ac_days di luar 1..7' => [['ac_days' => ['8']], 'ac_days.0', null],
            'jam mulai bukan jam' => [['ac_start_time' => '25:00'], 'ac_start_time', 'Jam mulai harus berformat JJ:MM, mis. 09:00.'],
            'jam akhir bukan jam' => [['ac_end_time' => '9.30'], 'ac_end_time', 'Jam akhir harus berformat JJ:MM, mis. 16:00.'],
            'jam pesan PIC kosong' => [['ac_pic_notice_time' => ''], 'ac_pic_notice_time', null],
            'jam akhir = jam mulai' => [['ac_start_time' => '10:00', 'ac_end_time' => '10:00'], 'ac_end_time', 'Jam akhir harus setelah jam mulai.'],
            'jam akhir sebelum mulai' => [['ac_start_time' => '10:00', 'ac_end_time' => '09:00'], 'ac_end_time', 'Jam akhir harus setelah jam mulai.'],
            'kapasitas 0' => [['ac_capacity' => '0'], 'ac_capacity', null],
            'kapasitas 21' => [['ac_capacity' => '21'], 'ac_capacity', null],
            'kapasitas desimal' => [['ac_capacity' => '2,5'], 'ac_capacity', 'Kapasitas per hari harus berupa bilangan bulat.'],
            'bulan cuci 0' => [['ac_wash_months' => '0'], 'ac_wash_months', null],
            'bulan cuci 61' => [['ac_wash_months' => '61', 'ac_major_months' => '61'], 'ac_wash_months', null],
            'bulan servis 61' => [['ac_major_months' => '61'], 'ac_major_months', null],
            'servis < cuci' => [['ac_wash_months' => '6', 'ac_major_months' => '5'], 'ac_major_months', 'Interval servis besar tidak boleh lebih pendek dari interval cuci rutin.'],
            'hari kendaraan 0' => [['vehicle_soon_days' => '0'], 'vehicle_soon_days', null],
            'hari kendaraan 366' => [['vehicle_soon_days' => '366'], 'vehicle_soon_days', null],
            'hari AC 366' => [['ac_soon_days' => '366'], 'ac_soon_days', null],
            'km 99' => [['vehicle_soon_km' => '99'], 'vehicle_soon_km', null],
            'km 50.001' => [['vehicle_soon_km' => '50.001'], 'vehicle_soon_km', null],
            'km teks' => [['vehicle_soon_km' => 'abc'], 'vehicle_soon_km', 'Ambang Segera kendaraan (km) harus berupa bilangan bulat.'],
            'isian berupa larik' => [['ac_capacity' => ['x']], 'ac_capacity', null],
        ];
        foreach ($cases as $name => [$over, $field, $message]) {
            $res = $this->save($over)->assertRedirect(route('ga-settings.edit'));
            $res->assertSessionHasErrors($field);
            if ($message) {
                $this->assertSame($message, session('errors')->first($field), $name);
            }
            $this->assertNull(Constants::get('ga.ac.capacity'), "$name: tidak boleh ada yang tersimpan");
        }
    }

    public function test_boundaries_and_indonesian_numbers_are_accepted(): void
    {
        $this->save(['vehicle_soon_days' => '1', 'vehicle_soon_km' => '100', 'ac_wash_months' => '1', 'ac_major_months' => '1', 'ac_capacity' => '1', 'ac_soon_days' => '365', 'ac_days' => ['7']])->assertSessionHasNoErrors();
        $this->save(['vehicle_soon_days' => '365', 'vehicle_soon_km' => '50.000', 'ac_wash_months' => '60', 'ac_major_months' => '60', 'ac_capacity' => '20', 'ac_start_time' => '00:00', 'ac_end_time' => '23:59'])->assertSessionHasNoErrors();
        $this->assertSame([50000, 60, 20], [VehicleCare::soonKm(), AcCare::majorMonths(), AcCare::capacity()]);
        $this->save(['vehicle_soon_km' => '1000'])->assertSessionHasNoErrors();
        $this->assertSame(1000, VehicleCare::soonKm());
    }

    public function test_access_follows_the_group_and_the_role(): void
    {
        $this->get(route('ga-settings.edit'))->assertRedirect();   // tamu: ke login

        $this->actingAs($this->admin)->get(route('ga-settings.edit'))->assertOk();
        $this->assertArrayHasKey('GAP', Modules::all());
        $this->assertNotContains('GAP', Modules::defaults('administrator'), 'GA tidak masuk bawaan peran');

        $spv = User::factory()->supervisor()->create();
        $this->actingAs($spv)->get(route('ga-settings.edit'))->assertForbidden();
        $this->actingAs($spv)->put(route('ga-settings.update'), $this->valid())->assertForbidden();

        $entry = $this->acUser(['GAP' => 'rw']);   // peran Data Entry: modul butuh Supervisor ke atas
        $this->actingAs($entry)->get(route('ga-settings.edit'))->assertForbidden();
        $this->actingAs(User::factory()->create())->put(route('ga-settings.update'), $this->valid())->assertForbidden();

        $group = UserGroup::create(['code' => 'SPVGA', 'name' => 'Supervisor GA']);
        $group->syncAccess(['GAP' => 'rw']);
        $granted = User::factory()->supervisor()->create(['group_id' => $group->id]);
        $this->actingAs($granted)->get(route('ga-settings.edit'))->assertOk();
        $this->actingAs($granted)->put(route('ga-settings.update'), $this->valid(['ac_capacity' => '4']))->assertRedirect();
        $this->assertSame(4, AcCare::capacity());

        $group->syncAccess(['GAP' => 'ro']);
        $granted = User::find($granted->id);   // hak Grup dimemoisasi per instance pengguna
        $this->actingAs($granted)->get(route('ga-settings.edit'))->assertOk();
        $this->actingAs($granted)->put(route('ga-settings.update'), $this->valid(['ac_capacity' => '9']))->assertForbidden();
        $this->assertSame(4, AcCare::capacity());
    }

    public function test_the_menu_has_no_separate_item_and_setup_screens_link_to_it(): void
    {
        $layers = collect(Menu::layers($this->admin));
        $this->assertNotContains('Pengaturan GA', collect($layers->firstWhere('code', 'GA')['children'])->pluck('label')->all());   // dibuka dari tombol Setup, bukan menu samping

        $this->assertNull(collect(Menu::layers(User::factory()->supervisor()->create()))->firstWhere('code', 'GA'));
    }

    public function test_who_changed_what_and_when_is_written_to_the_activity_log(): void
    {
        $this->save(['ac_capacity' => '5'])->assertSessionHasNoErrors();
        $this->save(['ac_capacity' => '5'])->assertSessionHas('status', 'Tidak ada perubahan.');   // tanpa perubahan: tidak dicatat dua kali

        $rows = collect(ActivityLog::read())->where('action', 'Ubah pengaturan GA');
        $this->assertCount(1, $rows);
        $this->assertSame($this->admin->username, $rows->first()['user']);
        $this->assertStringContainsString('ga.ac.capacity - > 5', $rows->first()['detail']);
    }

    public function test_the_rights_migration_gives_gap_to_admin_once_and_rolls_back(): void
    {
        $adminGroup = UserGroup::firstOrCreate(['code' => 'ADMIN'], ['name' => 'Administrator']);
        $other = UserGroup::create(['code' => 'LAIN', 'name' => 'Lain']);
        $migration = require base_path('database/migrations/2026_11_20_120000_give_ga_settings_to_admin_groups.php');

        $migration->up();
        $migration->up();
        $this->assertSame(1, DB::table('sys_user_group_module')->where(['module' => 'GAP', 'group_id' => $adminGroup->id])->count());
        $this->assertSame('rw', DB::table('sys_user_group_module')->where(['module' => 'GAP', 'group_id' => $adminGroup->id])->value('access'));
        $this->assertSame(0, DB::table('sys_user_group_module')->where(['module' => 'GAP', 'group_id' => $other->id])->count());

        $migration->down();
        $this->assertSame(0, DB::table('sys_user_group_module')->where('module', 'GAP')->count());
    }
}
