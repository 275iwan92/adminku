<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menu desktop: induk Stok membungkus subkelompok Transaksi dan Laporan; Data Master dan Keamanan tetap induk sendiri.
 * Output       : lulus/gagal.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class MenuLayersTest extends TestCase
{
    use RefreshDatabase;

    public function test_stok_wraps_transaksi_and_laporan(): void
    {
        $layers = collect(Menu::layers(User::factory()->administrator()->create()));

        $this->assertSame(['MST', 'STK', 'GA', 'HR', 'PUR', 'KEU', 'SEC'], $layers->pluck('code')->all());
        $this->assertSame(['Karyawan', 'Bagian', 'Rekrutmen', 'Kedisiplinan'], collect($layers->firstWhere('code', 'HR')['children'])->pluck('label')->all());
        $this->assertSame(['Perawatan Kendaraan', 'Perawatan AC', 'Perawatan Gedung'], collect($layers->firstWhere('code', 'GA')['children'])->pluck('label')->all());
        $this->assertSame(['Permintaan Pembelian', 'Keputusan Pesanan', 'Pesanan Pembelian (PO)', 'Penerimaan Pembelian (PV)'], collect($layers->firstWhere('code', 'PUR')['children'])->pluck('label')->all());
        $stok = $layers->firstWhere('code', 'STK');
        $this->assertSame(['Transaksi', 'Laporan'], collect($stok['children'])->pluck('label')->all());   // tanpa butir Beranda Stok: induk Stok membuka Beranda Stok saat diklik
        $this->assertSame(route('stock.home'), $stok['open']);
        $this->assertSame([route('opening', 'transaksi'), route('opening', 'laporan')], collect($stok['children'])->pluck('open')->all());
        $this->assertSame(['H-TRX', 'H-RPT'], collect($stok['children'])->pluck('code')->all());
        $this->assertContains('RCV', collect($stok['children'][0]['items'])->pluck('code')->all());
        $this->assertContains('R-SUM', collect($stok['children'][1]['items'])->pluck('code')->all());
        $this->assertNotContains('STB', collect($stok['children'])->flatMap(fn ($g) => collect($g['items'])->pluck('code'))->all());
        $this->assertSame(['MST' => route('opening', 'master'), 'GA' => route('opening', 'ga'), 'HR' => route('opening', 'hr'), 'PUR' => route('opening', 'purchase')], $layers->whereIn('code', ['MST', 'GA', 'HR', 'PUR'])->pluck('open', 'code')->all());

        // Keuangan langsung berisi modulnya tanpa subkelompok (KSM, KSK, 3 laporan; KSR tersembunyi lewat MENU_UNDER).
        $keu = $layers->firstWhere('code', 'KEU');
        $this->assertSame(route('opening', 'kas'), $keu['open']);
        $this->assertSame(['KSM', 'KSK', 'R-KFLOW', 'R-KIN', 'R-KOUT'], collect($keu['children'])->pluck('code')->all());
        $this->assertNotContains('H-KAS', collect($keu['children'])->pluck('code')->all());
    }

    public function test_entry_keeps_only_allowed_items_and_no_security(): void
    {
        $layers = collect(Menu::layers(User::factory()->create()));

        $this->assertSame(['MST', 'STK'], $layers->pluck('code')->all());
        $items = collect($layers->firstWhere('code', 'STK')['children'])->flatMap(fn ($g) => isset($g['items']) ? collect($g['items'])->pluck('code') : [$g['code']])->all();
        $this->assertContains('RCV', $items);
        $this->assertNotContains('OPN', $items);
    }

    public function test_home_renders_with_nested_menu_and_phone_menu_is_unchanged(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->get(route('home'))->assertOk()->assertSee('Stok');
        $this->assertSame(['MST', 'TRX', 'RPT', 'GA', 'HR', 'PUR', 'DLV', 'KEU', 'SEC'], collect(Menu::modules($admin))->pluck('code')->all());
    }
}
