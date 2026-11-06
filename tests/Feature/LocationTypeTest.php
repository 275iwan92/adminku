<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga jenis lokasi penyimpanan: tersimpan dari form, hanya nilai yang dikenal, daftar dikelompokkan per jenis.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class LocationTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_type_is_saved_optional_and_validated(): void
    {
        $sup = User::factory()->supervisor()->create();

        $this->actingAs($sup)->post(route('locations.store'), ['code' => 'x1', 'name' => 'Rak X1', 'type' => 'lemari'])->assertSessionHasNoErrors();
        $this->assertSame('lemari', Location::where('code', 'X1')->value('type'));

        $this->actingAs($sup)->post(route('locations.store'), ['code' => 'x2', 'name' => 'Rak X2', 'type' => ''])->assertSessionHasNoErrors();
        $this->assertNull(Location::where('code', 'X2')->value('type'));

        $this->actingAs($sup)->post(route('locations.store'), ['code' => 'x3', 'name' => 'Rak X3', 'type' => 'kapal'])->assertSessionHasErrors('type');

        $loc = Location::where('code', 'X2')->first();
        $this->actingAs($sup)->put(route('locations.update', $loc), ['code' => 'X2', 'name' => 'Rak X2', 'type' => 'area_luas']);
        $this->assertSame('area_luas', $loc->fresh()->type);
    }

    public function test_list_groups_locations_by_type(): void
    {
        Location::create(['code' => 'L1', 'name' => 'Lemari satu', 'type' => 'lemari']);
        Location::create(['code' => 'Z9', 'name' => 'Tanpa jenis']);

        $this->actingAs(User::factory()->supervisor()->create())->get(route('locations.index'))
            ->assertOk()->assertSee('Lemari satu')->assertSee('Belum ditentukan')->assertSee('Kosong');
    }
}
