<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Department;
use App\Models\Batch;
use App\Models\Recipient;
use App\Models\StockDocument;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga bahwa yang diinput di Penerimaan dan Pengeluaran (setelah Confirm) tampil di Laporan Penerimaan / Pengeluaran dengan periode bawaan.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class DocumentReportsShowEntriesTest extends TestCase
{
    use RefreshDatabase;

    private function item(): Batch
    {
        $category = Category::firstOrCreate(['code' => 'ATK'], ['name' => 'ATK']);

        return $this->makeBatch(['code' => 'LP1', 'name' => 'Barang Laporan', 'category_id' => $category->id, 'unit' => 'pcs', 'unit_price' => 100, 'reorder_point' => 0, 'reorder_qty' => 50, 'lead_time_days' => 3]);
    }

    private function postDoc(string $slug, array $payload, User $by): void
    {
        $this->actingAs($by)->post(route('docs.store', $slug), $payload);
        $this->put(route('docs.update', [$slug, StockDocument::latest('id')->firstOrFail()]), $payload + ['_action' => 'confirm']);
    }

    public function test_confirmed_receiving_and_issuance_appear_in_their_reports(): void
    {
        $entry = User::factory()->create(['username' => 'ent']);
        $sup = User::factory()->supervisor()->create(['username' => 'sup']);
        $supplier = Supplier::create(['code' => 'S1', 'name' => 'Supplier Satu']);
        $dept = Department::create(['code' => 'MNT', 'name' => 'Maintenance']);
        $recipient = Recipient::create(['name' => 'Penerima Uji', 'department_id' => $dept->id]);
        $item = $this->item();

        $this->postDoc('receiving', ['supplier_id' => $supplier->id, 'ref_no' => 'SJ-1', 'lines' => [['batch_id' => $item->id, 'qty' => 100, 'unit_cost' => 10]]], $entry);
        $this->postDoc('issuance', ['recipient_id' => $recipient->id, 'department_id' => $dept->id, 'lines' => [['batch_id' => $item->id, 'qty' => 30]]], $entry);

        $this->assertSame(['confirmed', 'confirmed'], StockDocument::orderBy('id')->pluck('status')->all());

        foreach ([$sup, $entry] as $user) {
            $this->actingAs($user)->get(route('reports.issuance'))->assertOk()->assertSee('ISS-', false)->assertSee('Barang Laporan', false)->assertSee('Penerima Uji', false);
            $this->actingAs($user)->get(route('reports.receiving'))->assertOk()->assertSee('RCV-', false)->assertSee('Barang Laporan', false)->assertSee('Supplier Satu', false);
        }
    }
}
