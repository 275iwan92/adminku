<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Company;
use App\Support\Constants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga Beranda: banner hero (sapaan dengan nama depan, tanggal) di desktop dan HP, kartu cuaca hanya bila layar Perusahaan punya tempat (detail banner: HomeHeroTest),
 *                dan baris jaringan x-dl2::net-line di bawah nama (desktop dan daftar Menu HP) untuk semua pengguna, dengan rute ping DevLib tanpa sesi (bukan session.ping yang memperpanjang sesi).
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class HomeGreetingTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = 'Mozilla/5.0 (Linux; Android 15; CPH2700) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36';

    /** Kerangka desktop (Layer Shell, route home): menu kiri, baris jaringan, label versi; isi Beranda ada di route home.board (home()). */
    private function shell()
    {
        return $this->actingAs(User::factory()->create(['name' => 'Budi Santoso']))->get(route('home'));
    }

    private function home(string $role = 'data_entry', ?string $ua = null)
    {
        $user = $role === 'supervisor' ? User::factory()->supervisor()->create(['name' => 'Budi Santoso']) : User::factory()->create(['name' => 'Budi Santoso']);

        return $this->actingAs($user)->withHeader('User-Agent', $ua ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/141.0.0.0 Safari/537.36')->get(str_contains((string) $ua, 'Mobile') ? route('home') : route('home.board'))->assertOk();
    }

    public function test_desktop_home_has_the_hero_banner_with_the_first_name_and_a_date_line(): void
    {
        $html = $this->home()->getContent();

        $this->assertStringContainsString('class="sstock-hero"', $html);
        $this->assertMatchesRegularExpression('/Selamat (pagi|siang|sore|malam)<\/span>, Budi</', $html);
        $this->assertStringContainsString('data-hero-date', $html);
        $this->assertStringNotContainsString('👋', $html);
        $this->assertStringNotContainsString('data-dl2-weather', $html, 'tanpa kota di layar Perusahaan kartu cuaca tidak dirender');
    }

    public function test_the_weather_card_is_rendered_only_when_the_company_has_a_place(): void
    {
        $this->assertStringNotContainsString('data-dl2="weather"', $this->home()->getContent());

        Constants::set('company.weather_city', 'Malang', 'company');
        Constants::set('company.weather_lat', '-7.98', 'company');
        Constants::set('company.weather_lon', '112.63', 'company');
        Constants::forget();
        $html = $this->home()->getContent();

        $this->assertStringContainsString('data-dl2="weather"', $html);
        $this->assertStringContainsString('&quot;city&quot;:&quot;Malang&quot;', $html);
        $this->assertStringContainsString('&quot;lat&quot;:-7.98', $html);
        $this->assertStringContainsString('&quot;lon&quot;:112.63', $html);
        $this->assertSame(['city' => 'Malang', 'lat' => -7.98, 'lon' => 112.63], Company::weather());
    }

    public function test_phone_home_has_the_same_hero_banner(): void
    {
        $html = $this->home('data_entry', self::PHONE)->getContent();

        $this->assertStringContainsString('class="sstock-hero"', $html);
        $this->assertMatchesRegularExpression('/Selamat (pagi|siang|sore|malam)<\/span>, Budi</', $html);
    }
    public function test_every_user_gets_the_network_line_on_desktop_and_in_the_phone_menu_with_the_devlib_ping(): void
    {
        $desktop = $this->shell()->getContent();
        $this->assertStringContainsString('&quot;netLine&quot;:{', $desktop);   // Layer Shell v2 menggambar net-line di kaki panel kiri dari konfigurasi
        $this->assertStringContainsString('&quot;pingUrl&quot;:&quot;'.route('devlib.ping').'&quot;', $desktop);   // ping tanpa sesi: session.ping memperpanjang sesi (idle-logout memakainya sendiri)

        $this->assertStringContainsString('&quot;netLine&quot;:{', $this->home('supervisor', self::PHONE)->getContent());   // phone-shell: opsi netLine dikirim; penggambarannya di lembar Menu menunggu dukungan phone-shell DevLib 2
    }

    public function test_the_devlib_ping_route_answers_without_a_session_so_it_cannot_extend_it(): void
    {
        $r = $this->get('/devlib/ping');

        $r->assertNoContent();
        $this->assertEmpty($r->headers->getCookies());
    }

    public function test_the_fallback_place_is_validated_and_accepts_a_decimal_comma(): void
    {
        $sup = User::factory()->administrator()->create();
        $base = ['name' => 'Maju Jaya', 'kop_style' => 'name'];

        $this->actingAs($sup)->put(route('company.update'), $base + ['weather_lat' => '95', 'weather_lon' => '112'])->assertSessionHasErrors('weather_lat');
        $this->actingAs($sup)->put(route('company.update'), $base + ['weather_lat' => '-7,98'])->assertSessionHasErrors('weather_lon');
        $this->actingAs($sup)->put(route('company.update'), $base + ['weather_lat' => 'abc', 'weather_lon' => '112'])->assertSessionHasErrors('weather_lat');

        $this->actingAs($sup)->put(route('company.update'), $base + ['weather_city' => 'Malang', 'weather_lat' => '-7,98', 'weather_lon' => '112.63'])->assertSessionHasNoErrors();
        Constants::forget();
        $this->assertSame(['city' => 'Malang', 'lat' => -7.98, 'lon' => 112.63], Company::weather());

        $this->actingAs($sup)->put(route('company.update'), $base)->assertSessionHasNoErrors();
        Constants::forget();
        $this->assertNull(Company::weather());
    }

    public function test_the_version_label_is_in_the_logo_title_and_under_the_login_form(): void
    {
        $count = trim((string) shell_exec('git -C '.escapeshellarg(base_path()).' rev-list --count HEAD'));
        $this->assertMatchesRegularExpression('/^\d+$/', $count);

        $home = $this->shell()->getContent();
        $this->assertStringContainsString('&quot;version&quot;:&quot;ver 0.'.$count.' A&quot;', $home);   // label versi di kaki Layer Shell (stage alpha = huruf A)

        $this->post('/logout');
        $login = $this->get(route('login'))->getContent();
        $this->assertStringContainsString('data-dl2="env-badge"', $login);
        $this->assertStringContainsString('ver 0.'.$count, $login);
    }
}
