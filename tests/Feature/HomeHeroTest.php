<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use App\Support\HomeHero;
use Carbon\Carbon;
use Devlib\Lang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga banner hero Beranda (desktop dan HP): sapaan menurut jam, keterangan peran / Grup / perusahaan, empat angka hanya untuk yang berhak, tombol Panduan Pengguna, jam dan tanggal zona aplikasi,
 *                serta teks Indonesia keterangan jaringan panel kiri (server tidak menjawab, perangkat ini offline).
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class HomeHeroTest extends TestCase
{
    use RefreshDatabase;

    private const PC = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/141.0.0.0 Safari/537.36';

    private const PHONE = 'Mozilla/5.0 (Linux; Android 15; CPH2700) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function home(User $user, string $ua = self::PC): string
    {
        return $this->actingAs($user)->withHeader('User-Agent', $ua)->get(route('home'))->assertOk()->getContent();
    }

    private function limited(array $access, string $name = 'Sari Dewi'): User
    {
        $group = UserGroup::create(['code' => 'T'.UserGroup::count(), 'name' => 'Grup uji']);
        $group->syncAccess($access);

        return User::factory()->create(['name' => $name, 'group_id' => $group->id]);
    }

    public function test_the_greeting_follows_the_hour_in_the_app_timezone(): void
    {
        foreach (['04:00' => 'pagi', '10:59' => 'pagi', '11:00' => 'siang', '14:59' => 'siang', '15:00' => 'sore', '18:59' => 'sore', '19:00' => 'malam', '23:59' => 'malam'] as $hm => $word) {
            $this->assertSame('Selamat '.$word, HomeHero::greeting((int) substr($hm, 0, 2)), $hm);
        }

        // 03:30 UTC = 10:30 WIB = pagi (bukan zona UTC), 04:00 UTC = 11:00 WIB = siang
        Carbon::setTestNow(Carbon::parse('2026-11-02 03:30:00', 'UTC'));
        $html = $this->home(User::factory()->administrator()->create(['name' => 'Budi Santoso']));
        $this->assertStringContainsString('Selamat pagi</span>, Budi', $html);
        $this->assertStringContainsString('<span data-hero-time>10:30:00</span> <small>WIB</small>', $html);
        $this->assertStringContainsString('Senin, 2 November 2026', $html);

        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00', 'UTC'));
        $this->assertStringContainsString('Selamat malam</span>, Budi', $this->home(User::factory()->administrator()->create(['name' => 'Budi Santoso'])));
    }

    public function test_desktop_and_phone_home_render_for_every_role_with_the_guide_button(): void
    {
        $users = [User::factory()->administrator()->create(), User::factory()->supervisor()->create(), User::factory()->create(), $this->limited(['ITM' => 'rw'])];

        foreach ($users as $user) {
            foreach ([self::PC, self::PHONE] as $ua) {
                $html = $this->home($user, $ua);
                $this->assertStringContainsString('class="sstock-hero"', $html);
                $this->assertStringContainsString('href="'.route('help.user-guide').'"', $html);
                $this->assertStringContainsString('Panduan Pengguna', $html);
                $this->assertStringContainsString('AdminKU · Beranda', $html);
            }
        }
    }

    public function test_the_four_numbers_are_shown_only_to_those_allowed_to_see_them(): void
    {
        $all = $this->home(User::factory()->administrator()->create());
        foreach (['Di titik pesan ulang', 'Menunggu persetujuan', 'Kendaraan perlu tindakan', 'AC terjadwal minggu ini'] as $label) {
            $this->assertStringContainsString('<span>'.$label.'</span>', $all);
        }
        $this->assertStringContainsString('Administrator · Jays', $all);

        $stock = $this->home($this->limited(['ITM' => 'rw']));
        $this->assertStringContainsString('<span>Di titik pesan ulang</span>', $stock);
        foreach (['Menunggu persetujuan', 'Kendaraan perlu tindakan', 'AC terjadwal minggu ini'] as $label) {
            $this->assertStringNotContainsString($label, $stock);
        }
        $this->assertStringContainsString('Data Entry · Grup uji · Jays', $stock);

        $ga = $this->home($this->limited(['GVM' => 'ro', 'GAS' => 'ro']));
        $this->assertStringContainsString('<span>Kendaraan perlu tindakan</span>', $ga);
        $this->assertStringContainsString('<span>AC terjadwal minggu ini</span>', $ga);
        $this->assertStringNotContainsString('Di titik pesan ulang', $ga);

        $none = $this->home($this->limited(['SO' => 'rw']));
        $this->assertStringNotContainsString('sstock-hero__stats', $none);
        $this->assertStringNotContainsString('<b class="sstock-hero__num', $none);
    }

    public function test_the_network_status_texts_are_indonesian_and_say_what_is_wrong(): void
    {
        $this->assertSame('Server tidak menjawab', Lang::t('net_server_down'));
        $this->assertSame('Perangkat ini offline', Lang::t('net_offline'));
        $this->assertStringContainsString('Server tidak menjawab', $this->home(User::factory()->supervisor()->create()));
    }
}
