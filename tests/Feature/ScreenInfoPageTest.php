<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Halaman ukur layar publik /ukur: tanpa login, tanpa data pengguna, satu komponen DevLib x-dl2::screen-info.
class ScreenInfoPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_opens_the_page_without_login_and_without_menu(): void
    {
        $html = $this->get('/ukur')->assertOk()->getContent();
        $this->assertStringContainsString('data-dl2-screen-info', $html);
        $this->assertStringContainsString('data-sinfo-copy', $html);
        $this->assertStringNotContainsString('dl-phone__burger', $html);
        $this->assertStringNotContainsString('dl-layer', $html);
        $this->assertGuest();
    }

    public function test_page_has_no_user_or_server_data_even_when_logged_in(): void
    {
        $user = User::factory()->create(['name' => 'Zed Pengukur Unik', 'username' => 'zedukur']);
        $html = $this->actingAs($user)->get('/ukur')->assertOk()->getContent();
        $this->assertStringNotContainsString('Zed Pengukur Unik', $html);
        $this->assertStringNotContainsString('zedukur', $html);
        $this->assertStringNotContainsString((string) $user->email, $html);
    }

    public function test_phone_gets_the_cover_viewport_and_reports_the_view_it_was_served(): void
    {
        $ua = 'Mozilla/5.0 (Linux; Android 15; CPH2700) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36';
        $html = $this->withHeader('User-Agent', $ua)->get('/ukur')->assertOk()->getContent();
        $this->assertStringContainsString('viewport-fit=cover', $html);
        $this->assertStringContainsString('data-view="phone"', $html);
        $this->assertStringContainsString('data-view-source="user-agent"', $html);
        $this->assertStringContainsString('data-os="android"', $html);
    }
}
