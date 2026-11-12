<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ActivityLog;
use App\Support\IpLocation;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga log aktivitas (CSV bulanan) dan layar super admin: yang dicatat (Buka, Input / Ubah / Hapus, Login, Login gagal, Logout) lengkap dengan user, IP, waktu; ping dan JSON baca tidak dicatat; kata sandi tidak pernah dicatat;
 *                kolom Lokasi (perkiraan) dari IP memakai ipwho.is yang di tes selalu di-fake (tidak ada jaringan sungguhan);
 *                layar Log Aktivitas dan Akun UAT hanya untuk super admin (selain dia 404, termasuk Administrator); super admin bisa mengubah kata sandi akun lain dan tidak tampil di daftar Pengguna.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/sstock-activity-'.uniqid();
        config(['sstock.activity_dir' => $this->dir, 'sstock.super_admin' => ['username' => 'johiwan', 'hash' => null], 'sstock.ip_lookup' => false]);   // pencarian lokasi hanya menyala di tes IpLocation
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function super(): User
    {
        return User::factory()->administrator()->create(['username' => 'johiwan']);
    }

    public function test_opening_a_page_is_logged_with_user_ip_and_time(): void
    {
        $user = User::factory()->create(['username' => 'penguji']);

        $this->actingAs($user)->get('/')->assertOk();

        $row = ActivityLog::read()[0];
        $this->assertSame('penguji', $row['user']);
        $this->assertSame('127.0.0.1', $row['ip']);
        $this->assertSame('Buka', $row['action']);
        $this->assertStringContainsString('home', $row['detail']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row['at']);
    }

    public function test_writes_are_logged_as_input_change_and_delete_without_the_body(): void
    {
        Route::middleware('web')->post('uji-baru', fn () => 'x')->name('things.store');
        Route::middleware('web')->put('uji-ubah', fn () => 'x')->name('things.update');
        Route::middleware('web')->delete('uji-hapus', fn () => 'x')->name('things.destroy');
        $this->actingAs(User::factory()->create(['username' => 'penguji']));

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->post('uji-baru', ['rahasia' => 'ISI-FORMULIR']);
        $this->put('uji-ubah');
        $this->delete('uji-hapus');

        $actions = array_column(ActivityLog::read(), 'action');
        $this->assertSame(['Hapus', 'Ubah', 'Input'], array_slice($actions, 0, 3));
        $this->assertStringNotContainsString('ISI-FORMULIR', (string) file_get_contents(ActivityLog::file()));
    }

    public function test_login_failed_login_and_logout_are_logged_and_the_password_never_is(): void
    {
        User::factory()->create(['username' => 'penguji', 'password' => 'rahasia-benar']);

        $this->post('/login', ['login' => 'penguji', 'password' => 'salah-sekali']);
        $this->post('/login', ['login' => 'penguji', 'password' => 'rahasia-benar']);
        $this->post('/logout');

        $rows = ActivityLog::read();
        $this->assertSame(['Logout', 'Login', 'Login gagal'], array_slice(array_column($rows, 'action'), 0, 3));
        $this->assertSame('penguji', $rows[2]['user']);
        $this->assertStringNotContainsString('salah-sekali', (string) file_get_contents(ActivityLog::file()));
        $this->assertStringNotContainsString('rahasia-benar', (string) file_get_contents(ActivityLog::file()));
    }

    public function test_session_ping_and_json_reads_are_not_logged_and_guests_are_not_logged(): void
    {
        $this->get('/login');
        $this->actingAs(User::factory()->create())->get(route('session.ping'));
        $this->getJson('/')->status();

        $this->assertSame([], ActivityLog::read());
    }

    public function test_a_spreadsheet_formula_in_a_value_is_defused_and_one_record_stays_on_one_line(): void
    {
        ActivityLog::write("=HYPERLINK(\"x\")\nbaris-baru", '1.2.3.4', 'Login gagal', 'login');

        $this->assertSame(1, count(file(ActivityLog::file())));
        $this->assertStringStartsWith("'=HYPERLINK", ActivityLog::read()[0]['user']);
    }

    public function test_the_log_reads_newest_first_and_only_the_tail_of_a_big_file(): void
    {
        foreach (range(1, 30) as $i) {
            ActivityLog::write('u', '1.1.1.1', 'Buka', "baris-$i");
        }

        $rows = ActivityLog::read(null, 5);

        $this->assertSame(['baris-30', 'baris-29', 'baris-28', 'baris-27', 'baris-26'], array_column($rows, 'detail'));
    }

    public function test_only_the_super_admin_can_open_the_screens_everyone_else_gets_404(): void
    {
        ActivityLog::write('x', '9.9.9.9', 'Buka', 'home /');
        $admin = User::factory()->administrator()->create();
        $super = $this->super();

        foreach (['super.log', 'super.accounts'] as $name) {
            $this->actingAs($admin)->get(route($name))->assertNotFound();
            $this->actingAs(User::factory()->supervisor()->create())->get(route($name))->assertNotFound();
            $this->actingAs($super)->get(route($name))->assertOk();
        }
        $this->actingAs($super)->get(route('super.log'))->assertSee('9.9.9.9')->assertDontSee('data-g-new', false);   // log hanya dibaca: tanpa tombol Data Baru
        $this->actingAs($super)->get(route('super.log.download', ['month' => now()->format('Y-m')]))->assertOk()->assertHeader('content-disposition');
        $this->actingAs($admin)->get(route('super.log.download', ['month' => now()->format('Y-m')]))->assertNotFound();
    }

    public function test_on_the_phone_the_super_screens_open_inside_the_shell_so_the_menu_button_stays(): void
    {
        $super = $this->super();
        $menu = $this->actingAs($super)->withCookie('ui', 'hp')->get('/')->assertOk()->getContent();
        $this->assertStringContainsString(route('hp.open', 'LOG'), $menu);
        $this->assertStringNotContainsString('href="'.route('super.log').'"', $menu, 'tidak lagi halaman mandiri tanpa kerangka');

        $this->actingAs($super)->withCookie('ui', 'hp')->get(route('hp.open', 'LOG'))->assertOk()->assertSee('data-dl2="phone-shell"', false)->assertSee('&quot;frame&quot;:&quot;'.route('super.log').'&quot;', false);   // phone-shell (menu + bilah) memuat layar desktop di iframe lewat opsi frame
        $this->actingAs($super)->withCookie('ui', 'hp')->get(route('hp.open', 'UAT'))->assertOk()->assertSee(route('super.accounts'), false);
        $this->actingAs(User::factory()->administrator()->create())->get(route('hp.open', 'LOG'))->assertNotFound();
    }

    public function test_the_menu_entries_exist_only_for_the_super_admin(): void
    {
        $this->actingAs(User::factory()->administrator()->create())->get('/')->assertDontSee('Log Aktivitas');
        $this->actingAs($this->super())->get('/')->assertSee('Log Aktivitas')->assertSee('Akun UAT');
    }

    public function test_accounts_screen_shows_the_seeded_defaults_and_hides_the_super_admin(): void
    {
        $this->seed(UserSeeder::class);

        $this->actingAs($this->super())->get(route('super.accounts'))->assertOk()->assertSee('spv')->assertSee('12345678')->assertDontSee('johiwan');
    }

    public function test_super_admin_changes_a_password_and_the_old_sessions_of_that_account_end(): void
    {
        $super = $this->super();
        $entry = User::factory()->create(['username' => 'entry', 'password' => 'lama-123']);
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $entry->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($super)->post(route('super.password', $entry), ['password' => 'baru-456'])->assertRedirect(route('super.accounts'));

        $this->assertTrue(Hash::check('baru-456', $entry->fresh()->password));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $entry->id)->count());
        $this->actingAs($super)->post(route('super.password', $entry), ['password' => 'abc'])->assertSessionHasErrors('password');
        $this->actingAs($super)->post(route('super.password', $super), ['password' => 'baru-456'])->assertNotFound();
        $this->actingAs(User::factory()->administrator()->create())->post(route('super.password', $entry), ['password' => 'baru-456'])->assertNotFound();
    }

    private function fakeWho(?array $body = null): void
    {
        config(['sstock.ip_lookup' => true]);
        Http::fake(['ipwho.is/*' => Http::response($body ?? ['success' => true, 'city' => 'Jakarta', 'region' => 'Jakarta', 'country' => 'Indonesia', 'connection' => ['isp' => 'Telkom']])]);
    }

    public function test_a_public_ip_gets_place_and_isp_and_is_looked_up_only_once(): void
    {
        $this->fakeWho();

        $this->assertSame(['8.8.4.4' => 'Jakarta, Jakarta, Indonesia · Telkom'], IpLocation::label(['8.8.4.4']));
        IpLocation::label(['8.8.4.4']);

        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://ipwho.is/8.8.4.4'));
    }

    public function test_a_failed_broken_or_unreachable_lookup_is_unknown_without_an_error(): void
    {
        config(['sstock.ip_lookup' => true]);
        Http::fake(['ipwho.is/8.8.4.1*' => Http::response(['success' => false]), 'ipwho.is/8.8.4.2*' => Http::response('bukan json', 200), 'ipwho.is/8.8.4.3*' => fn () => throw new ConnectionException('timeout')]);

        $this->assertSame(['8.8.4.1' => 'Tidak diketahui', '8.8.4.2' => 'Tidak diketahui', '8.8.4.3' => 'Tidak diketahui'], IpLocation::label(['8.8.4.1', '8.8.4.2', '8.8.4.3']));
    }

    public function test_after_one_timeout_the_other_ips_in_the_same_view_are_not_waited_for(): void
    {
        config(['sstock.ip_lookup' => true]);
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $out = IpLocation::label(['8.8.4.1', '8.8.4.2', '8.8.4.3']);

        $this->assertSame(['8.8.4.1' => 'Tidak diketahui', '8.8.4.2' => 'Mencari...', '8.8.4.3' => 'Mencari...'], $out);
    }

    public function test_private_loopback_and_invalid_ips_are_never_sent_anywhere(): void
    {
        $this->fakeWho();

        $out = IpLocation::label(['127.0.0.1', '192.168.1.5', '10.0.0.7', '::1', 'fd00::1', 'bukan-ip', '']);

        $this->assertSame(['127.0.0.1' => 'Jaringan lokal', '192.168.1.5' => 'Jaringan lokal', '10.0.0.7' => 'Jaringan lokal', '::1' => 'Jaringan lokal', 'fd00::1' => 'Jaringan lokal', 'bukan-ip' => '', '' => ''], $out);
        Http::assertNothingSent();
    }

    public function test_an_ipv6_address_is_looked_up_and_at_most_eight_new_ips_per_view(): void
    {
        $this->fakeWho();
        $ips = array_map(fn ($i) => "8.8.8.$i", range(1, 10));

        $out = IpLocation::label(['2606:4700:4700::1111', ...$ips]);

        Http::assertSentCount(8);
        $this->assertSame('Jakarta, Jakarta, Indonesia · Telkom', $out['2606:4700:4700::1111']);
        $this->assertSame(['Jakarta, Jakarta, Indonesia · Telkom', 'Mencari...'], [$out['8.8.8.7'], $out['8.8.8.8']]);

        IpLocation::label($ips);   // pemuatan berikutnya: yang sudah dicari dari cache, sisanya baru dicari

        Http::assertSentCount(11);   // 8 + 3 sisanya; yang sudah di-cache tidak diulang
    }

    public function test_with_the_lookup_switched_off_nothing_is_sent(): void
    {
        $this->fakeWho();
        config(['sstock.ip_lookup' => false]);

        $this->assertSame(['8.8.4.4' => 'Mencari...'], IpLocation::label(['8.8.4.4']));
        Http::assertNothingSent();
    }

    public function test_the_log_screen_shows_the_location_column_and_the_csv_stays_unchanged(): void
    {
        $this->fakeWho();
        ActivityLog::write('x', '8.8.4.4', 'Buka', 'home /');
        $before = (string) file_get_contents(ActivityLog::file());

        $this->actingAs($this->super())->get(route('super.log'))->assertOk()->assertSee('Lokasi (perkiraan)')->assertSee('Jakarta, Jakarta, Indonesia · Telkom', false)->assertSee('ipwho.is');   // data grid ada di atribut JSON (Unicode apa adanya)

        $this->assertStringNotContainsString('Jakarta', (string) file_get_contents(ActivityLog::file()));
        $this->assertStringStartsWith($before, (string) file_get_contents(ActivityLog::file()));   // hanya menambah baris Buka milik super admin sendiri
    }
}
