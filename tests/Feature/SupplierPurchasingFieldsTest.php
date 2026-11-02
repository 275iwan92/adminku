<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\StockDocument;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga kolom Purchase di master Pemasok (email, syarat bayar, catatan), Kontan tetap tunai dan terkunci, dan tab Riwayat beli (terakhir beli, harga terakhir, berapa kali) dari Penerimaan terkonfirmasi.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class SupplierPurchasingFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_fields_save_and_show_and_kontan_stays_cash_and_locked(): void
    {
        $user = User::factory()->supervisor()->create(['username' => 'sup']);
        $this->actingAs($user)->post(route('suppliers.store'), ['code' => 'S1', 'name' => 'CV Maju', 'email' => 'budi@maju.co.id', 'payment_term_days' => 30, 'note' => 'Kirim Senin'])->assertSessionHasNoErrors();
        $s = Supplier::where('code', 'S1')->firstOrFail();
        $this->assertSame([30, 'budi@maju.co.id', '30 hari'], [$s->payment_term_days, $s->email, $s->termLabel()]);
        $this->get(route('suppliers.index'))->assertOk()->assertSee('budi@maju.co.id', false)->assertSee('30 hari', false);
        $this->post(route('suppliers.store'), ['code' => 'S2', 'name' => 'Toko Dua', 'email' => 'bukan-email'])->assertSessionHasErrors('email');

        $kontan = Supplier::where('code', 'KONTAN')->firstOrFail();
        $this->assertSame('Tunai', $kontan->termLabel());
        $this->put(route('suppliers.update', $kontan), ['code' => 'KONTAN', 'name' => 'Kontan', 'payment_term_days' => 14, 'area' => ''])->assertSessionHasErrors('payment_term_days');
    }

    public function test_history_tab_lists_last_price_and_times_from_confirmed_receivings_only(): void
    {
        $user = User::factory()->supervisor()->create(['username' => 'sup']);
        $supplier = Supplier::create(['code' => 'S1', 'name' => 'CV Maju']);
        $other = Supplier::create(['code' => 'S2', 'name' => 'Toko Lain']);
        $cat = Category::firstOrCreate(['code' => 'ATK'], ['name' => 'ATK']);
        $batch = $this->makeBatch(['code' => 'KRT', 'name' => 'Kertas A4', 'category_id' => $cat->id, 'unit' => 'pcs', 'unit_price' => 100, 'reorder_point' => 0, 'reorder_qty' => 50, 'lead_time_days' => 3]);

        foreach ([[$supplier, 50000], [$supplier, 52000], [$other, 99000]] as [$sup, $cost]) {
            $payload = ['supplier_id' => $sup->id, 'lines' => [['batch_id' => $batch->id, 'qty' => 10, 'unit_cost' => $cost]]];
            $this->actingAs($user)->post(route('docs.store', 'receiving'), $payload);
            $this->put(route('docs.update', ['receiving', StockDocument::latest('id')->firstOrFail()]), $payload + ['_action' => 'confirm']);
        }

        $rows = $supplier->purchaseHistory();
        $this->assertCount(1, $rows);
        $this->assertSame([2, 'Kertas A4'], [$rows[0]->times, $rows[0]->name]);
        $this->assertEquals(52000, $rows[0]->last_price);
        $this->actingAs($user)->get(route('suppliers.show', $supplier))->assertOk()->assertSee('Riwayat beli', false)->assertSee('Kertas A4', false);
        $this->assertCount(1, $other->purchaseHistory());
    }
}
