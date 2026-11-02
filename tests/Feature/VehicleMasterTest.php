<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Services\VehicleCare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga master GA Perawatan Aset Kendaraan: Jenis Kendaraan beserta rencana perawatannya (sinkron menurut nama, tiap butir punya interval) dan Kendaraan (plat unik, jenis aktif, odometer awal),
 *                serta hak Grup: tanpa Grup tidak ada akses GA (403), RO hanya melihat, RW boleh menulis.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class VehicleMasterTest extends TestCase
{
    use RefreshDatabase;

    private function ga(array $access): User
    {
        $group = UserGroup::create(['code' => 'GA'.count(UserGroup::all()), 'name' => 'GA uji']);
        $group->syncAccess($access);

        return User::factory()->create(['group_id' => $group->id]);
    }

    private function typePayload(array $over = []): array
    {
        return $over + ['code' => 'PICKUP', 'name' => 'Pickup', 'plans' => [
            ['name' => 'Oli mesin', 'interval_km' => 5000, 'interval_months' => 6],
            ['name' => 'Aki', 'interval_km' => '', 'interval_months' => 24],
        ]];
    }

    public function test_type_is_saved_with_plans_and_edit_syncs_by_name(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        $this->post(route('vehicle-types.store'), $this->typePayload())->assertSessionHasNoErrors();
        $type = VehicleType::firstWhere('code', 'PICKUP');
        $this->assertSame(['Oli mesin', 'Aki'], $type->plans->pluck('name')->all());
        $oliId = $type->plans->first()->id;

        $this->put(route('vehicle-types.update', $type), ['name' => 'Pickup Bak', 'plans' => [
            ['name' => 'oli mesin', 'interval_km' => 7000, 'interval_months' => ''],
            ['name' => 'Filter udara', 'interval_km' => 20000, 'interval_months' => 12],
        ]])->assertSessionHasNoErrors();

        $type->refresh()->load('plans');
        $this->assertSame('Pickup Bak', $type->name);
        $this->assertSame('PICKUP', $type->code);
        $this->assertSame(['oli mesin', 'Filter udara'], $type->plans->pluck('name')->all(), 'Aki dibuang, butir oli dipertahankan (nama mengikuti ketikan terakhir)');
        $this->assertSame($oliId, $type->plans->first()->id, 'butir dengan nama sama dipertahankan (riwayat tetap terhubung)');
        $this->assertSame(7000, $type->plans->first()->interval_km);
        $this->assertNull($type->plans->first()->interval_months);
    }

    public function test_plan_needs_a_name_an_interval_and_no_duplicates(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        $this->post(route('vehicle-types.store'), $this->typePayload(['plans' => [['name' => 'Ban', 'interval_km' => '', 'interval_months' => '']]]))->assertSessionHasErrors('plans.0.interval_km');
        $this->post(route('vehicle-types.store'), $this->typePayload(['plans' => [['name' => 'Ban', 'interval_km' => 1], ['name' => 'ban', 'interval_km' => 2]]]))->assertSessionHasErrors('plans.0.name');
        $this->post(route('vehicle-types.store'), $this->typePayload(['code' => 'x y']))->assertSessionHasErrors('code');
        $this->assertSame(0, VehicleType::count());

        $this->post(route('vehicle-types.store'), $this->typePayload());
        $this->post(route('vehicle-types.store'), $this->typePayload(['name' => 'Lain']))->assertSessionHasErrors('code');
        $this->post(route('vehicle-types.store'), $this->typePayload(['code' => 'PICKUP2', 'name' => 'pickup']))->assertSessionHasErrors('name');
    }

    public function test_vehicle_is_saved_with_normalized_plate_and_unique_plate_and_active_type(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $type = VehicleType::create(['code' => 'BOKS', 'name' => 'Boks']);
        $off = VehicleType::create(['code' => 'OFF', 'name' => 'Lama', 'is_active' => false]);
        $payload = ['plate' => ' n  1234  ab ', 'name' => 'Gran Max Boks', 'vehicle_type_id' => $type->id, 'year' => 2021, 'kir_required' => 1, 'odometer_km' => 85000];

        $this->post(route('vehicles.store'), $payload)->assertSessionHasNoErrors();
        $v = Vehicle::firstWhere('plate', 'N 1234 AB');
        $this->assertNotNull($v);
        $this->assertSame(85000, $v->odometer_km);
        $this->assertTrue($v->kir_required);

        $this->post(route('vehicles.store'), ['plate' => 'n 1234 ab'] + $payload)->assertSessionHasErrors('plate');
        $this->post(route('vehicles.store'), ['plate' => 'L 1 A', 'vehicle_type_id' => $off->id] + $payload)->assertSessionHasErrors('vehicle_type_id');
        $this->post(route('vehicles.store'), ['plate' => 'L 2 A', 'year' => 1900] + $payload)->assertSessionHasErrors('year');
        $this->post(route('vehicles.store'), ['plate' => 'L 3 A', 'odometer_km' => -5] + $payload)->assertSessionHasErrors('odometer_km');
    }

    public function test_new_vehicle_records_the_starting_odometer_and_type_is_locked_after_the_first_service(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);
        $type = VehicleType::create(['code' => 'BOKS', 'name' => 'Boks']);
        $other = VehicleType::create(['code' => 'PICKUP', 'name' => 'Pickup']);

        $this->post(route('vehicles.store'), ['plate' => 'N 5 A', 'name' => 'Boks', 'vehicle_type_id' => $type->id, 'odometer_km' => 42000])->assertSessionHasNoErrors();
        $v = Vehicle::firstWhere('plate', 'N 5 A');
        $this->assertDatabaseHas('g_vehicle_odometer', ['vehicle_id' => $v->id, 'km' => 42000, 'source' => 'awal']);
        $this->assertSame(today()->toDateString(), $v->odometer_at->toDateString());

        $update = ['plate' => 'N 5 A', 'name' => 'Boks', 'vehicle_type_id' => $other->id];
        $this->put(route('vehicles.update', $v), $update)->assertSessionHasNoErrors();   // belum ada servis: jenis boleh diganti
        VehicleCare::recordService($v->fresh(), ['serviced_at' => today()->toDateString(), 'odometer_km' => 42100, 'cost' => 0, 'jobs' => []], $admin);
        $this->put(route('vehicles.update', $v), ['vehicle_type_id' => $type->id] + $update)->assertSessionHasErrors('vehicle_type_id');
        $this->assertSame($other->id, $v->fresh()->vehicle_type_id);
    }

    public function test_odometer_cannot_be_changed_from_the_master_and_toggle_works(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $type = VehicleType::create(['code' => 'BOKS', 'name' => 'Boks']);
        $v = Vehicle::create(['vehicle_type_id' => $type->id, 'plate' => 'N 1 A', 'name' => 'Boks', 'odometer_km' => 1000, 'kir_required' => true]);
        $update = ['plate' => 'N 1 A', 'name' => 'Boks Baru', 'vehicle_type_id' => $type->id];

        $this->put(route('vehicles.update', $v), $update + ['odometer_km' => 5])->assertSessionHasErrors('odometer_km');
        $this->put(route('vehicles.update', $v), $update)->assertSessionHasNoErrors();
        $this->assertSame(1000, $v->fresh()->odometer_km);
        $this->assertSame('Boks Baru', $v->fresh()->name);
        $this->assertFalse($v->fresh()->kir_required, 'toggle tidak dikirim = Tidak');

        $this->postJson(route('vehicles.toggle', $v))->assertOk()->assertJson(['active' => false]);
        $this->put(route('vehicles.update', $v), ['_action' => 'activate'])->assertSessionHasNoErrors();
        $this->assertTrue($v->fresh()->is_active);
    }

    public function test_screens_open_and_grids_list_the_data(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $type = VehicleType::create(['code' => 'BOKS', 'name' => 'Boks']);
        $type->plans()->create(['name' => 'Oli mesin', 'interval_km' => 5000, 'sort' => 1]);
        $v = Vehicle::create(['vehicle_type_id' => $type->id, 'plate' => 'N 1 A', 'name' => 'Boks']);

        $this->get(route('vehicles.index'))->assertOk()->assertSee('N 1 A');
        $this->get(route('vehicles.create'))->assertOk();
        $this->get(route('vehicles.show', $v))->assertOk()->assertSee('N 1 A');
        $this->get(route('vehicle-types.index'))->assertOk()->assertSee('Boks');
        $this->get(route('vehicle-types.create'))->assertOk();
        $this->get(route('vehicle-types.show', $type))->assertOk()->assertSee('Oli mesin');
    }

    public function test_group_rights_decide_access_to_the_masters(): void
    {
        $type = VehicleType::create(['code' => 'BOKS', 'name' => 'Boks']);

        // Tanpa Grup (bawaan peran): GA tidak otomatis.
        $this->actingAs(User::factory()->create())->get(route('vehicles.index'))->assertForbidden();
        $this->actingAs(User::factory()->supervisor()->create())->get(route('vehicle-types.index'))->assertForbidden();

        // Grup tanpa GVK / hanya RO / RW.
        $this->actingAs($this->ga(['GVT' => 'rw']))->get(route('vehicles.index'))->assertForbidden();
        $ro = $this->ga(['GVK' => 'ro', 'GVT' => 'ro']);
        $this->actingAs($ro)->get(route('vehicles.index'))->assertOk();
        $this->actingAs($ro)->get(route('vehicle-types.show', $type))->assertOk();
        $this->actingAs($ro)->post(route('vehicle-types.store'), $this->typePayload())->assertForbidden();
        $this->actingAs($ro)->post(route('vehicles.store'), ['plate' => 'N 9 A', 'name' => 'X', 'vehicle_type_id' => $type->id])->assertForbidden();

        $rw = $this->ga(['GVK' => 'rw', 'GVT' => 'rw']);
        $this->actingAs($rw)->post(route('vehicles.store'), ['plate' => 'N 9 A', 'name' => 'X', 'vehicle_type_id' => $type->id, 'kir_required' => 0])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('m_vehicle', ['plate' => 'N 9 A']);
    }
}
