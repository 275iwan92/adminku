<?php

namespace Tests\Feature;

use App\Models\AcArea;
use App\Models\AcOrder;
use App\Models\AcRoom;
use App\Models\AcUnit;
use App\Models\AcVendor;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\AcCare;
use App\Services\AcMessage;
use App\Support\Constants;
use App\Support\Menu;
use Database\Seeders\AcSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga data contoh GA Perawatan AC (4 area, 7 ruang, 9 unit dengan status Aman, Segera, dan Terlambat, satu vendor fiktif @example.test, satu kunjungan Draf berisi 2 AC), seeder idempoten
 *                (dijalankan ulang tidak menggandakan dan tidak menggeser riwayat), migrasi hak Grup (Administrator saja), menu GA yang memuat item nyata, dan semua layar AC dengan data contoh: tanpa 5xx, di bawah 30 kueri.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class AcSeederTest extends TestCase
{
    use RefreshDatabase;

    private function counts(): array
    {
        return array_map(fn ($t) => DB::table($t)->count(), ['m_ac_vendor', 'm_ac_area', 'm_ac_room', 'm_ac_unit', 'g_ac_service_order', 'g_ac_service_line', 'g_ac_service_reschedule']);
    }

    public function test_the_sample_data_has_all_statuses_and_the_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $counts = $this->counts();

        $this->assertSame([1, 4, 7, 9, 1, 2, 0], $counts);
        $over = AcCare::overview(AcUnit::query()->with('room.area')->get());
        $status = AcUnit::query()->orderBy('asset_code')->get()->mapWithKeys(fn ($u) => [$u->asset_code => $over[$u->id]['status']])->all();
        $this->assertSame(['AC-0001' => 'terlambat', 'AC-0002' => 'terlambat', 'AC-0003' => 'segera', 'AC-0004' => 'segera', 'AC-0005' => 'aman', 'AC-0006' => 'aman', 'AC-0007' => 'terlambat', 'AC-0008' => 'segera', 'AC-0009' => 'aman'], $status);
        $this->assertSame(['major', 'wash'], [$over[AcUnit::firstWhere('asset_code', 'AC-0002')->id]['job'], $over[AcUnit::firstWhere('asset_code', 'AC-0001')->id]['job']]);

        $this->assertSame(['Daikin', 'Panasonic', 'LG', 'Gree', 'Sharp'], AcUnit::query()->orderBy('asset_code')->pluck('brand')->unique()->values()->all());
        $this->assertSame(['KL1', 'KL2', 'GDG', 'WRK'], AcArea::query()->orderBy('id')->pluck('code')->all());
        $this->assertSame(1.0, AcUnit::firstWhere('asset_code', 'AC-0001')->capacity_pk);
        $this->assertSame(3.0, AcUnit::firstWhere('asset_code', 'AC-0009')->capacity_pk);
        $vendor = AcVendor::first();
        $this->assertStringEndsWith('@example.test', $vendor->email);
        $this->assertSame('6281200001001', AcMessage::waNumber($vendor->phone));
        foreach (AcRoom::all() as $r) {
            $this->assertStringEndsWith('@example.test', $r->pic_email);
        }

        $order = AcOrder::first();
        $this->assertSame(['draft', 2, $vendor->id], [$order->status, $order->lines()->count(), $order->vendor_id]);
        $this->assertTrue($order->visit_date->gte(today()->addDay()) && in_array($order->visit_date->dayOfWeekIso, AcCare::visitDays(), true));
        $this->assertSame([3, 12, 3, 30], [AcCare::capacity(), AcCare::majorMonths(), AcCare::washMonths(), AcCare::soonDays()]);

        $this->seed(AcSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($counts, $this->counts(), 'seeder dijalankan ulang tidak menggandakan data');
    }

    public function test_reseeding_keeps_changed_settings_and_the_history_of_an_existing_unit(): void
    {
        User::factory()->administrator()->create(['username' => 'admin']);
        $this->seed(AcSeeder::class);
        Constants::set('ga.ac.capacity', 2, 'ga');
        $u = AcUnit::firstWhere('asset_code', 'AC-0001');
        $u->update(['last_wash_at' => '2026-10-30', 'brand' => 'Ganti']);

        $this->seed(AcSeeder::class);

        $this->assertSame(2, AcCare::capacity());
        $this->assertSame('2026-10-30', $u->fresh()->last_wash_at->toDateString(), 'tanggal cuci awal tidak digeser');
        $this->assertSame('Daikin', $u->fresh()->brand, 'data master contoh disegarkan');
    }

    public function test_the_admin_group_gets_the_ac_modules_from_the_migration_and_other_groups_do_not(): void
    {
        $admin = UserGroup::where('code', 'ADMIN')->first();
        $other = UserGroup::create(['code' => 'GD', 'name' => 'Gudang']);
        $other->syncAccess(['ISS' => 'rw']);
        $migration = require database_path('migrations/2026_11_19_120000_give_ac_care_to_admin_groups.php');
        $modules = ['GAS', 'GAV', 'GAD', 'GAK', 'GAA', 'GAR', 'GAE'];

        $migration->down();
        $this->assertSame([], array_intersect($modules, array_keys($admin->moduleAccess())));
        $migration->up();
        $migration->up();   // dapat diulang
        $access = $admin->moduleAccess();
        foreach ($modules as $m) {
            $this->assertSame('rw', $access[$m]);
        }
        $this->assertSame(['ISS' => 'rw'], $other->moduleAccess());
    }

    public function test_the_ga_menu_lists_the_real_ac_items_and_keeps_only_the_building_placeholder(): void
    {
        $labels = collect(Menu::layers(User::factory()->administrator()->create()))->firstWhere('code', 'GA')['children'];
        $this->assertSame(['Penjadwalan AC', 'Kunjungan AC', 'Denah AC', 'Master AC', 'Area dan Gedung AC', 'Ruang AC', 'Vendor AC', 'Perawatan Gedung'], array_slice(array_column($labels, 'label'), 6));
        $this->assertNotContains('Perawatan AC', array_column($labels, 'label'));
        foreach (array_slice($labels, 6, 7) as $item) {
            $this->assertStringNotContainsString('segera', $item['url']);
        }
        $this->actingAs(User::factory()->administrator()->create())->get('/segera/ac')->assertNotFound();
        $this->get('/segera/gedung')->assertOk();   // Perawatan Gedung tetap layar sementara

        // Pengguna biasa tanpa Grup tidak melihat GA; pengguna berhak (Grup) hanya melihat modul yang diberikan.
        $this->assertNull(collect(Menu::layers(User::factory()->create()))->firstWhere('code', 'GA'));
        $group = UserGroup::create(['code' => 'GAV', 'name' => 'Lihat AC']);
        $group->syncAccess(['GAD' => 'ro', 'GAV' => 'ro']);
        $user = User::factory()->create(['group_id' => $group->id]);
        $this->assertSame(['Kunjungan AC', 'Denah AC'], array_column(collect(Menu::layers($user))->firstWhere('code', 'GA')['children'], 'label'));
    }

    public function test_the_phone_shell_opens_every_ac_module_and_every_master_form_goes_back_to_its_grid(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin);
        foreach (['GAS', 'GAV', 'GAD', 'GAK', 'GAA', 'GAR', 'GAE'] as $code) {
            $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36')->get(route('hp.open', $code))->assertOk();
        }
        $back = function (string $html) {
            preg_match('/<a[^>]*href="([^"]*)"[^>]*data-f-act="back"|<a[^>]*data-f-act="back"[^>]*href="([^"]*)"/', $html, $m);

            return html_entity_decode($m[1] ?? $m[2] ?? '');
        };
        $forms = [
            route('ac-units.create') => route('ac-units.index'), route('ac-units.show', AcUnit::first()) => route('ac-units.index'),
            route('ac-areas.create') => route('ac-areas.index'), route('ac-areas.show', AcArea::first()) => route('ac-areas.index'),
            route('ac-rooms.create') => route('ac-rooms.index'), route('ac-rooms.show', AcRoom::first()) => route('ac-rooms.index'),
            route('ac-vendors.create') => route('ac-vendors.index'), route('ac-vendors.show', AcVendor::first()) => route('ac-vendors.index'),
        ];
        foreach ($forms as $url => $grid) {
            $this->assertSame($grid, $back($this->get($url)->assertOk()->getContent()), $url);
        }
    }

    public function test_every_ac_screen_works_with_the_sample_data_and_stays_under_the_query_budget(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('username', 'admin')->first();
        $order = AcOrder::first();
        $unit = AcUnit::firstWhere('asset_code', 'AC-0001');
        $urls = [
            route('ac-schedule.index'), route('ac-schedule.index', ['show' => 'semua']), route('ac-visits.index'), route('ac-visits.show', $order), route('ac-visits.print', $order),
            route('ac-plan.index'), route('ac-plan.index', ['unit' => $unit->id]), route('ac-plan.index', ['area' => AcArea::firstWhere('code', 'GDG')->id]),
            route('ac-units.index'), route('ac-units.show', $unit), route('ac-areas.index'), route('ac-areas.show', AcArea::first()), route('ac-rooms.index'), route('ac-rooms.show', AcRoom::first()),
            route('ac-vendors.index'), route('ac-vendors.show', AcVendor::first()),
        ];
        $this->actingAs($admin);
        foreach ($urls as $url) {
            $this->get($url)->assertOk();   // pemanasan: view dikompilasi
        }
        foreach ($urls as $url) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $start = microtime(true);
            $this->get($url)->assertOk();
            $this->assertLessThan(30, count(DB::getQueryLog()), $url);
            $this->assertLessThan(1500, (microtime(true) - $start) * 1000, $url.' terlalu lambat');
        }
        $this->get(route('ac-schedule.index'))->assertSee('AC-0001')->assertSee('lewat 45 hari', false)->assertSee('Terlambat dicuci');
        $this->get(route('ac-visits.show', $order))->assertSee($order->no)->assertSee('Gree')->assertSee('Sari Dewi (contoh)');
    }
}
