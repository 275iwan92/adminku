<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga layar FORMULIR data master dan admin di Tampilan HP: semua form terbuka (cookie ui=phone) tanpa galat dan memakai konteks HP; tabel kecil di form Barang bertumpuk (x-dl::table stack),
 *                layar Modul memakai kartu, Lupa / Atur ulang password memakai kerangka login HP; desktop tidak berubah (tanpa dl-on-phone, kerangka lama).
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class PhoneFormsTest extends TestCase
{
    use RefreshDatabase;

    private const PC = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
    }

    private function product(): Item
    {
        Unit::firstOrCreate(['code' => 'dus'], ['name' => 'Dus']);

        return Item::register(['name' => 'Pensil HP', 'category_id' => Category::firstOrCreate(['code' => 'ATK'], ['name' => 'ATK'])->id, 'unit' => 'pcs', 'unit_price' => 100, 'reorder_point' => 1, 'reorder_qty' => 10]);
    }

    /** @return array<string, string> nama layar => alamat */
    private function screens(): array
    {
        $p = $this->product();
        $supplier = Supplier::firstOrCreate(['code' => 'S1'], ['name' => 'S One']);
        $group = UserGroup::query()->first();

        return [
            'barang baru' => route('products.create'), 'barang' => route('products.show', $p), 'supplier baru' => route('suppliers.create'), 'supplier' => route('suppliers.show', $supplier),
            'satuan' => route('units.create'), 'kategori' => route('categories.create'), 'lokasi' => route('locations.create'), 'departemen' => route('departments.create'),
            'penerima' => route('recipients.create'), 'perusahaan' => route('company.edit'), 'pengguna' => route('users.create'), 'grup baru' => route('groups.create'),
            'grup' => route('groups.show', $group), 'modul' => route('modules.index'),
        ];
    }

    public function test_every_master_form_opens_on_the_phone_view_and_stays_desktop_on_a_pc(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        foreach ($this->screens() as $name => $url) {
            $this->withCookie('ui', 'phone')->get($url)->assertOk()->assertSee('dl-on-phone', false);
            $this->withCookie('ui', 'desktop')->withHeader('User-Agent', self::PC)->get($url)->assertOk()->assertDontSee('dl-on-phone', false);
        }
    }

    public function test_small_tables_in_the_item_form_stack_into_cards_on_the_phone(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());
        $p = $this->product();
        ItemUnit::create(['item_id' => $p->id, 'unit' => 'dus', 'factor' => 12]);
        $html = $this->withCookie('ui', 'phone')->get(route('products.show', $p))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'dl-table--stack'), 'tabel Batch dan Satuan masuk');
        $this->assertStringContainsString('data-label="Faktor ke pcs"', $html);
    }

    public function test_modules_screen_declares_phone_cards(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $this->withCookie('ui', 'phone')->get(route('modules.index'))->assertOk()->assertSee('phoneCards', false);
    }

    public function test_forgot_and_reset_password_use_the_phone_login_frame_only_on_the_phone(): void
    {
        foreach ([route('password.request'), route('password.reset', ['token' => 'abc', 'email' => 'a@b.test'])] as $url) {
            $phone = $this->withCookie('ui', 'phone')->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('dl-plogin', $phone);
            $this->assertStringNotContainsString('dl-frame', $phone);
            $pc = $this->withCookie('ui', 'desktop')->withHeader('User-Agent', self::PC)->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('dl-plogin', $pc);
            $this->assertStringContainsString('dl-loginbox', $pc);
        }
    }
}
