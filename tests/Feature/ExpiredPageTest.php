<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga layar 419 (formulir kedaluwarsa) tidak lagi menjebak pengguna: tamu dikembalikan ke halaman masuk dengan pesan, pengguna masuk ke halaman asalnya, keluar tetap ke halaman masuk, JSON tidak diubah.
 * Sumber hulu  : bootstrap/app.php (render HttpException 419).
 * Status       : Aktif.
 */
class ExpiredPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware('web')->post('/_t419', fn () => abort(419));
        Route::middleware('web')->post('/_t419-json', fn () => abort(419))->name('t419json');
    }

    public function test_a_guest_with_an_expired_form_goes_back_to_login_with_a_message(): void
    {
        $this->from(route('login'))->post('/_t419', ['login' => 'budi', 'password' => 'rahasia'])
            ->assertRedirect(route('login'))->assertSessionHas('error', 'Halaman sudah kedaluwarsa. Silakan coba lagi.')->assertSessionMissing('_old_input.password');
        $this->followingRedirects()->get(route('login'))->assertOk()->assertSee('kedaluwarsa');
    }

    public function test_a_signed_in_user_returns_to_the_page_they_came_from(): void
    {
        $this->actingAs(User::factory()->administrator()->create())->from(route('products.index'))->post('/_t419')
            ->assertRedirect(route('products.index'))->assertSessionHas('error');
    }

    public function test_json_requests_keep_the_419_status(): void
    {
        $this->postJson('/_t419-json')->assertStatus(419);
    }

    public function test_login_pages_keep_their_token_fresh(): void
    {
        $this->get(route('login'))->assertOk()->assertSee(route('login.token'), false)->assertSee('visibilitychange', false);
        $this->get(route('login.token'))->assertOk()->assertJsonStructure(['token'])->assertHeader('Cache-Control', 'no-store, private');
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36')->get(route('login'))->assertOk()->assertSee(route('login.token'), false);
    }
}
