<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Department;
use App\Models\Location;
use App\Models\Recipient;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga aturan master hasil uji kemudahan: nama unik (tanpa membedakan huruf besar / kecil dan spasi ujung) di form penuh dan tambah-cepat untuk Supplier, Departemen, Kategori, Lokasi, Satuan, Penerima;
 *                kode lokasi tanpa spasi; kode Supplier dan Departemen terisi otomatis bila kosong. Nama Barang sengaja TIDAK unik.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class MasterRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $sup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sup = User::factory()->supervisor()->create();
        $this->actingAs($this->sup);
    }

    public function test_supplier_name_must_be_unique_ignoring_case_and_trailing_spaces(): void
    {
        Supplier::create(['code' => 'S1', 'name' => 'Toko Maju']);

        $this->post(route('suppliers.store'), ['code' => 'S2', 'name' => ' TOKO MAJU  '])->assertSessionHasErrors('name');
        $this->assertFalse(Supplier::where('code', 'S2')->exists());
    }

    public function test_a_supplier_can_be_saved_again_with_its_own_name(): void
    {
        $s = Supplier::create(['code' => 'S1', 'name' => 'Toko Maju']);

        $this->put(route('suppliers.update', $s), ['code' => 'S1', 'name' => 'Toko Maju'])->assertSessionHasNoErrors();
    }

    public function test_department_name_must_be_unique_in_the_full_form(): void
    {
        Department::create(['code' => 'MNT', 'name' => 'Maintenance']);

        $this->post(route('departments.store'), ['code' => 'D2', 'name' => 'maintenance'])->assertSessionHasErrors('name');
    }

    public function test_category_name_must_be_unique_in_the_full_form(): void
    {
        Category::create(['code' => 'ATK', 'name' => 'ATK (Alat Tulis Kantor)']);

        $this->post(route('categories.store'), ['code' => 'ZZZ', 'name' => 'atk (alat tulis kantor)'])->assertSessionHasErrors('name');
    }

    public function test_location_name_must_be_unique_in_the_full_form(): void
    {
        Location::create(['code' => 'A1-01', 'name' => 'Rak A1-01']);

        $this->post(route('locations.store'), ['code' => 'Z9', 'name' => 'RAK A1-01'])->assertSessionHasErrors('name');
    }

    public function test_location_code_cannot_contain_spaces(): void
    {
        $this->post(route('locations.store'), ['code' => 'Z 9', 'name' => 'Rak Z9'])->assertSessionHasErrors('code');
        $this->post(route('locations.store'), ['code' => 'Z9', 'name' => 'Rak Z9'])->assertSessionHasNoErrors();
    }

    public function test_unit_name_must_be_unique_in_the_full_form(): void
    {
        Unit::create(['code' => 'bx', 'name' => 'Buah']);

        $this->post(route('units.store'), ['code' => 'bh', 'name' => 'buah'])->assertSessionHasErrors('name');
    }

    public function test_recipient_name_must_be_unique_in_the_full_form_and_quick_add(): void
    {
        Recipient::create(['name' => 'Budi (Teknisi)']);

        $this->post(route('recipients.store'), ['name' => 'budi (teknisi)'])->assertSessionHasErrors('name');
        $this->postJson(route('recipients.store'), ['name' => 'BUDI (TEKNISI)'])->assertStatus(422);
    }

    public function test_item_names_are_not_unique(): void
    {
        $cat = Category::create(['code' => 'ATK', 'name' => 'ATK']);
        $payload = ['name' => 'Pulpen Hitam', 'category_id' => $cat->id, 'unit' => 'pcs', 'unit_price' => 1, 'reorder_point' => 0, 'reorder_qty' => 0, 'lead_time_days' => 0];

        $this->post(route('products.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('products.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame(2, \App\Models\Item::where('name', 'Pulpen Hitam')->count());
    }

    public function test_supplier_code_is_filled_automatically_when_left_empty(): void
    {
        Supplier::create(['code' => 'SUP-004', 'name' => 'Lama']);

        $this->post(route('suppliers.store'), ['code' => '', 'name' => 'Toko Baru'])->assertSessionHasNoErrors();

        $this->assertSame('SUP-005', Supplier::where('name', 'Toko Baru')->value('code'));
    }

    public function test_new_supplier_form_offers_the_next_code_as_the_default(): void
    {
        Supplier::create(['code' => 'SUP-004', 'name' => 'Lama']);

        $this->get(route('suppliers.create'))->assertOk()->assertSee('value="SUP-005"', false);
    }

    public function test_department_code_is_filled_automatically_when_left_empty(): void
    {
        $this->post(route('departments.store'), ['code' => '', 'name' => 'Produksi Utama'])->assertSessionHasNoErrors();

        $this->assertSame('PRODUKSIUTAM', Department::where('name', 'Produksi Utama')->value('code'));
    }

    public function test_new_department_form_offers_a_code_as_the_default(): void
    {
        $this->get(route('departments.create'))->assertOk()->assertSee('value="DEP-001"', false);
    }
}
