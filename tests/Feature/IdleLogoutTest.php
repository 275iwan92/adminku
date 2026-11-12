<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga logout otomatis (x-dl2::idle-logout): terpasang di halaman berlogin dengan menit = umur sesi server, tidak di halaman tamu; rute session.ping 204 untuk pengguna aktif, ditolak untuk tamu dan akun nonaktif.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class IdleLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_in_pages_mount_the_component_with_the_session_lifetime(): void
    {
        config(['session.lifetime' => 45]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('help.user-guide'))->assertOk()
            ->assertSee('data-dl2="idle-logout"', false)->assertSee('&quot;minutes&quot;:45', false)
            ->assertSee('&quot;logoutUrl&quot;:&quot;'.route('logout').'&quot;', false)->assertSee('&quot;pingUrl&quot;:&quot;'.route('session.ping').'&quot;', false)
            ->assertSee('&quot;loginUrl&quot;:&quot;'.route('login').'&quot;', false)->assertSee('name="csrf-token"', false);
        $this->assertSame(1, substr_count($this->actingAs($user)->get(route('home'))->getContent(), 'data-dl2="idle-logout"'));
    }

    public function test_guest_pages_do_not_mount_the_component(): void
    {
        foreach (['login', 'password.request'] as $name) {
            $this->get(route($name))->assertOk()->assertDontSee('data-dl2="idle-logout"', false);
        }
    }

    public function test_logout_is_post_only(): void
    {
        $this->actingAs(User::factory()->create())->get(route('logout'))->assertStatus(405);
    }

    public function test_ping_answers_204_without_body_for_a_logged_in_user_and_touches_the_session(): void
    {
        foreach ([User::factory(), User::factory()->supervisor(), User::factory()->administrator()] as $factory) {
            $res = $this->actingAs($factory->create())->get(route('session.ping'))->assertNoContent();
            $this->assertSame('', $res->getContent());
            $this->assertNotEmpty($res->headers->getCookies());   // StartSession memperbarui cookie sesi di tiap permintaan = sesi diperpanjang
        }
    }

    public function test_ping_is_rejected_for_guests(): void
    {
        $this->getJson(route('session.ping'))->assertUnauthorized();
        $this->get(route('session.ping'))->assertRedirect(route('login'));
    }

    public function test_ping_is_rejected_for_a_deactivated_user(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)->getJson(route('session.ping'))->assertForbidden();
        $this->assertGuest();
    }
}
