<?php

namespace Tests\Feature;

use App\Enums\StockDocumentType;
use App\Models\Category;
use App\Models\Batch;
use App\Models\Location;
use App\Models\Item;
use App\Models\Recipient;
use App\Models\StockDocument;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga model Barang dua tingkat: kode produk otomatis {AWALAN}-{URUT} tanpa tanggal, batch per tanggal kedaluwarsa ({kode produk}-{YYMMDD}) yang dibuat oleh Penerimaan,
 *                pengeluaran FEFO, titik pesan ulang per produk (stok kedaluwarsa tidak dihitung), master Kategori (awalan 3 karakter) dan Lokasi Penyimpanan, serta peringatan kedaluwarsa.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class ItemCodeTest extends TestCase
{
    use RefreshDatabase;

    private function payload(Category $cat, array $extra = []): array
    {
        return array_merge(['name' => 'Barang uji', 'category_id' => $cat->id, 'unit' => 'pcs', 'unit_price' => 100, 'reorder_point' => 1, 'reorder_qty' => 5, 'lead_time_days' => 1], $extra);
    }

    private function product(array $extra = [], ?string $code = null): Item
    {
        $cat = Category::firstOrCreate(['code' => 'ATK'], ['name' => 'ATK']);

        return Item::register($this->payload($cat, $extra), $code);
    }

    // Penerimaan (draf lalu Confirm) lewat jalur resmi; baris = [produk, jumlah, tanggal kedaluwarsa].
    private function receive(array $lines, ?User $by = null): void
    {
        $docs = app(StockDocuments::class);
        $by ??= User::factory()->create();
        $doc = $docs->create(StockDocumentType::Receiving, ['supplier_id' => Supplier::firstOrCreate(['code' => 'S1'], ['name' => 'S'])->id], array_map(
            fn ($l) => ['item_id' => $l[0]->id, 'qty' => $l[1], 'unit_cost' => 10, 'expiry_date' => $l[2] ?? null], $lines), $by);
        $docs->confirm($doc, $by);
    }

    public function test_product_code_follows_category_prefix_and_running_number(): void
    {
        $atk = Category::create(['code' => 'ATK', 'name' => 'ATK']);
        $tls = Category::create(['code' => 'TLS', 'name' => 'Tools']);
        $this->actingAs(User::factory()->supervisor()->create());

        $this->post(route('products.store'), $this->payload($atk))->assertRedirect();
        $this->post(route('products.store'), $this->payload($atk, ['tracks_expiry' => 1]))->assertRedirect();
        $this->post(route('products.store'), $this->payload($tls))->assertRedirect();

        $this->assertEqualsCanonicalizing(['ATK-0001', 'ATK-0002', 'TLS-0001'], Item::pluck('code')->all());
        // Barang biasa langsung punya satu batch berkode sama; barang berkedaluwarsa belum punya batch sampai ada penerimaan bertanggal.
        $this->assertEqualsCanonicalizing(['ATK-0001', 'TLS-0001'], Batch::pluck('code')->all());
    }

    public function test_data_entry_can_add_an_item_but_only_the_supervisor_completes_its_parameters(): void
    {
        $atk = Category::create(['code' => 'ATK', 'name' => 'ATK']);

        $this->actingAs(User::factory()->create())->post(route('products.store'), ['name' => 'Spidol', 'category_id' => $atk->id, 'unit' => 'pcs'])->assertRedirect();
        $product = Item::firstOrFail();
        $this->assertSame([0.0, 0.0, 'ATK-0001'], [$product->unit_price, $product->reorder_point, $product->code]);

        $this->put(route('products.update', $product), ['_action' => 'save', 'name' => 'X', 'unit' => 'pcs'])->assertForbidden();
    }

    public function test_code_category_and_expiry_flag_are_fixed_after_saving(): void
    {
        $atk = Category::create(['code' => 'ATK', 'name' => 'ATK']);
        $tls = Category::create(['code' => 'TLS', 'name' => 'Tools']);
        $this->actingAs(User::factory()->supervisor()->create());
        $this->post(route('products.store'), $this->payload($atk, ['tracks_expiry' => 1]));
        $product = Item::firstOrFail();

        $this->put(route('products.update', $product), ['_action' => 'save'] + $this->payload($tls, ['name' => 'Baru', 'code' => 'HACK', 'tracks_expiry' => 0]))->assertRedirect();

        $product->refresh();
        $this->assertSame(['ATK-0001', $atk->id, true, 'Baru'], [$product->code, $product->category_id, $product->tracks_expiry, $product->name]);
    }

    public function test_category_prefix_must_be_three_characters_and_unique(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());

        $this->post(route('categories.store'), ['code' => 'ab', 'name' => 'Pendek'])->assertSessionHasErrors('code');
        $this->post(route('categories.store'), ['code' => 'abcd', 'name' => 'Panjang'])->assertSessionHasErrors('code');
        $this->post(route('categories.store'), ['code' => 'abc', 'name' => 'Boleh'])->assertRedirect();
        $this->assertDatabaseHas('m_category', ['code' => 'ABC']);
        $this->post(route('categories.store'), ['code' => 'ABC', 'name' => 'Kembar'])->assertSessionHasErrors('code');
    }

    public function test_items_can_use_only_active_locations_and_categories(): void
    {
        $cat = Category::create(['code' => 'ATK', 'name' => 'ATK']);
        Location::create(['code' => 'A1', 'name' => 'Rak A1']);
        Location::create(['code' => 'OLD', 'name' => 'Rak lama', 'is_active' => false]);
        $off = Category::create(['code' => 'OFF', 'name' => 'Nonaktif', 'is_active' => false]);
        $this->actingAs(User::factory()->supervisor()->create());

        $this->post(route('products.store'), $this->payload($cat, ['location' => 'A1']))->assertRedirect();
        $this->post(route('products.store'), $this->payload($cat, ['location' => 'GHOST']))->assertSessionHasErrors('location');
        $this->post(route('products.store'), $this->payload($cat, ['location' => 'OLD']))->assertSessionHasErrors('location');
        $this->post(route('products.store'), $this->payload($off))->assertSessionHasErrors('category_id');
        $this->assertSame(1, Item::count());
    }

    public function test_location_master_is_supervisor_only_and_code_stays(): void
    {
        $this->actingAs(User::factory()->create())->get(route('locations.index'))->assertForbidden();

        $this->actingAs(User::factory()->supervisor()->create());
        $this->post(route('locations.store'), ['code' => 'b2-01', 'name' => 'Rak B2'])->assertRedirect();
        $loc = Location::where('code', 'B2-01')->firstOrFail();

        $this->put(route('locations.update', $loc), ['_action' => 'save', 'code' => 'ZZ', 'name' => 'Rak B2 baru'])->assertRedirect();
        $this->assertSame(['B2-01', 'Rak B2 baru'], [$loc->fresh()->code, $loc->fresh()->name]);
        $this->get(route('locations.index'))->assertOk();
        $this->get(route('categories.index'))->assertOk();
    }

    public function test_receiving_creates_one_batch_per_expiry_date_and_reuses_the_same_date(): void
    {
        $lem = $this->product(['tracks_expiry' => true, 'name' => 'Lem']);

        $this->receive([[$lem, 10, '2026-03-15']]);
        $this->receive([[$lem, 5, '2026-03-15'], [$lem, 20, '2026-06-20']]);

        $this->assertEqualsCanonicalizing(['ATK-0001-260315' => 15.0, 'ATK-0001-260620' => 20.0], $lem->batches()->pluck('stock_qty', 'code')->all());
        $this->assertSame(35.0, (float) $lem->batches()->sum('stock_qty'));
    }

    public function test_an_expiring_product_needs_a_date_on_every_receiving_line_and_unused_batches_are_removed(): void
    {
        $lem = $this->product(['tracks_expiry' => true, 'name' => 'Lem']);
        $sup = Supplier::create(['code' => 'S1', 'name' => 'S']);
        $this->actingAs(User::factory()->create());

        $line = ['item_id' => $lem->id, 'qty' => 5, 'unit_cost' => 10];
        $this->post(route('docs.store', 'receiving'), ['supplier_id' => $sup->id, 'lines' => [$line]])->assertSessionHasErrors('lines.0.expiry_date');

        $this->post(route('docs.store', 'receiving'), ['supplier_id' => $sup->id, 'lines' => [$line + ['expiry_date' => today()->addMonths(6)->toDateString()]]])->assertRedirect();   // tanggal lampau ditolak di Penerimaan
        $doc = StockDocument::firstOrFail();
        $this->assertSame(1, $lem->batches()->count());                                       // batch dibuat oleh draf

        $this->put(route('docs.update', ['receiving', $doc]), ['_action' => 'delete'])->assertRedirect();
        $this->assertSame(0, $lem->batches()->count());                                       // draf dihapus: batch yang tidak terpakai ikut hilang
    }

    public function test_issuance_spills_over_to_the_next_batch_in_fefo_order_and_warns_about_a_later_pick(): void
    {
        $lem = $this->product(['tracks_expiry' => true, 'name' => 'Lem']);
        $future = today()->addYear();
        $this->receive([[$lem, 30, $future->copy()->addMonths(4)->toDateString()], [$lem, 10, $future->toDateString()]]);
        $first = $lem->batchFor($future->toDateString());                                  // kedaluwarsa lebih cepat, stok 10
        $second = $lem->batchFor($future->copy()->addMonths(4)->toDateString());           // stok 30
        $docs = app(StockDocuments::class);
        $user = User::factory()->create();
        $recipient = Recipient::create(['name' => 'Budi']);

        // Mengeluarkan 15 dengan memilih batch pertama (stok 10): 10 darinya, sisa 5 dari batch berikutnya.
        $doc = $docs->create(StockDocumentType::Issuance, ['recipient_id' => $recipient->id], [['batch_id' => $first->id, 'qty' => 15]], $user);
        $this->assertEqualsCanonicalizing([[$first->id, 10.0], [$second->id, 5.0]], $doc->lines->map(fn ($l) => [$l->batch_id, $l->qty])->all());
        $this->assertSame([], $docs->fefoWarnings($doc));            // draf: batch pertama habis diambil dokumen yang sama, jadi sambungan ke batch kedua bukan pelanggaran FEFO
        $docs->confirm($doc, $user);
        $this->assertSame([0.0, 25.0], [$first->fresh()->stock_qty, $second->fresh()->stock_qty]);
        $this->assertSame([], $docs->fefoWarnings($doc->fresh()));   // batch pertama sudah habis: tidak ada yang lebih cepat kedaluwarsa

        // Memilih batch yang kedaluwarsanya lebih jauh padahal yang lebih cepat masih ada stok: hanya peringatan.
        $this->receive([[$lem, 4, $future->toDateString()]]);
        $later = $docs->create(StockDocumentType::Issuance, ['recipient_id' => $recipient->id], [['batch_id' => $second->id, 'qty' => 1]], $user);
        $this->assertStringContainsString('Keluarkan yang kedaluwarsa lebih dulu', implode(' ', $docs->fefoWarnings($later)));
    }

    public function test_reorder_point_counts_the_available_stock_of_the_whole_product_without_expired_batches(): void
    {
        $lem = $this->product(['tracks_expiry' => true, 'name' => 'Lem', 'reorder_point' => 10]);
        $this->receive([[$lem, 50, today()->subDays(5)->toDateString()], [$lem, 5, today()->addDays(90)->toDateString()]]);

        // 55 di rak, tetapi 50 sudah kedaluwarsa: tersedia 5 <= titik 10.
        $this->assertTrue($lem->fresh()->isBelowReorderPoint());
        $this->assertSame([$lem->id], Item::belowReorderPoint()->pluck('id')->all());

        $this->receive([[$lem, 20, today()->addDays(120)->toDateString()]]);                // dua batch sehat: 25 > 10
        $this->assertFalse($lem->fresh()->isBelowReorderPoint());
        $this->assertSame([], Item::belowReorderPoint()->pluck('id')->all());
    }

    public function test_expiry_state_and_alerts_only_count_active_items_that_still_have_stock(): void
    {
        $mk = function (string $name, int $days, float $stock, bool $active = true) {
            $item = $this->product(['tracks_expiry' => true, 'name' => $name])->batchFor(today()->addDays($days));
            $item->forceFill(['stock_qty' => $stock, 'is_active' => $active])->save();

            return $item;
        };
        $expired = $mk('Uji Lama', -3, 5);
        $soon = $mk('Uji Sebentar', 10, 5);
        $mk('Uji Jauh', 90, 5);              // masih lama
        $mk('Uji Habis', -3, 0);             // stok habis: tidak ada yang dibuang
        $mk('Uji Nonaktif', -3, 5, false);   // nonaktif

        $this->assertSame(['KEDALUWARSA', 'HAMPIR'], [$expired->expiryState(), $soon->expiryState()]);
        $this->assertSame(['Uji Lama', 'Uji Sebentar'], Batch::nearExpiry()->orderBy('expiry_date')->pluck('name')->all());

        $this->actingAs(User::factory()->supervisor()->create());
        $this->get(route('stock.home'))->assertOk()->assertSee('Pisahkan dari rak');
        $report = $this->get(route('reports.expiry'))->assertOk()->assertSee('KEDALUWARSA')->assertSee('HAMPIR')->getContent();
        $this->assertStringNotContainsString('Uji Jauh', $report);
        $this->assertStringContainsString('Uji Jauh', $this->get(route('reports.expiry', ['days' => 120]))->getContent());
    }

    public function test_the_sample_data_builds_and_shows_the_expiry_alerts(): void
    {
        $this->seed();

        $this->assertSame(0, Batch::whereNull('location')->count());
        $this->assertSame(0, Batch::whereNull('item_id')->count());
        $this->assertSame(Location::count(), Location::whereIn('code', Batch::pluck('location'))->count());
        $this->assertSame(2, Batch::nearExpiry()->count());  // satu sudah lewat, satu hampir; dua batch lain masih lama
        $this->assertSame(2, Item::where('name', 'Lem Kayu Fox 100 ml')->firstOrFail()->batches()->count());   // satu produk, dua batch
        $this->actingAs(User::where('username', 'spv')->firstOrFail())->get(route('stock.home'))->assertOk()->assertSee('Lem Kayu Fox 100 ml');
    }
}
