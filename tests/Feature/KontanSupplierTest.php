<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga supplier sistem Kontan: ada sesudah migrasi dan seeder (idempoten), selalu pertama di dropdown supplier, tidak bisa dinonaktifkan / diganti nama / dihapus.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class KontanSupplierTest extends TestCase
{
    use RefreshDatabase;

    private function kontan(): Supplier
    {
        return Supplier::where('code', 'KONTAN')->firstOrFail();
    }

    public function test_kontan_exists_after_migration_and_seeding_is_idempotent(): void
    {
        $this->assertTrue($this->kontan()->is_system);
        $this->assertTrue($this->kontan()->is_active);
        $this->assertSame('Kontan', $this->kontan()->name);

        (new SupplierSeeder)->run();
        (new SupplierSeeder)->run();

        $this->assertSame(1, Supplier::where('code', 'KONTAN')->count());
        $this->assertSame(1, Supplier::where('is_system', true)->count());
    }

    public function test_migration_marks_an_existing_kontan_without_touching_other_data(): void
    {
        DB::table('m_supplier')->where('code', 'KONTAN')->update(['is_system' => false, 'name' => 'Kontan Lama']);
        Supplier::create(['code' => 'S1', 'name' => 'Lain']);

        Schema::rename('m_supplier', 'suppliers');   // migrasi lama berjalan sebelum rename m_, jadi memakai nama lama
        (require base_path('database/migrations/2026_11_13_100000_add_system_supplier_kontan.php'))->up();
        Schema::rename('suppliers', 'm_supplier');

        $this->assertTrue($this->kontan()->is_system);
        $this->assertSame('Kontan Lama', $this->kontan()->name);
        $this->assertFalse(Supplier::where('code', 'S1')->firstOrFail()->is_system);
        $this->assertSame(1, Supplier::where('code', 'KONTAN')->count());
    }

    public function test_kontan_is_first_in_every_supplier_dropdown(): void
    {
        Supplier::create(['code' => 'A1', 'name' => 'Aaa Pertama Abjad']);
        $user = User::factory()->supervisor()->create();
        $first = fn (string $html) => preg_match('/data-value="(\d+)"/', substr($html, strpos($html, 'name="supplier_id"')), $m) ? (int) $m[1] : null;

        $this->actingAs($user);
        $this->assertSame($this->kontan()->id, $first($this->get(route('docs.create', 'receiving'))->assertOk()->getContent()));
        $this->assertSame($this->kontan()->id, $first($this->get(route('reports.receiving'))->assertOk()->getContent()));
        $this->assertSame($this->kontan()->id, Supplier::forSelect()->first()->id);
    }

    public function test_kontan_cannot_be_deactivated_renamed_or_deleted(): void
    {
        $kontan = $this->kontan();
        $this->actingAs(User::factory()->supervisor()->create());

        $this->postJson(route('suppliers.toggle', $kontan))->assertStatus(422)->assertJson(['message' => Supplier::SYSTEM_MESSAGE]);
        $this->put(route('suppliers.update', $kontan), ['_action' => 'deactivate'])->assertSessionHasErrors('_action');
        $this->put(route('suppliers.update', $kontan), ['code' => 'KONTAN', 'name' => 'Tunai', 'area' => ''])->assertSessionHasErrors('name');
        $this->put(route('suppliers.update', $kontan), ['code' => 'LAIN', 'name' => 'Kontan', 'area' => ''])->assertSessionHasErrors('code');

        $this->assertTrue($kontan->fresh()->is_active);
        $this->assertSame('Kontan', $kontan->fresh()->name);
        $this->expectException(LogicException::class);
        $kontan->delete();
    }

    public function test_other_suppliers_still_toggle_and_kontan_form_hides_deactivate(): void
    {
        $user = User::factory()->supervisor()->create();
        $other = Supplier::create(['code' => 'S1', 'name' => 'Biasa']);

        $this->actingAs($user)->get(route('suppliers.show', $this->kontan()))->assertOk()->assertDontSee('data-f-act="deactivate"', false);
        $this->get(route('suppliers.show', $other))->assertOk()->assertSee('data-f-act="deactivate"', false);
        $this->postJson(route('suppliers.toggle', $other))->assertOk()->assertJson(['active' => false]);
    }
}
