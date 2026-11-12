<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Grid master yang dibuka lewat hub Setup (Kendaraan, AC) punya tombol Kembali ke hub-nya, dan Pengaturan GA kembali ke hub asalnya (?dari=). Layar yang berdiri sendiri di menu tidak diberi tombol.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class SetupBackButtonTest extends TestCase
{
    use RefreshDatabase;

    private function backTo(string $url, string $to): void
    {
        $html = $this->actingAs(User::factory()->administrator()->create())->get($url)->assertOk()->getContent();
        $this->assertStringContainsString('&quot;back&quot;:&quot;'.$to.'&quot;', $html, "Kembali di {$url} harus ke {$to}");
    }

    public function test_setup_grids_go_back_to_their_hub(): void
    {
        foreach (['vehicles.index', 'vehicle-types.index'] as $r) {
            $this->backTo(route($r), route('fleet.setup'));
        }
        foreach (['ac-units.index', 'ac-areas.index', 'ac-rooms.index', 'ac-vendors.index'] as $r) {
            $this->backTo(route($r), route('ac-setup.index'));
        }
    }

    public function test_ga_settings_goes_back_to_the_hub_it_was_opened_from(): void
    {
        $this->backTo(route('ga-settings.edit', ['dari' => 'ac']), route('ac-setup.index'));
        $this->backTo(route('ga-settings.edit', ['dari' => 'fleet']), route('fleet.setup'));
        $this->backTo(route('ga-settings.edit', ['dari' => 'x']), route('home'));
        $this->get(route('fleet.setup'))->assertSee('ga-settings?dari=fleet', false);
        $this->get(route('ac-setup.index'))->assertSee('ga-settings?dari=ac', false);
    }

    public function test_saving_ga_settings_keeps_the_hub(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->put(route('ga-settings.update', ['dari' => 'ac']), [])->assertSessionHasErrors();
        $this->put(route('ga-settings.update', ['dari' => 'ac']), [
            'vehicle_soon_days' => 30, 'vehicle_soon_km' => '1000', 'ac_wash_months' => 3, 'ac_major_months' => 12, 'ac_soon_days' => 30,
            'ac_days' => [1, 2], 'ac_start_time' => '09:00', 'ac_end_time' => '16:00', 'ac_capacity' => 3, 'ac_pic_notice_time' => '15:00',
        ])->assertRedirect(route('ga-settings.edit', ['dari' => 'ac']));
    }

    public function test_stand_alone_screens_have_no_extra_back_button(): void
    {
        $html = $this->actingAs(User::factory()->administrator()->create())->get(route('products.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('fa-arrow-left', $html);
    }
}
