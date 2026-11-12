<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga merek AdminKU di layar masuk, lupa password, dan kerangka HP (desktop dan ponsel): logo adminku.svg, judul tab AdminKU, tidak ada kata "Stoku", CSS merek dua warna terbangun, dan Beranda HP memakai judul merek.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class BrandScreensTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = 'Mozilla/5.0 (Linux; Android 15; CPH2700) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36';

    private const PC = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36';

    public function test_login_and_forgot_password_show_the_adminku_logo_and_name_on_desktop_and_phone(): void
    {
        foreach ([self::PC, self::PHONE] as $ua) {
            foreach (['login', 'password.request'] as $route) {
                $html = $this->withHeader('User-Agent', $ua)->get(route($route))->assertOk()->getContent();
                $this->assertStringContainsString('ak-logo-badge', $html, "$route ($ua) memuat lencana logo (gambarnya adminku.svg lewat app.css)");
                $this->assertStringContainsString('<b>ADMINKU</b>', $html, "$route ($ua) mencetak nama merek (dua warna lewat CSS)");
                $this->assertStringContainsString('KERJA JADI RAPI', $html);
                $this->assertMatchesRegularExpression('/<title>[^<]*AdminKU<\/title>/u', $html);
                $this->assertStringNotContainsString('Stoku', $html);
            }
        }
    }

    public function test_phone_ua_gets_the_phone_screens_and_home_header_is_the_brand(): void
    {
        $login = $this->withHeader('User-Agent', self::PHONE)->get('/login')->assertOk()->getContent();
        $this->assertStringContainsString('ak-plogin', $login);

        $home = $this->actingAs(User::factory()->create())->withHeader('User-Agent', self::PHONE)->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('sstock-brandtitle', $home);
        $this->assertStringContainsString('<h1 class="dl-phone__title">ADMINKU</h1>', $home);
        $this->assertStringContainsString('<title>Beranda — AdminKU</title>', $home);
        $this->assertStringNotContainsString('Stoku', $home);
    }

    public function test_form_tabs_are_colorful_pills(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        foreach (['.dl-form__body.dl-tabs > .dl-tabs__bar .dl-tabs__tab', ':nth-child(5)', '[data-tab="audit"]', 'border-radius: 999px'] as $needle) {
            $this->assertStringContainsString($needle, $css);
        }
    }

    public function test_phone_screens_open_with_a_phone_viewport_numeric_keyboards_and_the_phone_css_rules(): void
    {
        $user = User::factory()->administrator()->create();
        foreach (['/', '/buka/transaksi', '/buka/laporan', '/buka/ga', '/buka/master', '/stok', '/m/find', '/m/counts', '/m/open/ITM', '/m/open/GVM', '/m/open/GAS', '/m/open/R-SUM'] as $path) {
            $html = $this->actingAs($user)->withHeader('User-Agent', self::PHONE)->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('width=device-width', $html, $path);
            $this->assertStringContainsString('dl-phone', $html, "$path memakai kerangka HP");
            $this->assertStringNotContainsString('Stoku', $html, $path);
        }
        // isian angka memakai keyboard angka (inputmode) di formulir odometer dan barang (baris dokumen digambar JS DevLib dan sudah decimal / numeric)
        foreach (['/vehicle-odometers/create', '/products/create'] as $path) {
            $this->actingAs($user)->get($path)->assertOk()->assertSee('inputmode="decimal"', false);
        }

        $css = file_get_contents(resource_path('css/app.css'));
        foreach (['body.dl-on-phone .dl-pager__goto { min-height: 44px; }', 'body.dl-on-phone .dl2-btn:is(.sstock-btn-slate, .sstock-btn-teal, .sstock-btn-coral, .sstock-btn-amber, .sstock-btn-red) { min-height: 44px; }','.dl-field__trigger:is(.dl-numtg, .dl-case)'] as $rule) {
            $this->assertStringContainsString($rule, $css);
        }
    }

    public function test_brand_css_is_built_into_the_stylesheet(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString("url('/media/brand/adminku.svg')", $css, 'semua lencana .ak-logo-badge memakai adminku.svg');
        $this->assertFileExists(public_path('media/brand/adminku.svg'));
        $this->assertStringContainsString('media/brand/adminku.svg', $this->withHeader('User-Agent', self::PC)->get('/login')->getContent(), 'pojok kiri atas login desktop');
        $this->assertStringContainsString('content: "Admin"', $css);
        $this->assertStringContainsString('content: "KU"', $css);
        $this->assertStringContainsStringIgnoringCase('#14a796', $css);
        $this->assertStringContainsStringIgnoringCase('#2dd4bf', $css);
    }
}
