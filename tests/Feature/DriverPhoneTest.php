<?php

namespace Tests\Feature;

use App\Models\DeliveryPosition;
use App\Models\DeliveryStop;
use App\Models\DeliveryTrip;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga fitur HP layar sopir: posisi GPS (hanya trip Ditugaskan dan belum selesai, validasi, throttle, token salah ditolak), scan kode (cocok / tidak, milik trip sendiri), peta posisi terakhir untuk admin, pembersihan 7 hari, manifest PWA.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class DriverPhoneTest extends TestCase
{
    use RefreshDatabase;

    private function trip(string $status = 'assigned', string $token = 'tok1'): DeliveryTrip
    {
        $type = VehicleType::firstOrCreate(['code' => 'MOBIL'], ['name' => 'Mobil']);
        $van = Vehicle::firstOrCreate(['plate' => 'B 1 XX'], ['vehicle_type_id' => $type->id, 'name' => 'Van', 'is_active' => true]);
        $trip = DeliveryTrip::create(['doc_no' => 'PGK-2611-000'.(DeliveryTrip::count() + 1), 'token' => $token, 'trip_date' => today(), 'vehicle_id' => $van->id, 'driver' => 'Budi', 'depart_time' => '08:00', 'return_min' => 20, 'status' => $status, 'created_by' => User::factory()->create()->id]);
        $trip->stops()->create(['seq' => 1, 'kind' => 'ant', 'customer' => 'Toko A', 'travel_min' => 10, 'items' => [['Semen', 1, 'sak', 50]]]);

        return $trip;
    }

    public function test_position_saved_for_active_trip_and_validated(): void
    {
        $trip = $this->trip();
        $this->postJson(route('driver.position', 'tok1'), ['lat' => -6.2, 'lng' => 106.8, 'accuracy' => 12])->assertOk();
        $this->assertSame(1, DeliveryPosition::where('trip_id', $trip->id)->count());
        $this->postJson(route('driver.position', 'tok1'), ['lat' => 99, 'lng' => 106.8])->assertStatus(422);
    }

    public function test_position_rejected_for_wrong_token_inactive_or_finished_trip(): void
    {
        $this->trip();
        $this->postJson(route('driver.position', 'salah'), ['lat' => -6.2, 'lng' => 106.8])->assertNotFound();
        $draft = $this->trip('draft', 'tok2');
        $this->postJson(route('driver.position', 'tok2'), ['lat' => -6.2, 'lng' => 106.8])->assertStatus(409);
        $done = $this->trip('assigned', 'tok3');
        $done->stops()->update(['arrived_at' => now()]);
        $this->postJson(route('driver.position', 'tok3'), ['lat' => -6.2, 'lng' => 106.8])->assertStatus(409);
        $this->assertSame(0, DeliveryPosition::count());
    }

    public function test_position_is_throttled(): void
    {
        $this->trip();
        for ($i = 0; $i < 6; $i++) {
            $this->postJson(route('driver.position', 'tok1'), ['lat' => -6.2, 'lng' => 106.8])->assertOk();
        }
        $this->postJson(route('driver.position', 'tok1'), ['lat' => -6.2, 'lng' => 106.8])->assertStatus(429);
    }

    public function test_scan_matches_stop_code_and_flags_mismatch(): void
    {
        $trip = $this->trip();
        $stop = $trip->stops()->first();
        $this->postJson(route('driver.scan', ['tok1', $stop->id]), ['code' => 'XYZ'])->assertOk()->assertJson(['ok' => false]);
        $this->assertFalse($stop->fresh()->scan_ok);
        $this->postJson(route('driver.scan', ['tok1', $stop->id]), ['code' => ' '.strtolower($trip->doc_no).'-1 '])->assertOk()->assertJson(['ok' => true]);
        $stop->refresh();
        $this->assertTrue($stop->scan_ok);
        $this->assertNotNull($stop->scanned_at);
    }

    public function test_scan_rejects_stop_of_another_trip_and_draft_trip(): void
    {
        $this->trip();
        $other = $this->trip('assigned', 'tok2')->stops()->first();
        $this->postJson(route('driver.scan', ['tok1', $other->id]), ['code' => 'X'])->assertNotFound();
        $draft = $this->trip('draft', 'tok3')->stops()->first();
        $this->postJson(route('driver.scan', ['tok3', $draft->id]), ['code' => 'X'])->assertStatus(410);
    }

    public function test_admin_sees_last_position_and_driver_screen_has_phone_hooks(): void
    {
        $trip = $this->trip();
        $group = UserGroup::create(['code' => 'DLV', 'name' => 'Pengiriman']);
        $group->syncAccess(['PGK' => 'rw']);
        $user = User::factory()->create(['group_id' => $group->id]);
        $this->actingAs($user)->getJson(route('trips.position', $trip))->assertOk()->assertJsonPath('lat', null);
        DeliveryPosition::create(['trip_id' => $trip->id, 'lat' => -6.2, 'lng' => 106.8, 'recorded_at' => now()]);
        $this->actingAs($user)->getJson(route('trips.position', $trip))->assertJsonPath('lat', -6.2);
        auth()->logout();
        $this->getJson(route('trips.position', $trip))->assertUnauthorized();

        $this->get(route('driver.show', 'tok1'))->assertOk()->assertSee('data-scan', false)->assertSee('data-gps', false)->assertSee('hanya tersedia di HP')->assertSee('manifest.webmanifest', false);
        $this->get(route('driver.manifest', 'tok1'))->assertOk()->assertJsonPath('display', 'standalone');
    }

    public function test_prune_removes_positions_older_than_7_days(): void
    {
        $trip = $this->trip();
        DeliveryPosition::create(['trip_id' => $trip->id, 'lat' => 1, 'lng' => 1, 'recorded_at' => now()->subDays(8)]);
        DeliveryPosition::create(['trip_id' => $trip->id, 'lat' => 1, 'lng' => 1, 'recorded_at' => now()->subDay()]);
        $this->artisan('delivery:prune-positions')->assertSuccessful();
        $this->assertSame(1, DeliveryPosition::count());
    }
}
