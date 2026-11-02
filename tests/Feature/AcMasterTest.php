<?php

namespace Tests\Feature;

use App\Models\AcArea;
use App\Models\AcRoom;
use App\Models\AcUnit;
use App\Models\AcVendor;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\AcCare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga master GA Perawatan AC: Area (kode otomatis, denah unggahan jpg / png maks 5 MB), Ruang (kode otomatis dari nama, persegi harus muat di denah), Unit AC (kode aset otomatis, unik dan TIDAK berubah,
 *                titik otomatis di tengah ruang), Vendor AC (nomor WhatsApp), hak Grup per modul, dan aturan Aktif / Nonaktif.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class AcMasterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('ga');
        $this->admin = User::factory()->administrator()->create();
    }

    private function area(string $name = 'Kantor Lt. 1'): AcArea
    {
        return AcArea::create(['code' => AcCare::uniqueCode('m_ac_area', $name), 'name' => $name]);
    }

    private function room(AcArea $area, string $name = 'Ruang Meeting', array $over = []): AcRoom
    {
        return AcRoom::create($over + ['area_id' => $area->id, 'code' => AcCare::uniqueCode('m_ac_room', $name), 'name' => $name, 'x' => 10, 'y' => 20, 'w' => 30, 'h' => 20]);
    }

    private function unit(AcRoom $room, array $over = []): AcUnit
    {
        return AcUnit::create($over + ['asset_code' => AcUnit::nextCode(), 'room_id' => $room->id, 'brand' => 'Daikin', 'capacity_pk' => 1]);
    }

    private function user(array $access): User
    {
        $group = UserGroup::create(['code' => 'G'.UserGroup::count(), 'name' => 'GA uji']);
        $group->syncAccess($access);

        return User::factory()->create(['group_id' => $group->id]);
    }

    public function test_codes_come_from_names_and_stay_unique(): void
    {
        $this->assertSame('RM', AcCare::codeFrom('Ruang Meeting'));
        $this->assertSame('KL1', AcCare::codeFrom('Kantor Lt. 1'));
        $this->assertSame('GUD', AcCare::codeFrom('Gudang'));
        $this->assertSame('X', AcCare::codeFrom('---'));
        $this->area('Gudang');
        $this->assertSame('GUD2', AcCare::uniqueCode('m_ac_area', 'Gudang'));
    }

    public function test_area_is_created_with_an_automatic_code_and_a_plan_that_can_be_replaced_and_removed(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('ac-areas.store'), ['name' => 'Kantor Lt. 2', 'plan' => UploadedFile::fake()->image('denah.png', 800, 400)])->assertRedirect();
        $area = AcArea::firstWhere('name', 'Kantor Lt. 2');
        $this->assertSame('KL2', $area->code);
        $this->assertSame([800, 400], [$area->plan_w, $area->plan_h]);
        Storage::disk('ga')->assertExists($area->plan_path);
        $this->get(route('ac-areas.plan', $area))->assertOk();
        $this->get(route('ac-areas.show', $area))->assertOk()->assertSee('Hapus Denah Unggahan');

        $old = $area->plan_path;
        $this->put(route('ac-areas.update', $area), ['name' => 'Kantor Lt. 2', 'code' => 'KL2', 'plan' => UploadedFile::fake()->image('baru.jpg', 300, 300)])->assertRedirect();
        Storage::disk('ga')->assertMissing($old);
        $this->assertSame([300, 300], [$area->fresh()->plan_w, $area->fresh()->plan_h]);

        $this->put(route('ac-areas.update', $area), ['name' => 'Kantor Lt. 2', 'code' => 'KL2', 'remove_plan' => '1'])->assertRedirect();
        $this->assertNull($area->fresh()->plan_path);
        $this->assertSame([], Storage::disk('ga')->allFiles());
        $this->get(route('ac-areas.plan', $area))->assertNotFound();

        $this->post(route('ac-areas.store'), ['name' => 'X', 'plan' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertSessionHasErrors(['plan' => 'Denah harus berupa gambar JPG atau PNG.']);
        $this->post(route('ac-areas.store'), ['name' => 'X', 'plan' => UploadedFile::fake()->image('besar.png')->size(5121)])->assertSessionHasErrors(['plan' => 'Denah paling besar 5 MB.']);
    }

    public function test_room_code_is_automatic_unique_and_the_rectangle_must_fit_the_plan(): void
    {
        $this->actingAs($this->admin);
        $area = $this->area();
        $base = ['area_id' => $area->id, 'name' => 'Ruang Meeting', 'x' => 5, 'y' => 5, 'w' => 25, 'h' => 25, 'pic_name' => 'Budi', 'pic_phone' => '0812-3456-7890', 'pic_email' => 'budi@example.test'];

        $this->post(route('ac-rooms.store'), $base)->assertRedirect();
        $this->post(route('ac-rooms.store'), $base)->assertRedirect();   // nama sama: kode kedua diberi angka
        $this->assertSame(['RM', 'RM2'], AcRoom::orderBy('id')->pluck('code')->all());

        $this->post(route('ac-rooms.store'), ['x' => 80, 'w' => 30] + $base)->assertSessionHasErrors(['x' => 'Persegi ruang keluar dari denah (posisi + ukuran lebih dari 100 persen).']);
        $this->post(route('ac-rooms.store'), ['pic_phone' => 'abc'] + $base)->assertSessionHasErrors('pic_phone');
        $this->post(route('ac-rooms.store'), ['pic_email' => 'bukan-email'] + $base)->assertSessionHasErrors('pic_email');
        $this->post(route('ac-rooms.store'), ['code' => 'RM'] + $base)->assertSessionHasErrors('code');
        $this->assertSame(2, AcRoom::count());

        $this->get(route('ac-rooms.create', ['area' => $area->id]))->assertOk();   // persegi bawaan: sel kosong berikutnya
        $room = AcRoom::first();
        $this->put(route('ac-rooms.update', $room), ['code' => 'MTG'] + $base)->assertRedirect();   // kode bisa diubah
        $this->assertSame('MTG', $room->fresh()->code);
    }

    public function test_unit_gets_an_automatic_permanent_asset_code_and_a_point_in_the_room_centre(): void
    {
        $this->actingAs($this->admin);
        $room = $this->room($this->area());
        $other = $this->room($room->area, 'Ruang Direksi', ['x' => 50, 'y' => 50]);
        $data = ['room_id' => $room->id, 'brand' => 'Daikin', 'model' => 'FTV25', 'capacity_pk' => '1.5', 'last_wash_at' => today()->toDateString()];

        $this->post(route('ac-units.store'), $data)->assertRedirect();
        $this->post(route('ac-units.store'), $data)->assertRedirect();
        $units = AcUnit::orderBy('id')->get();
        $this->assertSame(['AC-0001', 'AC-0002'], $units->pluck('asset_code')->all());
        $this->assertEqualsWithDelta(19.0, $units[0]->pos_x, 0.01);   // tengah ruang 10 + 30/2 - 6, lihat withPosition
        $this->assertEqualsWithDelta(30.0, $units[0]->pos_y, 0.01);
        $this->assertNotEquals($units[0]->pos_x, $units[1]->pos_x, 'titik AC kedua di ruang yang sama tidak bertumpuk');

        // Kode aset tidak berubah, ruang boleh pindah.
        $this->put(route('ac-units.update', $units[0]), ['asset_code' => 'AC-9999'] + $data)->assertSessionHasErrors('asset_code');
        $this->put(route('ac-units.update', $units[0]), ['room_id' => $other->id] + $data)->assertSessionHasNoErrors()->assertRedirect();
        $moved = $units[0]->fresh();
        $this->assertSame(['AC-0001', $other->id], [$moved->asset_code, $moved->room_id]);
        $this->assertEqualsWithDelta(59.0, $moved->pos_x, 0.01, 'pindah ruang: titik dihitung ulang di ruang baru');

        $this->post(route('ac-units.store'), ['asset_code' => 'AC-0001'] + $data)->assertSessionHasErrors('asset_code');
        $this->post(route('ac-units.store'), ['capacity_pk' => '0'] + $data)->assertSessionHasErrors('capacity_pk');
        $this->post(route('ac-units.store'), ['last_wash_at' => today()->addDay()->toDateString()] + $data)->assertSessionHasErrors(['last_wash_at' => 'Tanggal cuci terakhir tidak boleh setelah hari ini.']);
        $this->post(route('ac-units.store'), ['pos_x' => '10'] + $data)->assertSessionHasErrors('pos_y');
    }

    public function test_vendor_is_created_with_an_automatic_code_and_validates_whatsapp_and_email(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('ac-vendors.store'), ['name' => 'CV Sejuk Contoh', 'phone' => '0812 3456 7890', 'email' => 'sejuk@example.test'])->assertRedirect();
        $this->assertSame('VND-001', AcVendor::first()->code);
        $this->post(route('ac-vendors.store'), ['name' => 'B', 'phone' => '12'])->assertSessionHasErrors('phone');
        $this->post(route('ac-vendors.store'), ['name' => 'B', 'email' => 'salah'])->assertSessionHasErrors('email');
        $this->put(route('ac-vendors.update', AcVendor::first()), ['name' => 'CV Sejuk Baru'])->assertRedirect();
        $this->assertSame(['VND-001', 'CV Sejuk Baru'], [AcVendor::first()->code, AcVendor::first()->name]);
    }

    public function test_deactivation_is_blocked_while_children_are_still_active(): void
    {
        $this->actingAs($this->admin);
        $area = $this->area();
        $room = $this->room($area);
        $unit = $this->unit($room);

        $this->postJson(route('ac-areas.toggle', $area))->assertStatus(422);
        $this->postJson(route('ac-rooms.toggle', $room))->assertStatus(422);
        $this->postJson(route('ac-units.toggle', $unit))->assertOk()->assertJson(['active' => false]);
        $this->postJson(route('ac-rooms.toggle', $room))->assertOk()->assertJson(['active' => false]);
        $this->postJson(route('ac-areas.toggle', $area))->assertOk()->assertJson(['active' => false]);
        $this->postJson(route('ac-areas.toggle', $area))->assertOk()->assertJson(['active' => true]);
        $this->put(route('ac-rooms.update', $room), ['_action' => 'activate'])->assertRedirect();
        $this->assertTrue($room->fresh()->is_active);
    }

    public function test_screens_render_and_access_follows_the_group(): void
    {
        $area = $this->area();
        $room = $this->room($area);
        $unit = $this->unit($room);
        $vendor = AcVendor::create(['code' => 'VND-001', 'name' => 'CV Contoh']);
        $urls = [
            route('ac-units.index'), route('ac-units.create'), route('ac-units.show', $unit), route('ac-areas.index'), route('ac-areas.create'), route('ac-areas.show', $area),
            route('ac-rooms.index'), route('ac-rooms.create'), route('ac-rooms.show', $room), route('ac-vendors.index'), route('ac-vendors.create'), route('ac-vendors.show', $vendor),
        ];
        $this->actingAs($this->admin);
        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
        $this->get(route('ac-units.index'))->assertSee('AC-0001')->assertSee('Daikin');

        $ro = $this->user(['GAK' => 'ro']);
        $this->actingAs($ro)->get(route('ac-units.index'))->assertOk();
        $this->post(route('ac-units.store'), ['room_id' => $room->id, 'brand' => 'LG', 'capacity_pk' => 1])->assertForbidden();   // hanya-baca
        $this->get(route('ac-areas.index'))->assertForbidden();   // modul lain tidak diberikan
        $this->get(route('ac-vendors.index'))->assertForbidden();

        $rw = $this->user(['GAE' => 'rw']);
        $this->actingAs($rw)->post(route('ac-vendors.store'), ['name' => 'V Baru'])->assertRedirect();
        $this->get(route('ac-units.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('ac-units.index'))->assertForbidden();   // peran biasa tanpa Grup: GA tidak termasuk bawaan
    }
}
