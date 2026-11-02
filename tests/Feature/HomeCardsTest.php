<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use App\Support\HomeCards;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga Beranda global (desktop dan HP): banner + satu kartu per modul (Stok, GA Kendaraan, GA AC) hanya untuk yang berhak modulnya, tanpa angka 0 palsu untuk yang tidak berhak, tanpa isi khusus Stok di Beranda,
 *                semua peran 200, kartu membuka layar pembuka modul (desktop: tab; HP: tampilan HP), dan jumlah kueri / waktu halaman di data contoh.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class HomeCardsTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = 'Mozilla/5.0 (Linux; Android 15; CPH2700) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36';

    private function group(array $access): User
    {
        $group = UserGroup::create(['code' => 'G'.random_int(100, 999), 'name' => 'Grup uji']);
        $group->syncAccess($access);

        return User::factory()->create(['group_id' => $group->id]);
    }

    private function codes(User $user, bool $phone = false): array
    {
        $req = $this->actingAs($user);
        $res = ($phone ? $req->withHeader('User-Agent', self::PHONE) : $req)->get(route('home'))->assertOk();
        $this->assertStringContainsString('sstock-hero', $res->getContent());

        return array_column($res->viewData('cards'), 'code');
    }

    public function test_every_role_gets_the_home_and_only_the_cards_it_may_open(): void
    {
        $this->assertSame(['STB'], $this->codes(User::factory()->create()));
        $this->assertSame(['STB'], $this->codes(User::factory()->supervisor()->create()));
        $this->assertSame(['STB', 'GVM', 'GAM'], $this->codes(User::factory()->administrator()->create()));
        $this->assertSame(['GVM', 'GAM'], $this->codes($this->group(['GVM' => 'ro', 'GAM' => 'ro'])));
        $this->assertSame([], $this->codes($this->group(['GVO' => 'rw'])));   // sopir: tidak ada kartu, bukan kartu berangka 0
        $this->assertSame(['STB'], $this->codes($this->group(['STB' => 'ro'])));
    }

    public function test_home_no_longer_carries_the_stock_content(): void
    {
        $html = $this->actingAs(User::factory()->supervisor()->create())->get(route('home'))->assertOk()->getContent();

        foreach (['Documents waiting approval', 'At / below reorder point', 'Open Receiving', 'reorder-modal'] as $text) {
            $this->assertStringNotContainsString($text, $html);
        }
        $this->assertStringContainsString('data-open-tab="STB"', $html);   // kartu Stok membuka Beranda Stok sebagai tab
        $this->assertStringNotContainsString('data-open-tab="GVM"', $html);
    }

    public function test_stock_card_shows_value_only_to_supervisor(): void
    {
        $this->actingAs(User::factory()->supervisor()->create())->get(route('home'))->assertSee('Nilai stok')->assertDontSee('Penerimaan hari ini');
        $this->actingAs(User::factory()->create())->get(route('home'))->assertDontSee('Nilai stok')->assertSee('Penerimaan hari ini');
    }

    public function test_phone_stacks_the_cards_as_links_to_phone_screens(): void
    {
        $html = $this->actingAs(User::factory()->administrator()->create())->withHeader('User-Agent', self::PHONE)->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('class="sstock-mod sstock-mod--teal "', $html);
        $this->assertStringContainsString('href="'.route('stock.home').'"', $html);
        $this->assertStringContainsString('href="'.route('hp.open', 'GVM').'"', $html);
        $this->assertStringNotContainsString('data-open-tab', $html);
        $this->assertStringNotContainsString('Cari Barang</a>', $html);   // Cari Barang dan ubin stok pindah ke Beranda Stok HP
    }

    public function test_money_is_shortened_for_the_card(): void
    {
        $this->assertSame('Rp 842,6 jt', HomeCards::shortMoney(842_600_000));
        $this->assertSame('Rp 1,2 M', HomeCards::shortMoney(1_240_000_000));
        $this->assertStringStartsWith('Rp', HomeCards::shortMoney(12_500));
    }

    public function test_sample_data_home_stays_within_the_query_and_time_budget(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('username', 'admin')->first() ?? User::factory()->administrator()->create();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        $this->actingAs($admin)->get(route('home'))->assertOk();   // pemanasan: kompilasi tampilan tidak ikut diukur

        foreach ([false, true] as $phone) {
            $queries = 0;
            $start = microtime(true);
            ($phone ? $this->actingAs($admin)->withHeader('User-Agent', self::PHONE) : $this->actingAs($admin))->get(route('home'))->assertOk();
            $ms = (microtime(true) - $start) * 1000;

            $this->assertLessThan(30, $queries, ($phone ? 'HP' : 'Desktop').": {$queries} kueri");
            $this->assertLessThan(250, $ms, ($phone ? 'HP' : 'Desktop').': '.round($ms).' ms');
        }
    }
}
