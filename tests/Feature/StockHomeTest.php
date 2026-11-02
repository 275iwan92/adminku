<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga Beranda Stok (modul STB): memuat isi Beranda lama (angka stok, peringatan), tanpa banner hero, dijaga hak modul STB, dan di HP tampil sebagai ubin ringkasan stok.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class StockHomeTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = 'Mozilla/5.0 (Linux; Android 15; CPH2700) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36';

    public function test_desktop_shows_old_home_content_without_hero(): void
    {
        foreach ([User::factory()->create(), User::factory()->supervisor()->create(), User::factory()->administrator()->create()] as $user) {
            $html = $this->actingAs($user)->get(route('stock.home'))->assertOk()->assertSee('Barang aktif')->assertSee('Penerimaan hari ini')->getContent();
            $this->assertStringNotContainsString('sstock-hero', $html);
        }
        $this->actingAs(User::factory()->supervisor()->create())->get(route('stock.home'))->assertSee('Nilai stok')->assertSee('Dokumen menunggu persetujuan');
        $this->actingAs(User::factory()->create())->get(route('stock.home'))->assertDontSee('Nilai stok');
    }

    public function test_needs_the_stb_module(): void
    {
        $without = UserGroup::create(['code' => 'GAONLY', 'name' => 'Petugas GA']);
        $without->syncAccess(['GVM' => 'rw']);
        $this->actingAs(User::factory()->create(['group_id' => $without->id]))->get(route('stock.home'))->assertForbidden();

        $with = UserGroup::create(['code' => 'STK', 'name' => 'Petugas Stok']);
        $with->syncAccess(['STB' => 'ro']);
        $this->actingAs(User::factory()->create(['group_id' => $with->id]))->get(route('stock.home'))->assertOk();
    }

    public function test_phone_shows_stock_tiles_and_cari_barang(): void
    {
        $html = $this->actingAs(User::factory()->create())->withHeader('User-Agent', self::PHONE)->get(route('stock.home'))->assertOk()->assertSee('Barang aktif')->assertSee('Cari Barang')->getContent();
        $this->assertStringNotContainsString('sstock-hero', $html);
    }

    public function test_migration_gives_stb_to_groups_with_items(): void
    {
        $group = UserGroup::create(['code' => 'ITMG', 'name' => 'Barang']);
        $group->syncAccess(['ITM' => 'ro']);
        $other = UserGroup::create(['code' => 'GAG', 'name' => 'GA']);
        $other->syncAccess(['GVM' => 'rw']);

        (require database_path('migrations/2026_11_21_100000_give_stock_home_to_groups.php'))->up();

        $this->assertSame('ro', $group->fresh()->moduleAccess()['STB'] ?? null);
        $this->assertArrayNotHasKey('STB', $other->fresh()->moduleAccess());
    }
}
