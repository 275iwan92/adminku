<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Batch;
use App\Models\StockDocument;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga tombol plus "tambah cepat" (x-dl::field.select add) di layar: ada hanya bagi yang boleh menulis master-nya, tidak ada di dokumen terkunci,
 *                tidak ada di filter laporan / pilih barang / form pengguna, dan pola quick-add lama (baris "Penerima baru" + modal qa-*) sudah hilang.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class QuickAddScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $entry;

    private User $sup;

    private Batch $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entry = User::factory()->create();
        $this->sup = User::factory()->supervisor()->create();
        $this->item = $this->makeBatch(['code' => 'Q1', 'name' => 'Item Q1', 'category_id' => Category::create(['code' => 'ATK', 'name' => 'ATK'])->id, 'unit' => 'pcs', 'unit_price' => 10, 'reorder_point' => 5, 'reorder_qty' => 20]);
    }

    private function plus(string $html, string $path): int
    {
        return substr_count($html, 'data-dl-select-add data-url="'.$path.'"');
    }

    public function test_receiving_form_has_a_supplier_plus_and_the_popup_posts_to_the_supplier_endpoint(): void
    {
        $html = $this->actingAs($this->entry)->get(route('docs.create', 'receiving'))->assertOk()
            ->assertSee('data-dl-sa-modal', false)->assertSee('Tambah Supplier')->assertSee('name="contact_name"', false)->getContent();

        $this->assertSame(1, $this->plus($html, '/suppliers'));
        $this->assertSame(1, $this->plus($html, '/recipients'));
        $this->assertSame(1, $this->plus($html, '/departments'));
        $this->assertSame(0, $this->plus($html, '/categories') + $this->plus($html, '/units') + $this->plus($html, '/locations'));
    }

    public function test_issuance_and_adjustment_use_the_component_not_the_old_quick_add(): void
    {
        $issuance = $this->actingAs($this->entry)->get(route('docs.create', 'issuance'))->assertOk()
            ->assertDontSee('qa-recipient', false)->assertDontSee('qa-department', false)->assertDontSee('Penerima baru', false)->assertDontSee('sstock-dd-new', false)->getContent();
        $this->assertSame([1, 1, 0], [$this->plus($issuance, '/recipients'), $this->plus($issuance, '/departments'), $this->plus($issuance, '/suppliers')]);
        $this->assertFileDoesNotExist(resource_path('views/docs/quick-add.blade.php'));

        $adjustment = $this->actingAs($this->sup)->get(route('docs.create', 'adjustment'))->assertOk()->assertDontSee('qa-recipient', false)->getContent();
        $this->assertSame([1, 1], [$this->plus($adjustment, '/recipients'), $this->plus($adjustment, '/departments')]);
    }

    public function test_plus_is_hidden_for_a_user_whose_group_cannot_write_that_master(): void
    {
        $group = UserGroup::create(['code' => 'RO', 'name' => 'Baca']);
        $group->syncAccess(['RCV' => 'rw', 'SUP' => 'ro', 'RCP' => 'rw', 'DEP' => 'ro']);
        $html = $this->actingAs(User::factory()->create(['group_id' => $group->id]))->get(route('docs.create', 'receiving'))->assertOk()->getContent();

        $this->assertSame([0, 1, 0], [$this->plus($html, '/suppliers'), $this->plus($html, '/recipients'), $this->plus($html, '/departments')]);
    }

    public function test_confirmed_documents_are_locked_without_plus_buttons(): void
    {
        $payload = ['supplier_id' => Supplier::firstOrCreate(['code' => 'S1'], ['name' => 'S'])->id, 'lines' => [['batch_id' => $this->item->id, 'qty' => 5, 'unit_cost' => 10]]];
        $this->actingAs($this->entry)->post(route('docs.store', 'receiving'), $payload)->assertSessionHasNoErrors();
        $doc = StockDocument::latest('id')->firstOrFail();
        $this->get(route('docs.show', ['receiving', $doc]))->assertOk()->assertSee('data-dl-select-add', false);   // draf masih bisa diubah

        $this->put(route('docs.update', ['receiving', $doc]), $payload + ['_action' => 'confirm'])->assertSessionHasNoErrors();
        $html = $this->get(route('docs.show', ['receiving', $doc]))->assertOk()->getContent();

        $this->assertSame('confirmed', $doc->fresh()->status);
        $this->assertSame(0, $this->plus($html, '/suppliers') + $this->plus($html, '/recipients') + $this->plus($html, '/departments'));
    }

    public function test_record_order_popup_has_a_supplier_plus_but_not_for_the_item(): void
    {
        $html = $this->actingAs($this->entry)->get(route('home'))->assertOk()->assertSee('id="reorder-modal"', false)->getContent();
        $this->assertSame(1, $this->plus($html, '/suppliers'));
        $this->assertSame(1, substr_count($html, 'data-dl-select-add'));   // item_id tidak diberi plus

        $group = UserGroup::create(['code' => 'ISSO', 'name' => 'Pengeluaran']);
        $group->syncAccess(['ISS' => 'rw', 'SUP' => 'ro']);
        $html = $this->actingAs(User::factory()->create(['group_id' => $group->id]))->get(route('home'))->assertOk()->getContent();
        $this->assertSame(0, substr_count($html, 'data-dl-select-add'));
    }

    public function test_recipient_form_has_a_department_plus(): void
    {
        $this->assertSame(1, $this->plus($this->actingAs($this->entry)->get(route('recipients.create'))->assertOk()->getContent(), '/departments'));
    }

    public function test_product_form_plus_buttons_follow_the_supervisor_only_masters(): void
    {
        $entry = $this->actingAs($this->entry)->get(route('products.create'))->assertOk()->getContent();
        $this->assertSame(0, substr_count($entry, 'data-dl-select-add'));

        $new = $this->actingAs($this->sup)->get(route('products.create'))->assertOk()->getContent();
        $this->assertSame([1, 1, 1], [$this->plus($new, '/categories'), $this->plus($new, '/units'), $this->plus($new, '/locations')]);
        $this->assertSame(3, substr_count($new, 'data-dl-select-add'));

        $show = $this->get(route('products.show', $this->item->item_id))->assertOk()->getContent();
        $this->assertSame(2, $this->plus($show, '/units'));   // satuan stok + dialog Satuan masuk (pack_unit)
        $this->assertSame(1, $this->plus($show, '/locations'));

        $showEntry = $this->actingAs($this->entry)->get(route('products.show', $this->item->item_id))->assertOk()->getContent();
        $this->assertSame(0, substr_count($showEntry, 'data-dl-select-add'));
    }

    public function test_filters_pickers_and_user_form_get_no_plus(): void
    {
        $this->actingAs($this->sup);
        foreach (['reports.summary', 'reports.receiving', 'reports.expiry', 'reports.adjustment', 'reports.stock-card'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('data-dl-select-add', false);
        }
        $this->actingAs(User::factory()->administrator()->create())->get(route('users.create'))->assertOk()->assertDontSee('data-dl-select-add', false);
        $this->get(route('units.create'))->assertOk()->assertDontSee('data-dl-select-add', false);
    }
}
