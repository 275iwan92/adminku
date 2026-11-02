<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use App\Models\Vehicle;
use App\Models\VehicleOdometer;
use App\Models\VehicleType;
use App\Services\VehicleCare;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga layar Monitoring Kendaraan dan Detail: baris per kendaraan aktif (urut terburuk dulu), teks pajak / KIR / servis berikutnya, kotak ringkasan, "Tidak wajib" bagi KIR, riwayat servis,
 *                lampiran privat yang hanya terbuka bagi pemegang hak modul GVM, dan jumlah kueri yang tetap kecil.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class FleetScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private VehicleType $type;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 08:00:00');
        Storage::fake('ga');
        $this->admin = User::factory()->administrator()->create();
        $this->type = VehicleType::create(['code' => 'PICKUP', 'name' => 'Pickup']);
        $this->type->plans()->create(['name' => 'Oli mesin', 'interval_km' => 5000, 'interval_months' => 6, 'sort' => 1]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function vehicle(string $plate, array $over = []): Vehicle
    {
        $v = Vehicle::create($over + ['vehicle_type_id' => $this->type->id, 'plate' => $plate, 'name' => 'Gran Max', 'kir_required' => true, 'odometer_km' => 10000, 'odometer_at' => '2026-10-20']);
        VehicleOdometer::create(['vehicle_id' => $v->id, 'read_at' => '2026-10-01', 'km' => 10000, 'source' => 'awal', 'created_by' => $this->admin->id]);

        return $v;
    }

    private function renew(Vehicle $v, string $type, string $until, $file = null)
    {
        return VehicleCare::recordDoc($v, ['doc_type' => $type, 'paid_at' => '2026-01-01', 'valid_until' => $until, 'cost' => 0, 'doc_no' => 'NO-1'], $file, $this->admin);
    }

    private function user(array $access): User
    {
        $group = UserGroup::create(['code' => 'G'.UserGroup::count(), 'name' => 'GA uji']);
        $group->syncAccess($access);

        return User::factory()->create(['group_id' => $group->id]);
    }

    public function test_monitoring_lists_active_vehicles_worst_first_with_summary(): void
    {
        $ok = $this->vehicle('B 1 OK');
        foreach (['pajak', 'stnk', 'kir'] as $t) {
            $this->renew($ok, $t, '2027-12-31');
        }
        VehicleCare::recordService($ok, ['serviced_at' => '2026-10-25', 'odometer_km' => 10100, 'cost' => 250000, 'jobs' => [$this->type->plans->first()->id]], $this->admin);

        $late = $this->vehicle('B 2 LATE');
        $this->renew($late, 'pajak', '2026-10-01');
        $this->renew($late, 'stnk', '2027-12-31');
        $car = $this->vehicle('B 3 CAR', ['kir_required' => false]);
        foreach (['pajak', 'stnk'] as $t) {
            $this->renew($car, $t, '2027-12-31');
        }
        $this->vehicle('B 4 OFF', ['is_active' => false]);

        $res = $this->actingAs($this->admin)->get(route('fleet.index'))->assertOk()->assertSee('Monitoring Kendaraan');
        $rows = collect($res->viewData('rows'))->keyBy('plate');

        $this->assertSame(['B 2 LATE', 'B 1 OK', 'B 3 CAR'], collect($res->viewData('rows'))->pluck('plate')->all(), 'terlambat dulu, lalu urut plat; kendaraan nonaktif tidak tampil');
        $this->assertSame([3, 1, 0, 'IDR 250.000'], [$res->viewData('active'), $res->viewData('late'), $res->viewData('soon'), VehicleCare::rp($res->viewData('yearCost'))]);
        $this->assertSame('Terlambat', $rows['B 2 LATE']['status']);
        $this->assertSame('01-10-2026 · lewat 32 hari', $rows['B 2 LATE']['tax']);
        $this->assertSame('Aman', $rows['B 1 OK']['status']);
        $this->assertSame('31-12-2027 · 424 hari lagi', $rows['B 1 OK']['tax']);
        $this->assertSame('Tidak wajib', $rows['B 3 CAR']['kir']);
        $this->assertSame('Oli mesin · 5.000 km lagi / 174 hari lagi', $rows['B 1 OK']['service']);
        $this->assertSame('Belum dicatat', $rows['B 2 LATE']['kir']);
    }

    public function test_detail_shows_documents_schedule_and_history(): void
    {
        $v = $this->vehicle('B 9 DT');
        $this->renew($v, 'pajak', '2027-01-15');
        VehicleCare::recordService($v, ['serviced_at' => '2026-10-25', 'odometer_km' => 10100, 'workshop' => 'Bengkel Maju', 'cost' => 1250000.5, 'jobs' => [$this->type->plans->first()->id]], $this->admin);

        $this->actingAs($this->admin)->get(route('fleet.show', $v))->assertOk()
            ->assertSeeInOrder(['B 9 DT', 'Dokumen dan masa berlaku', 'Pajak tahunan', '15-01-2027', 'Jadwal perawatan', 'Oli mesin', '15.100 km / 25-04-2027', 'Riwayat servis', 'Bengkel Maju', 'IDR 1.250.000,50'])
            ->assertSee('Belum dicatat');

        $car = $this->vehicle('B 8 CAR', ['kir_required' => false]);
        $this->get(route('fleet.show', $car))->assertOk()->assertSee('Tidak wajib');
    }

    public function test_monitoring_and_detail_stay_within_a_small_query_budget(): void
    {
        foreach (range(1, 12) as $i) {
            $v = $this->vehicle("B $i QQ");
            $this->renew($v, 'pajak', '2027-06-01');
            VehicleCare::recordService($v, ['serviced_at' => '2026-10-25', 'odometer_km' => 10100, 'cost' => 1, 'jobs' => [$this->type->plans->first()->id]], $this->admin);
        }
        $this->actingAs($this->admin);
        $queries = function (string $url) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($url)->assertOk();

            return count(DB::getQueryLog());
        };

        $this->assertLessThan(30, $queries(route('fleet.index')));
        $this->assertLessThan(30, $queries(route('fleet.show', Vehicle::first())));
    }

    public function test_screens_need_the_monitoring_module(): void
    {
        $v = $this->vehicle('B 7 AC');

        $this->actingAs($this->user(['GVK' => 'rw']))->get(route('fleet.index'))->assertForbidden();
        $this->actingAs($this->user(['GVK' => 'rw']))->get(route('fleet.show', $v))->assertForbidden();
        $this->actingAs($this->user(['GVM' => 'ro']))->get(route('fleet.show', $v))->assertOk();
        auth()->logout();
        $this->get(route('fleet.index'))->assertRedirect(route('login'));
    }

    public function test_attachments_are_private_and_served_only_with_the_monitoring_right(): void
    {
        $v = $this->vehicle('B 6 FL');
        $doc = $this->renew($v, 'pajak', '2027-01-15', UploadedFile::fake()->create('bukti.pdf', 50, 'application/pdf'));
        $photo = VehicleCare::recordOdometer($v, ['read_at' => '2026-11-02', 'km' => 10200], UploadedFile::fake()->image('odo.jpg'), $this->admin);

        $this->actingAs($this->user(['GVM' => 'ro']));
        $res = $this->get(route('fleet.file', ['doc', $doc->id]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('inline', $res->headers->get('Content-Disposition'));
        $this->assertStringContainsString('pajak-tahunan-b-6-fl-2026-01-01.pdf', $res->headers->get('Content-Disposition'));
        $this->get(route('fleet.file', ['odometer', $photo->id]))->assertOk();
        $this->get(route('fleet.file', ['odometer', $doc->id]))->assertNotFound();   // id doc bukan id foto: tidak ada fotonya
        $this->assertStringNotContainsString('storage', route('fleet.file', ['doc', $doc->id]));
        $this->assertFalse(file_exists(public_path('storage/'.$doc->attachment_path)));

        $this->actingAs($this->user(['GVK' => 'rw']))->get(route('fleet.file', ['doc', $doc->id]))->assertForbidden();
        auth()->logout();
        $this->get(route('fleet.file', ['doc', $doc->id]))->assertRedirect(route('login'));
    }
}
