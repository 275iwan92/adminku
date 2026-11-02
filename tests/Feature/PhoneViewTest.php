<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga tampilan HP: tebakan perangkat dan pilihan di halaman login (cookie ui, pilihan pengguna menang atas tebakan), saklar di Menu / profil, Beranda HP, kerangka HP dengan modul menurut akses, dan tampilan desktop yang tidak berubah.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class PhoneViewTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = 'Mozilla/5.0 (Linux; Android 15; CPH2700) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36';

    private const PC = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36';

    public function test_login_page_follows_device_and_cookie_and_offers_the_phone_view_popup_on_desktop_only(): void
    {
        // ponsel: halaman login HP (tanpa popup), tanpa jalan ke tampilan desktop (ponsel selalu Tampilan HP)
        $phone = $this->withHeader('User-Agent', self::PHONE)->get('/login')->assertOk()->getContent();
        $this->assertStringContainsString('dl-plogin', $phone);
        $this->assertStringNotContainsString('dl-phoneoffer', $phone);
        $this->assertStringNotContainsString('name="ui" value="desktop"', $phone);

        // komputer tanpa pilihan: login desktop + popup tawaran (CSS menampilkannya hanya pada layar phone: lebar < 600 px, atau HP landscape)
        $pc = $this->withHeader('User-Agent', self::PC)->get('/login')->getContent();
        $this->assertStringNotContainsString('dl-plogin', $pc);
        $this->assertStringContainsString('dl-phoneoffer', $pc);
        $this->assertStringContainsString('Ingin tampilan yang lebih nyaman di smartphone?', $pc);
        $this->assertStringContainsString('Tetap desktop', $pc);
        $this->assertStringContainsString('data-auto', $pc, 'layar phone langsung ke Tampilan HP (DevLib phone-offer auto)');

        // pilihan pengguna (cookie) menang atas tebakan di komputer; di ponsel asli cookie desktop diabaikan
        $chosen = $this->withHeader('User-Agent', self::PHONE)->withCookie('ui', 'desktop')->get('/login')->getContent();
        $this->assertStringContainsString('dl-plogin', $chosen);
        $this->assertStringNotContainsString('dl-phoneoffer', $chosen);
        $this->assertStringContainsString('dl-plogin', $this->withHeader('User-Agent', self::PC)->withCookie('ui', 'hp')->get('/login')->getContent());
    }

    public function test_a_real_phone_is_always_the_phone_view_with_no_desktop_switch_but_a_narrowed_pc_window_keeps_the_way_back(): void
    {
        $user = User::factory()->create();
        $phone = $this->actingAs($user)->withHeader('User-Agent', self::PHONE)->withCookie('ui', 'desktop')->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('dl-phone', $phone);
        $this->assertStringNotContainsString('data-dl-device', $phone);
        $this->assertStringNotContainsString('action="'.route('ui.set').'"', $phone);

        $pc = $this->actingAs($user)->withHeader('User-Agent', self::PC)->withCookie('ui', 'phone')->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('action="'.route('ui.set').'"', $pc);
    }

    public function test_guest_can_save_the_view_choice_from_the_popup_and_the_phone_login(): void
    {
        $this->withHeader('User-Agent', self::PC)->post(route('ui.set'), ['ui' => 'hp'])->assertRedirect()->assertCookie('ui', 'phone');
        $this->withHeader('User-Agent', self::PHONE)->post(route('ui.set'), ['ui' => 'desktop'])->assertRedirect()->assertCookie('ui', 'desktop');
        $this->post(route('ui.set'), ['ui' => 'tablet'])->assertSessionHasErrors('ui');
        $this->get('/ui')->assertRedirect(route('home'));   // muat ulang halaman hasil ganti tampilan: bukan 405
        $this->get('/ui')->assertCookieMissing('ui');
    }

    public function test_login_stores_the_chosen_view_in_the_ui_cookie(): void
    {
        User::factory()->create(['username' => 'ani', 'password' => bcrypt('rahasia123')]);

        $this->post('/login', ['login' => 'ani', 'password' => 'rahasia123', 'ui' => 'phone'])->assertRedirect()->assertCookie('ui', 'phone');
        auth()->logout();
        $this->flushSession();
        $this->post('/login', ['login' => 'ani', 'password' => 'rahasia123', 'ui' => 'bogus'])->assertRedirect()->assertCookieMissing('ui');
    }

    public function test_home_follows_the_cookie_then_the_device_guess_and_desktop_is_untouched(): void
    {
        $sup = User::factory()->supervisor()->create();

        $desktop = $this->actingAs($sup)->withHeader('User-Agent', self::PC)->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('dl-shell', $desktop);
        $this->assertStringNotContainsString('dl-phone__burger', $desktop);
        $this->assertStringContainsString('sstock-hero', $desktop, 'Beranda desktop memuat banner hero (menggantikan sapaan dan kutipan)');

        $phone = $this->actingAs($sup)->withHeader('User-Agent', self::PHONE)->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('dl-phone__burger', $phone);
        $this->assertStringNotContainsString('dl-shell', $phone);

        // pilihan pengguna menang atas tebakan di komputer; di ponsel asli pilihan desktop diabaikan (ponsel selalu Tampilan HP)
        $this->actingAs($sup)->withHeader('User-Agent', self::PHONE)->withCookie('ui', 'desktop')->get('/')->assertSee('dl-phone__burger', false)->assertDontSee('dl-shell', false);
        $this->actingAs($sup)->withHeader('User-Agent', self::PC)->withCookie('ui', 'hp')->get('/')->assertSee('dl-phone__burger', false);
    }

    public function test_desktop_home_offers_the_phone_view_popup_only_until_the_user_has_chosen(): void
    {
        $sup = User::factory()->supervisor()->create();

        $this->actingAs($sup)->withHeader('User-Agent', self::PC)->get('/')->assertSee('dl-phoneoffer', false)->assertSee('data-auto', false)->assertSee('Ingin tampilan yang lebih nyaman di smartphone?');
        $this->actingAs($sup)->withHeader('User-Agent', self::PC)->withCookie('ui', 'desktop')->get('/')->assertDontSee('dl-phoneoffer', false);
        $this->actingAs($sup)->withHeader('User-Agent', self::PHONE)->get('/')->assertDontSee('dl-phoneoffer', false);
    }

    public function test_automatic_switch_is_marked_and_a_user_choice_removes_the_mark(): void
    {
        $user = User::factory()->supervisor()->create();

        $this->actingAs($user)->post(route('ui.set'), ['ui' => 'phone', 'auto' => 1])->assertCookie('ui', 'phone')->assertCookie('ui_auto', '1');
        $res = $this->actingAs($user)->post(route('ui.set'), ['ui' => 'desktop']);
        $res->assertCookie('ui', 'desktop');
        $this->assertTrue(collect($res->headers->getCookies())->contains(fn ($c) => $c->getName() === 'ui_auto' && $c->isCleared()), 'pilihan pengguna membuang penanda otomatis');

        // ui hasil otomatis dianggap belum dipilih: Beranda desktop memuat popup lagi; kerangka HP kembali sendiri hanya untuk pilihan otomatis
        $this->actingAs($user)->withHeader('User-Agent', self::PC)->withCookie('ui', 'desktop')->withCookie('ui_auto', '1')->get('/')->assertSee('dl-phoneoffer', false);
        $this->actingAs($user)->withCookie('ui', 'phone')->withCookie('ui_auto', '1')->get('/')->assertSee('data-auto-return', false);
        $this->defaultCookies = [];   // withCookie bertahan antar permintaan: buang ui_auto dari yang sebelumnya
        $this->actingAs($user)->withCookie('ui', 'phone')->get('/')->assertDontSee('data-auto-return', false);
    }

    public function test_desktop_home_gets_one_extra_profile_action_phone_view(): void
    {
        $html = $this->actingAs(User::factory()->supervisor()->create())->withHeader('User-Agent', self::PC)->get('/')->getContent();
        $this->assertStringContainsString('data-lm-act="device"', $html);
        $this->assertStringContainsString('data-dl-device', $html);
    }

    public function test_switch_saves_the_cookie_and_rejects_other_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('ui.set'), ['ui' => 'desktop'])->assertRedirect(route('home'))->assertCookie('ui', 'desktop');
        $this->actingAs($user)->post(route('ui.set'), ['ui' => 'tablet'])->assertSessionHasErrors('ui');
    }

    // DevLib PhoneShell.init mengikat klik ke elemen-elemen ini tanpa pemeriksaan: satu saja hilang dari kerangka = galat JS "Cannot read properties of null (reading 'addEventListener')" dan menu HP mati.
    public function test_every_phone_screen_renders_all_the_hooks_phone_shell_js_binds_to(): void
    {
        $user = User::factory()->supervisor()->create();
        $hooks = ['data-dl-phone-menu', 'data-dl-phone-bar', 'data-dl-phone-more', 'data-dl-phone-list', 'data-dl-phone-panel', 'data-dl-phone-close-panel', 'data-dl-phone-sheet', 'dl-phone__main'];

        foreach (['/', route('opening', 'transaksi'), route('hp.open', 'ITM'), route('hp.find'), route('hp.counts')] as $url) {
            $html = $this->actingAs($user)->withHeader('User-Agent', self::PHONE)->get($url)->assertOk()->getContent();
            foreach ($hooks as $hook) {
                $this->assertStringContainsString($hook, $html, "$url tanpa $hook");
            }
        }
    }

    public function test_phone_shell_menu_lists_only_modules_the_user_may_open(): void
    {
        $entry = User::factory()->create();
        $html = $this->actingAs($entry)->withCookie('ui', 'hp')->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('Penerimaan Barang', $html);
        $this->assertStringNotContainsString('>Pengguna<', $html);
        $this->assertStringNotContainsString('Daftar Modul', $html);
        $this->assertStringContainsString('action="'.route('ui.set').'"', $html);

        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->withCookie('ui', 'hp')->get('/')->assertSee('Pengguna');
    }

    public function test_phone_menu_has_the_sign_out_power_icon_in_the_name_row_and_no_bottom_button(): void
    {
        $html = $this->actingAs(User::factory()->create())->withCookie('ui', 'hp')->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#dl-phone__who--out.*action="'.preg_quote(route('logout'), '#').'".*dl-phone__poweroff#s', $html);
        $this->assertStringNotContainsString('class="dl-phone__out"', $html);
    }

    public function test_module_without_a_phone_screen_opens_its_desktop_page_inside_the_frame(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withCookie('ui', 'hp')->get(route('hp.open', 'ITM'))->assertOk()->assertSee('<iframe class="dl-phone__frame" src="'.route('products.index').'"', false);
        $this->actingAs($user)->get(route('hp.open', 'USR'))->assertForbidden();
        $this->actingAs($user)->get(route('hp.open', 'XXX'))->assertNotFound();
    }

    public function test_transaction_tab_lists_accessible_transaction_modules(): void
    {
        $entry = User::factory()->create();
        $this->actingAs($entry)->get(route('hp.group', 'TRX'))->assertRedirect(route('opening', 'transaksi'));   // layar pembuka menggantikan daftar kelompok
        $this->actingAs($entry)->get(route('opening', 'transaksi'))->assertOk()->assertSee('Penerimaan Barang')->assertSee('Pengeluaran Barang')->assertDontSee('Saldo Awal')->assertDontSee('Penyesuaian Stok');
        $this->actingAs($entry)->get(route('hp.group', 'NOPE'))->assertNotFound();
    }

    public function test_opname_tab_is_hidden_and_the_count_screen_refused_without_the_so_module(): void
    {
        $group = UserGroup::create(['code' => 'NOSO', 'name' => 'Tanpa opname']);
        $group->syncAccess(['RCV' => 'rw']);
        $user = User::factory()->create(['group_id' => $group->id]);

        $html = $this->actingAs($user)->withCookie('ui', 'hp')->get('/')->getContent();
        $this->assertStringNotContainsString(route('hp.counts'), $html);
        $this->actingAs($user)->get(route('hp.counts'))->assertForbidden();
    }

    public function test_desktop_pages_get_the_phone_body_class_only_when_the_device_is_hp(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withCookie('ui', 'hp')->get(route('products.index'))->assertSee('dl-on-phone', false);
        $this->actingAs($user)->withCookie('ui', 'desktop')->withHeader('User-Agent', self::PC)->get(route('products.index'))->assertDontSee('dl-on-phone', false);
    }

    public function test_items_grid_declares_phone_cards_without_a_desktop_view_switch(): void
    {
        $html = $this->actingAs(User::factory()->create())->get(route('products.index'))->getContent();
        $this->assertStringContainsString('phoneCards', $html);
        $this->assertStringNotContainsString('data-g-view', $html);
    }

    public function test_every_list_screen_declares_phone_cards_so_phones_do_not_scroll_tables_sideways(): void
    {
        $sup = User::factory()->supervisor()->create();
        $screens = [
            'products.index' => [], 'suppliers.index' => [], 'units.index' => [], 'categories.index' => [], 'locations.index' => [],
            'departments.index' => [], 'recipients.index' => [], 'reorder-orders.index' => [], 'stock-counts.index' => [],
            'docs.index' => ['receiving'], 'docs.index ' => ['issuance'], 'docs.index  ' => ['opening'], 'docs.index   ' => ['adjustment'],
        ];
        foreach ($screens as $name => $params) {
            $html = $this->actingAs($sup)->get(route(trim($name), $params))->assertOk()->getContent();
            $this->assertStringContainsString('phoneCards', $html, $name.' '.json_encode($params));
        }
    }

    public function test_admin_list_screens_also_declare_phone_cards(): void
    {
        $admin = User::factory()->administrator()->create();
        foreach (['users.index', 'groups.index', 'modules.index'] as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk()->assertSee('phoneCards', false);
        }
    }
}
