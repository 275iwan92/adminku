<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga bahwa login tidak punya "ingat saya": kotak tidak tampil dan masukan remember yang dikirim paksa diabaikan (tanpa remember_token dan cookie recaller).
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class NoRememberMeTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_has_no_remember_checkbox(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('data-dl2="login-box"', false)->assertSee('&quot;usernameName&quot;:&quot;login&quot;', false)->assertDontSee('name="remember"', false);
    }

    public function test_forced_remember_input_is_ignored(): void
    {
        $user = User::factory()->create(['username' => 'budi', 'remember_token' => null]);

        $res = $this->post(route('login.attempt'), ['login' => 'budi', 'password' => 'password', 'remember' => '1']);

        $res->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->remember_token);
        $this->assertEmpty(collect($res->headers->getCookies())->filter(fn ($c) => str_starts_with($c->getName(), 'remember_web_')));
    }
}
