<?php

namespace Tests\Feature;

use App\Enums\StockDocumentType;
use App\Models\Category;
use App\Models\Location;
use App\Models\StockCount;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\StockDocuments;
use App\Support\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga layar daftar / mulai Stok Opname: menu hanya untuk yang berhak, Data Entry tidak bisa memulai / membatalkan / membuka kunci, validasi cakupan, satu opname berjalan, pemberian modul ke grup.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class StockCountStartScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $sup;

    private User $entry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sup = User::factory()->supervisor()->create();
        $this->entry = User::factory()->create();
        $category = Category::create(['code' => 'ATK', 'name' => 'ATK']);
        Location::create(['code' => 'RAK-A', 'name' => 'Rak A']);
        $item = $this->makeItem(['code' => 'A1', 'name' => 'Item A1', 'category_id' => $category->id, 'unit' => 'pcs', 'unit_price' => 100, 'location' => 'RAK-A']);
        $docs = app(StockDocuments::class);
        $docs->confirm($docs->create(StockDocumentType::Receiving, ['supplier_id' => Supplier::create(['code' => 'S1', 'name' => 'S'])->id], [['item_id' => $item->id, 'qty' => 10, 'unit_cost' => 100]], $this->sup), $this->sup);
    }

    private function payload(array $over = []): array
    {
        return $over + ['count_date' => today()->toDateString(), 'scope_type' => 'all', 'threshold_pct' => 5, 'blind' => 1, 'lock_transactions' => 1];
    }

    private function codes(User $user): array
    {
        return collect(Menu::modules($user))->flatMap(fn ($m) => collect($m['children'])->pluck('code'))->all();
    }

    public function test_menu_shows_stok_opname_to_supervisor_and_data_entry(): void
    {
        $this->assertContains('SO', $this->codes($this->sup));
        $this->assertContains('SO', $this->codes($this->entry));
        $this->actingAs($this->entry)->get(route('home'))->assertOk()->assertSee('Stok Opname');
    }

    public function test_menu_is_hidden_for_a_group_without_the_module(): void
    {
        $group = UserGroup::create(['code' => 'GD', 'name' => 'Gudang']);
        $group->syncAccess(['ISS' => 'rw']);
        $user = User::factory()->create(['group_id' => $group->id]);

        $this->assertNotContains('SO', $this->codes($user));
        $this->actingAs($user)->get(route('stock-counts.index'))->assertForbidden();
    }

    public function test_list_is_open_to_data_entry_but_starting_is_supervisor_only(): void
    {
        $this->actingAs($this->entry)->get(route('stock-counts.index'))->assertOk()->assertDontSee('Mulai Opname');
        $this->get(route('stock-counts.create'))->assertForbidden();
        $this->post(route('stock-counts.store'), $this->payload())->assertForbidden();
        $this->assertSame(0, StockCount::count());

        $this->actingAs($this->sup)->get(route('stock-counts.index'))->assertOk()->assertSee('Mulai Opname');
        $this->get(route('stock-counts.create'))->assertOk()->assertSee('Hitung buta')->assertSee('Kunci transaksi selama opname');
    }

    public function test_supervisor_starts_a_count_and_everyone_with_access_can_open_it(): void
    {
        $this->actingAs($this->sup)->post(route('stock-counts.store'), $this->payload(['note' => 'Opname bulanan']))->assertRedirect();
        $count = StockCount::firstOrFail();
        $this->assertSame(['counting', true, true, 5.0, 1], [$count->status, $count->blind, $count->lock_transactions, $count->threshold_pct, $count->lines()->count()]);
        $this->assertSame($this->sup->id, $count->created_by);

        foreach ([$this->sup, $this->entry] as $user) {
            $this->actingAs($user)->get(route('stock-counts.show', $count))->assertOk()->assertSee($count->doc_no)->assertSee('Sedang dihitung');
        }
        $this->actingAs($this->entry)->get(route('stock-counts.index'))->assertSee($count->doc_no)->assertSee('0 \/ 1', false);   // baris grid dikirim sebagai JSON (garis miring di-escape)
    }

    public function test_switches_default_off_when_unchecked_and_scope_needs_its_value(): void
    {
        $this->actingAs($this->sup)->post(route('stock-counts.store'), $this->payload(['scope_type' => 'category']))->assertSessionHasErrors('category_code');
        $this->post(route('stock-counts.store'), $this->payload(['scope_type' => 'location']))->assertSessionHasErrors('location_code');
        $this->post(route('stock-counts.store'), $this->payload(['threshold_pct' => 101]))->assertSessionHasErrors('threshold_pct');
        $this->assertSame(0, StockCount::count());

        $payload = $this->payload(['scope_type' => 'location', 'location_code' => 'RAK-A']);
        unset($payload['blind'], $payload['lock_transactions']);
        $this->post(route('stock-counts.store'), $payload)->assertRedirect();
        $count = StockCount::firstOrFail();
        $this->assertSame([false, false, 'RAK-A'], [$count->blind, $count->lock_transactions, $count->scope_value]);
    }

    public function test_only_one_running_count_and_scope_without_stock_is_refused_with_a_clear_message(): void
    {
        $this->actingAs($this->sup)->post(route('stock-counts.store'), $this->payload())->assertRedirect();
        $first = StockCount::firstOrFail();

        $this->post(route('stock-counts.store'), $this->payload())->assertSessionHasErrors(['scope_type' => "Stok Opname {$first->doc_no} masih berjalan (Sedang dihitung). Hanya satu opname boleh berjalan: selesaikan atau batalkan dulu sebelum memulai yang baru."]);
        $this->post(route('stock-counts.cancel', $first))->assertRedirect();

        Location::create(['code' => 'RAK-B', 'name' => 'Rak B']);
        $this->post(route('stock-counts.store'), $this->payload(['scope_type' => 'location', 'location_code' => 'RAK-B']))->assertSessionHasErrors('scope_type');
        $this->assertSame(1, StockCount::count());
    }

    public function test_cancel_and_unlock_are_supervisor_only_and_unlock_is_recorded(): void
    {
        $this->actingAs($this->sup)->post(route('stock-counts.store'), $this->payload());
        $count = StockCount::firstOrFail();

        $this->actingAs($this->entry)->post(route('stock-counts.unlock', $count))->assertForbidden();
        $this->post(route('stock-counts.cancel', $count))->assertForbidden();
        $this->assertSame(['counting', true], [$count->fresh()->status, $count->fresh()->lock_transactions]);

        $this->actingAs($this->sup)->post(route('stock-counts.unlock', $count))->assertRedirect();
        $fresh = $count->fresh();
        $this->assertSame([false, $this->sup->id], [$fresh->lock_transactions, $fresh->unlocked_by]);
        $this->assertNotNull($fresh->unlocked_at);
        $this->get(route('stock-counts.show', $count))->assertSee('Dibuka');
        $this->post(route('stock-counts.unlock', $count))->assertStatus(422);   // sudah terbuka

        $this->post(route('stock-counts.lock', $count))->assertRedirect();   // Supervisor mengunci kembali: tercatat siapa dan kapan
        $locked = $count->fresh();
        $this->assertSame([true, $this->sup->id], [$locked->lock_transactions, $locked->locked_by]);
        $this->assertNotNull($locked->locked_at);
        $this->get(route('stock-counts.show', $count))->assertSee('Dikunci');
        $this->post(route('stock-counts.lock', $count))->assertStatus(422);   // sudah terkunci
        $this->actingAs($this->entry)->post(route('stock-counts.lock', $count))->assertForbidden();

        $this->actingAs($this->sup)->post(route('stock-counts.cancel', $count))->assertRedirect();
        $this->assertSame('cancelled', $count->fresh()->status);
    }

    public function test_read_only_module_access_cannot_start(): void
    {
        $group = UserGroup::create(['code' => 'RO', 'name' => 'Lihat']);
        $group->syncAccess(['SO' => 'ro']);
        $sup = User::factory()->supervisor()->create(['group_id' => $group->id]);

        $this->actingAs($sup)->get(route('stock-counts.index'))->assertOk();
        $this->post(route('stock-counts.store'), $this->payload())->assertForbidden();
    }

    public function test_migration_gives_the_module_to_groups_that_write_adjustment_or_receiving_only(): void
    {
        $adj = UserGroup::create(['code' => 'A', 'name' => 'A']);
        $adj->syncAccess(['ADJ' => 'rw']);
        $rcv = UserGroup::create(['code' => 'B', 'name' => 'B']);
        $rcv->syncAccess(['RCV' => 'rw', 'ISS' => 'rw']);
        $ro = UserGroup::create(['code' => 'C', 'name' => 'C']);
        $ro->syncAccess(['ADJ' => 'ro', 'RCV' => 'ro']);
        $none = UserGroup::create(['code' => 'D', 'name' => 'D']);
        $none->syncAccess(['ISS' => 'rw']);

        $this->runLegacyGroupMigration('2026_11_14_110000_give_stock_count_to_groups.php');
        $this->runLegacyGroupMigration('2026_11_14_110000_give_stock_count_to_groups.php');   // aman diulang

        $this->assertSame('rw', $adj->moduleAccess()['SO']);
        $this->assertSame('rw', $rcv->moduleAccess()['SO']);
        $this->assertArrayNotHasKey('SO', $ro->moduleAccess());
        $this->assertArrayNotHasKey('SO', $none->moduleAccess());
    }
}
