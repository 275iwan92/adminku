<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Department;
use App\Models\Item;
use App\Models\Recipient;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga bahwa tombol Kembali di SEMUA form menuju grid-nya (bukan layar sebelumnya): tiap form punya tautan Kembali ke grid yang benar, dan konfigurasi back_history mati.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class BackButtonTest extends TestCase
{
    use RefreshDatabase;

    private function backHref(string $html): ?string
    {
        preg_match('/&quot;back&quot;:&quot;([^&]*)&quot;/', $html, $m);   // DevLib 2: tombol Kembali dibangun JS dari konfigurasi form

        return html_entity_decode($m[1] ?? '') ?: null;
    }

    public function test_every_form_back_button_points_to_its_grid(): void
    {
        $this->assertFalse(config('devlib.back_history'));

        $admin = User::factory()->administrator()->create();
        $cat = Category::create(['code' => 'ATK', 'name' => 'ATK']);
        Unit::firstOrCreate(['code' => 'pcs'], ['name' => 'Pcs', 'kind' => 'count']);
        $product = Item::register(['name' => 'Item X', 'category_id' => $cat->id, 'unit' => 'pcs', 'unit_price' => 1], 'X1');
        $forms = [
            route('products.create') => route('products.index'),
            route('products.show', $product) => route('products.index'),
            route('suppliers.create') => route('suppliers.index'),
            route('suppliers.show', Supplier::create(['code' => 'S1', 'name' => 'S'])) => route('suppliers.index'),
            route('units.create') => route('units.index'),
            route('units.show', Unit::first()) => route('units.index'),
            route('departments.create') => route('departments.index'),
            route('departments.show', Department::create(['code' => 'D1', 'name' => 'D'])) => route('departments.index'),
            route('recipients.create') => route('recipients.index'),
            route('recipients.show', Recipient::create(['name' => 'R'])) => route('recipients.index'),
            route('users.create') => route('users.index'),
            route('users.show', $admin) => route('users.index'),
            route('groups.create') => route('groups.index'),
            route('groups.show', UserGroup::create(['code' => 'G1', 'name' => 'G'])) => route('groups.index'),
        ];
        foreach (['opening', 'receiving', 'issuance', 'adjustment'] as $type) {
            $forms[route('docs.create', $type)] = route('docs.index', $type);
        }

        foreach ($forms as $url => $grid) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            $this->assertSame($grid, $this->backHref($html), "Kembali di {$url} harus ke {$grid}");
        }
    }
}
