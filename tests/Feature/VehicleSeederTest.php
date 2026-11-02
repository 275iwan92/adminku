<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use App\Models\Vehicle;
use App\Services\VehicleCare;
use App\Support\Constants;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\VehicleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga data contoh GA Perawatan Aset Kendaraan: empat kendaraan fiktif dengan status Aman, Segera, dan Terlambat, seeder idempoten (dijalankan ulang tidak menggandakan), layar Monitoring / Detail dengan data contoh
 *                tetap di bawah 30 kueri, dan migrasi hak Grup (modul GA untuk grup Administrator).
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class VehicleSeederTest extends TestCase
{
    use RefreshDatabase;

    private function counts(): array
    {
        return array_map(fn ($t) => DB::table($t)->count(), ['m_vehicle_type', 'm_vehicle_type_plan', 'm_vehicle', 'g_vehicle_doc', 'g_vehicle_service', 'g_vehicle_service_line', 'g_vehicle_odometer']);
    }

    public function test_sample_vehicles_cover_all_statuses_and_the_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $counts = $this->counts();

        $this->assertSame(3, $counts[0]);
        $this->assertSame(4, $counts[2]);
        $this->assertGreaterThan(0, $counts[3]);
        $over = VehicleCare::overview(Vehicle::query()->with('type')->get());
        $status = Vehicle::query()->orderBy('plate')->get()->mapWithKeys(fn ($v) => [$v->plate => $over[$v->id]['status']])->all();
        $this->assertSame(['N 1001 JY' => 'aman', 'N 1002 JY' => 'segera', 'N 1003 JY' => 'terlambat', 'N 1004 JY' => 'aman'], $status);

        $car = Vehicle::firstWhere('plate', 'N 1001 JY');
        $this->assertFalse($car->kir_required);
        $this->assertSame('Tidak wajib', $over[$car->id]['docs']['kir']['text']);
        $this->assertSame(30, VehicleCare::soonDays());
        $this->assertSame(1000, VehicleCare::soonKm());

        $this->seed(VehicleSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($counts, $this->counts(), 'seeder dijalankan ulang tidak menggandakan data');
    }

    public function test_reseeding_keeps_a_changed_threshold_and_current_odometer(): void
    {
        User::factory()->administrator()->create();
        $this->seed(VehicleSeeder::class);
        Constants::set('ga.vehicle.soon_days', 45, 'ga');
        $v = Vehicle::firstWhere('plate', 'N 1002 JY');
        $v->update(['odometer_km' => $v->odometer_km + 50]);

        $this->seed(VehicleSeeder::class);

        $this->assertSame(45, VehicleCare::soonDays());
        $this->assertSame(69650, $v->fresh()->odometer_km);
    }

    public function test_monitoring_and_detail_with_sample_data_stay_under_the_query_budget(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin)->get(route('fleet.index'))->assertOk();   // pemanasan: view dikompilasi

        $measure = function (string $url) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($url)->assertOk();

            return count(DB::getQueryLog());
        };
        $this->assertLessThan(30, $measure(route('fleet.index')));
        foreach (Vehicle::all() as $v) {
            $this->assertLessThan(30, $measure(route('fleet.show', $v)), $v->plate);
        }
        $this->get(route('fleet.index'))->assertSee('N 1003 JY')->assertSee('Terlambat');
    }

    public function test_admin_group_gets_the_vehicle_modules_from_the_migration_and_other_groups_do_not(): void
    {
        $admin = UserGroup::where('code', 'ADMIN')->first();
        $other = UserGroup::create(['code' => 'GD', 'name' => 'Gudang']);
        $other->syncAccess(['ISS' => 'rw']);
        $migration = require database_path('migrations/2026_11_18_120000_give_vehicle_care_to_admin_groups.php');
        $modules = ['GVM', 'GVD', 'GVS', 'GVO', 'GVK', 'GVT'];

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
}
