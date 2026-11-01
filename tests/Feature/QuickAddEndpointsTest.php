<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Department;
use App\Models\Location;
use App\Models\Recipient;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga endpoint JSON "tambah cepat" (x-dl::field.select add): balasan 201 {id,name}, galat 422 standar, nama kembar ditolak (kecuali Penerima), dan hak akses sama dengan master-nya
 *                (Supplier / Penerima / Departemen: Data Entry boleh; Kategori / Lokasi / Satuan: hanya Supervisor; grup tanpa modul atau hanya RO ditolak).
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class QuickAddEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_is_added_with_an_automatic_code_and_no_region(): void
    {
        DB::table('geo_country')->insert(['id_country' => 1, 'country_name' => 'Indonesia']);   // data wilayah ada, tetapi jalur cepat tidak menanyakannya
        $this->actingAs(User::factory()->create());

        $first = $this->postJson(route('suppliers.store'), ['name' => 'CV Cepat', 'phone' => '031555', 'contact_name' => 'Budi'])->assertCreated()->json();
        $second = $this->postJson(route('suppliers.store'), ['name' => 'PT Kedua'])->assertCreated()->json();

        $this->assertSame(['id', 'name'], array_keys($first));
        $this->assertSame('CV Cepat', $first['name']);
        $this->assertSame(['SUP-001', 'SUP-002'], [Supplier::find($first['id'])->code, Supplier::find($second['id'])->code]);
        $this->assertSame(['031555', 'Budi', null], [Supplier::find($first['id'])->phone, Supplier::find($first['id'])->contact_name, Supplier::find($first['id'])->area_id]);
        $this->assertFalse(Supplier::find($first['id'])->is_system);
    }

    public function test_supplier_validation_and_duplicate_name(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson(route('suppliers.store'), ['name' => 'Sama'])->assertCreated();

        $this->postJson(route('suppliers.store'), ['name' => ''])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson(route('suppliers.store'), ['name' => 'Sama'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson(route('suppliers.store'), ['name' => 'Kontan'])->assertUnprocessable()->assertJsonValidationErrors('name');   // nama supplier sistem juga terpakai
    }

    public function test_the_normal_supplier_form_still_requires_its_fields(): void
    {
        $this->actingAs(User::factory()->create())->post(route('suppliers.store'), ['code' => '', 'name' => ''])->assertSessionHasErrors(['name'])->assertSessionDoesntHaveErrors('code');   // kode kosong terisi otomatis
    }

    public function test_department_and_recipient_reply_with_id_and_name(): void
    {
        $this->actingAs(User::factory()->create());

        $dept = $this->postJson(route('departments.store'), ['name' => 'Gudang Utama'])->assertCreated()->json();
        $rec = $this->postJson(route('recipients.store'), ['name' => 'Rina'])->assertCreated()->json();

        $this->assertSame(['id', 'name'], array_keys($dept));
        $this->assertSame(['id', 'name'], array_keys($rec));
        $this->assertSame('GUDANGUTAMA', Department::find($dept['id'])->code);
        $this->assertNotNull(Recipient::find($rec['id']));
        $this->postJson(route('departments.store'), ['name' => 'Gudang Utama'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson(route('departments.store'), ['name' => ''])->assertUnprocessable();
        $this->postJson(route('recipients.store'), ['name' => ''])->assertUnprocessable();
        $this->postJson(route('recipients.store'), ['name' => 'rina '])->assertUnprocessable()->assertJsonValidationErrors('name');   // nama penerima unik (tanpa beda huruf besar / spasi ujung)
    }

    public function test_supervisor_adds_category_location_and_unit(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());

        $cat = $this->postJson(route('categories.store'), ['code' => 'ELK', 'name' => 'Elektronik'])->assertCreated()->json();
        $loc = $this->postJson(route('locations.store'), ['code' => 'rak-a1', 'name' => 'Rak A1'])->assertCreated()->json();
        $unit = $this->postJson(route('units.store'), ['code' => 'uji1', 'name' => 'Satuan Uji'])->assertCreated()->json();

        $this->assertSame(['id', 'name'], array_keys($cat));
        $this->assertSame(Category::where('code', 'ELK')->value('id'), $cat['id']);
        $this->assertSame(['RAK-A1', 'RAK-A1 · Rak A1'], [$loc['id'], $loc['name']]);   // id = kunci dropdown (kode), name = labelnya
        $this->assertSame(['uji1', 'uji1 · Satuan Uji'], [$unit['id'], $unit['name']]);
        $this->assertSame('count', Unit::where('code', 'uji1')->value('kind'));
        $this->assertTrue(Location::where('code', 'RAK-A1')->value('is_active') && Unit::where('code', 'uji1')->value('is_active'));
    }

    public function test_category_location_and_unit_validate_and_reject_duplicate_names(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());
        $this->postJson(route('categories.store'), ['code' => 'ELK', 'name' => 'Elektronik'])->assertCreated();
        $this->postJson(route('locations.store'), ['code' => 'A1', 'name' => 'Rak A1'])->assertCreated();
        $this->postJson(route('units.store'), ['code' => 'uji1', 'name' => 'Satuan Uji'])->assertCreated();

        $this->postJson(route('categories.store'), ['code' => 'XY', 'name' => ''])->assertUnprocessable()->assertJsonValidationErrors(['code', 'name']);
        $this->postJson(route('categories.store'), ['code' => 'ELK', 'name' => 'Lain'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson(route('categories.store'), ['code' => 'LAI', 'name' => 'Elektronik'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson(route('locations.store'), ['code' => 'A2', 'name' => 'Rak A1'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson(route('locations.store'), ['code' => '', 'name' => ''])->assertUnprocessable()->assertJsonValidationErrors(['code', 'name']);
        $this->postJson(route('units.store'), ['code' => 'lbr', 'name' => 'Satuan Uji'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson(route('units.store'), ['code' => 'Bad Code', 'name' => 'X'])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_data_entry_cannot_add_category_location_or_unit(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson(route('categories.store'), ['code' => 'ELK', 'name' => 'Elektronik'])->assertForbidden();
        $this->postJson(route('locations.store'), ['code' => 'A1', 'name' => 'Rak A1'])->assertForbidden();
        $this->postJson(route('units.store'), ['code' => 'uji1', 'name' => 'Satuan Uji'])->assertForbidden();
        $this->assertSame(0, Category::where('code', 'ELK')->count() + Location::count() + Unit::where('code', 'uji1')->count());
    }

    public function test_group_without_the_module_or_read_only_is_rejected(): void
    {
        $group = UserGroup::create(['code' => 'VIEW', 'name' => 'Viewer']);
        $group->syncAccess(['SUP' => 'ro', 'DEP' => 'ro', 'RCP' => 'ro', 'ISS' => 'rw']);
        $this->actingAs(User::factory()->create(['group_id' => $group->id]));

        $this->postJson(route('suppliers.store'), ['name' => 'X'])->assertForbidden();
        $this->postJson(route('departments.store'), ['name' => 'X'])->assertForbidden();
        $this->postJson(route('recipients.store'), ['name' => 'X'])->assertForbidden();

        $none = UserGroup::create(['code' => 'NONE', 'name' => 'Tanpa']);
        $none->syncAccess(['ISS' => 'rw']);
        $this->actingAs(User::factory()->supervisor()->create(['group_id' => $none->id]));
        $this->postJson(route('categories.store'), ['code' => 'ELK', 'name' => 'Elektronik'])->assertForbidden();
        $this->postJson(route('units.store'), ['code' => 'uji1', 'name' => 'Satuan Uji'])->assertForbidden();
    }

    public function test_guests_are_rejected(): void
    {
        $this->postJson(route('suppliers.store'), ['name' => 'X'])->assertUnauthorized();
    }
}
