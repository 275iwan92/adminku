<?php

namespace Tests\Feature;

use App\Enums\StockDocumentType;
use App\Models\Category;
use App\Models\Item;
use App\Models\StockCount;
use App\Models\StockDocument;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockCounts;
use App\Services\StockDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga mesin Stok Opname: snapshot per cakupan, baris kosong / 0 / Tidak dihitung, Selesai hitung, kunci transaksi (satu penjaga di buku besar), selisih, alasan wajib,
 *                stok bergeser, satu Penyesuaian approved, hitung ulang, kembalikan, batal, nomor SO.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class StockCountEngineTest extends TestCase
{
    use RefreshDatabase;

    private User $sup;

    private StockCounts $counts;

    private Category $atk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sup = User::factory()->supervisor()->create();
        $this->counts = app(StockCounts::class);
        $this->atk = Category::create(['code' => 'ATK', 'name' => 'ATK']);
    }

    private function item(string $code, float $qty, float $cost = 100, array $extra = []): Item
    {
        $item = $this->makeItem(['code' => $code, 'name' => "Item {$code}", 'category_id' => $extra['category_id'] ?? $this->atk->id, 'unit' => 'pcs', 'unit_price' => $cost, 'location' => $extra['location'] ?? null]);
        if ($qty > 0) {
            $this->receive($item, $qty, $cost);
        }

        return $item->refresh();
    }

    private function receive(Item $item, float $qty, float $cost): StockDocument
    {
        $docs = app(StockDocuments::class);
        $doc = $docs->create(StockDocumentType::Receiving, ['supplier_id' => Supplier::firstOrCreate(['code' => 'S1'], ['name' => 'S1'])->id], [['item_id' => $item->id, 'qty' => $qty, 'unit_cost' => $cost]], $this->sup);
        $docs->confirm($doc, $this->sup);

        return $doc;
    }

    private function start(array $over = []): StockCount
    {
        return $this->counts->start($this->sup, $over + ['count_date' => today()->toDateString(), 'scope_type' => 'all', 'blind' => true, 'lock_transactions' => true, 'threshold_pct' => 5]);
    }

    private function fill(StockCount $c, Item $item, mixed $qty, array $in = []): void
    {
        $this->counts->saveLine($c, $c->lines()->where('item_id', $item->id)->value('id'), ['counted_qty' => $qty] + $in);
    }

    private function line(StockCount $c, Item $item)
    {
        return $c->lines()->where('item_id', $item->id)->first();
    }

    public function test_snapshot_follows_scope_and_keeps_system_qty_and_average_cost(): void
    {
        $other = Category::create(['code' => 'CLN', 'name' => 'Kebersihan']);
        $a = $this->item('A1', 10, 1500, ['location' => 'RAK-A']);
        $b = $this->item('B1', 4, 200, ['location' => 'RAK-B', 'category_id' => $other->id]);
        $empty = $this->item('E1', 0, 100, ['location' => 'RAK-A']);
        $off = $this->item('X1', 5, 100);
        $off->forceFill(['is_active' => false])->save();
        $this->receive($a, 10, 2500);   // biaya rata-rata menjadi 2000

        $all = $this->start();
        $this->assertMatchesRegularExpression('/^SO-\d{4}-0001$/', $all->doc_no);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $all->lines()->pluck('item_id')->all());   // stok 0 dan nonaktif tidak ikut
        $line = $this->line($all, $a);
        $this->assertSame(20.0, $line->system_qty);
        $this->assertSame(2000.0, $line->unit_cost);
        $this->assertNull($line->counted_qty);
        $this->counts->cancel($all, $this->sup);

        $byCategory = $this->start(['scope_type' => 'category', 'scope_value' => 'CLN']);
        $this->assertSame([$b->id], $byCategory->lines()->pluck('item_id')->all());
        $this->counts->cancel($byCategory, $this->sup);

        $byLocation = $this->start(['scope_type' => 'location', 'scope_value' => 'RAK-A']);
        $this->assertSame([$a->id], $byLocation->lines()->pluck('item_id')->all());
        $this->assertSame('RAK-A', $byLocation->scope_value);
        $this->assertSame(0.0, $empty->stock_qty);
    }

    public function test_scope_without_stock_and_second_running_count_are_refused(): void
    {
        $this->item('A1', 3);
        $this->expectValidation(fn () => $this->start(['scope_type' => 'location', 'scope_value' => 'NOWHERE']), 'Tidak ada batch berstok');

        $first = $this->start();
        $this->expectValidation(fn () => $this->start(), "Stok Opname {$first->doc_no} masih berjalan");
        $this->counts->cancel($first, $this->sup);
        $this->assertNotNull($this->start());   // sesudah dibatalkan boleh memulai lagi
    }

    private function expectValidation(callable $fn, string $contains): void
    {
        try {
            $fn();
            $this->fail('Seharusnya ditolak: '.$contains);
        } catch (ValidationException $e) {
            $this->assertStringContainsString($contains, $e->validator->errors()->first());
        } catch (HttpException $e) {
            $this->assertStringContainsString($contains, $e->getMessage());
        }
    }

    public function test_blank_zero_and_skipped_lines_and_partial_save(): void
    {
        [$a, $b, $c] = [$this->item('A1', 10), $this->item('B1', 5), $this->item('C1', 2)];
        $sheet = $this->start();

        $this->fill($sheet, $a, 7);
        $this->fill($sheet, $b, 0);   // 0 = memang kosong, bukan belum dihitung
        $this->assertSame(7.0, $this->line($sheet, $a)->counted_qty);
        $this->assertTrue($this->line($sheet, $b)->isFilled());
        $this->assertSame(0.0, $this->line($sheet, $b)->counted_qty);
        $this->assertFalse($this->line($sheet, $c)->isFilled());   // penyimpanan sebagian: C1 tetap belum dihitung

        $this->fill($sheet, $b, '');   // dikosongkan lagi = belum dihitung
        $this->assertFalse($this->line($sheet, $b)->isFilled());

        $this->fill($sheet, $c, '', ['skipped' => true]);
        $this->assertTrue($this->line($sheet, $c)->skipped);
        $this->assertFalse($this->line($sheet, $c)->isComplete());   // Tidak dihitung butuh catatan
        $this->fill($sheet, $c, '', ['skipped' => true, 'note' => 'rak terkunci']);
        $this->assertTrue($this->line($sheet, $c)->isComplete());

        $this->fill($sheet, $c, 2, ['skipped' => true, 'note' => 'rak terkunci']);   // mengisi hasil melepas tanda Tidak dihitung
        $this->assertFalse($this->line($sheet, $c)->skipped);
        $this->fill($sheet, $c, 2, ['skipped' => true]);   // menandai Tidak dihitung mengosongkan hasil
        $this->assertNull($this->line($sheet, $c)->counted_qty);

        $this->expectValidation(fn () => $this->fill($sheet, $a, -1), 'angka 0 atau lebih');
        $this->expectValidation(fn () => $this->fill($sheet, $a, 'abc'), 'angka 0 atau lebih');
    }

    public function test_remembers_the_counter_name_and_defaults_it_from_the_session_value(): void
    {
        $a = $this->item('A1', 10);
        $sheet = $this->start();

        $this->counts->saveLine($sheet, $this->line($sheet, $a)->id, ['counted_qty' => 9, 'counted_by' => ' Budi '], null);
        $this->assertSame('Budi', $this->line($sheet, $a)->counted_by);
        $this->counts->saveLine($sheet, $this->line($sheet, $a)->id, ['counted_qty' => 8], 'Sari');   // tanpa nama: memakai nama terakhir yang diingat
        $this->assertSame('Sari', $this->line($sheet, $a)->counted_by);
    }

    public function test_finish_is_refused_while_lines_are_open_and_lists_them(): void
    {
        [$a, $b] = [$this->item('A1', 10), $this->item('B1', 5)];
        $sheet = $this->start();
        $this->fill($sheet, $a, 10);

        $this->expectValidation(fn () => $this->counts->finish($sheet, $this->sup), 'B1');
        $this->assertSame('counting', $sheet->fresh()->status);

        $this->fill($sheet, $b, '', ['skipped' => true]);
        $this->expectValidation(fn () => $this->counts->finish($sheet, $this->sup), 'B1');   // Tidak dihitung tanpa catatan belum lengkap

        $this->fill($sheet, $b, '', ['skipped' => true, 'note' => 'tidak ditemukan']);
        $this->counts->finish($sheet, $this->sup);
        $this->assertSame('review', $sheet->fresh()->status);
        $this->expectValidation(fn () => $this->fill($sheet, $a, 1), 'tidak diizinkan');   // setelah Selesai hitung baris terkunci
    }

    public function test_lock_refuses_posting_while_counting_and_allows_it_after_done_cancel_or_unlock(): void
    {
        $a = $this->item('A1', 10);
        $docs = app(StockDocuments::class);
        $supplier = Supplier::first();
        $draft = fn () => $docs->create(StockDocumentType::Receiving, ['supplier_id' => $supplier->id], [['item_id' => $a->id, 'qty' => 1, 'unit_cost' => 100]], $this->sup);
        $out = fn () => $docs->create(StockDocumentType::Issuance, ['recipient' => 'Budi'], [['item_id' => $a->id, 'qty' => 1]], $this->sup);
        $adj = $docs->create(StockDocumentType::Adjustment, ['reason' => 'x'], [['item_id' => $a->id, 'counted_qty' => 9]], $this->sup);
        $docs->submit($adj);

        $sheet = $this->start();
        $doc = $draft();   // draf boleh disimpan saat dikunci
        $issue = $out();
        $this->expectValidation(fn () => $docs->confirm($doc, $this->sup), "Stok Opname {$sheet->doc_no} sedang berjalan, transaksi dikunci. Hubungi Supervisor.");
        $this->expectValidation(fn () => $docs->confirm($issue, $this->sup), 'transaksi dikunci');
        $this->expectValidation(fn () => $docs->approve($adj, $this->sup), 'transaksi dikunci');
        $this->assertSame(10.0, $a->fresh()->stock_qty);

        $this->counts->unlock($sheet, $this->sup);   // kunci dibuka: tercatat siapa dan kapan
        $this->assertSame($this->sup->id, $sheet->fresh()->unlocked_by);
        $this->assertNotNull($sheet->fresh()->unlocked_at);
        $docs->confirm($doc, $this->sup);
        $this->assertSame(11.0, $a->fresh()->stock_qty);
        $this->counts->cancel($sheet, $this->sup);

        $second = $this->start();   // kunci bawaan aktif lagi; dibatalkan -> bebas
        $this->expectValidation(fn () => $docs->confirm($issue, $this->sup), 'transaksi dikunci');
        $this->counts->cancel($second, $this->sup);
        $docs->confirm($issue, $this->sup);
        $this->assertSame(10.0, $a->fresh()->stock_qty);

        $third = $this->start();
        $this->fill($third, $a, 10);
        $this->counts->finish($third, $this->sup);
        $this->counts->approve($third, $this->sup);   // Selesai: bebas
        $docs->approve($adj, $this->sup);
        $this->assertSame(9.0, $a->fresh()->stock_qty);
    }

    public function test_lock_also_stops_cancelling_a_posted_document(): void
    {
        $a = $this->item('A1', 10);
        $doc = StockDocument::firstOrFail();
        $this->start();

        $this->expectValidation(fn () => app(StockDocuments::class)->cancel($doc, $this->sup, 'salah input'), 'transaksi dikunci');
        $this->assertSame(10.0, $a->fresh()->stock_qty);
    }

    public function test_count_without_lock_does_not_block_transactions(): void
    {
        $a = $this->item('A1', 10);
        $this->start(['lock_transactions' => false]);

        $this->receive($a, 1, 100);
        $this->assertSame(11.0, $a->fresh()->stock_qty);
    }

    public function test_approve_posts_one_approved_adjustment_and_stock_becomes_the_physical_count(): void
    {
        [$a, $b, $c, $d] = [$this->item('A1', 10, 1500), $this->item('B1', 5, 200), $this->item('C1', 8, 100), $this->item('D1', 3, 100)];
        $sheet = $this->start(['note' => 'Opname Oktober']);
        $this->fill($sheet, $a, 8);       // -2
        $this->fill($sheet, $b, 5.5);     // +0,5 (10% > 5%)
        $this->fill($sheet, $c, 8);       // sama
        $this->fill($sheet, $d, '', ['skipped' => true, 'note' => 'rak tidak bisa dibuka']);
        $this->counts->finish($sheet, $this->sup);
        $this->counts->reviewLine($sheet, $this->line($sheet, $a)->id, ['reason' => 'Rusak']);
        $this->expectValidation(fn () => $this->counts->approve($sheet, $this->sup), 'B1');   // B1 selisih 10 persen tanpa alasan

        $this->counts->reviewLine($sheet, $this->line($sheet, $b)->id, ['reason' => 'Salah catat']);
        $docsBefore = StockDocument::count();
        $adj = $this->counts->approve($sheet, $this->sup);

        $this->assertSame($docsBefore + 1, StockDocument::count());
        $this->assertSame(StockDocumentType::Adjustment, $adj->type);
        $this->assertSame('approved', $adj->status);
        $this->assertSame("Stok Opname {$sheet->doc_no}", $adj->reason);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $adj->lines()->pluck('item_id')->all());   // hanya baris selisih
        $lineA = $adj->lines()->where('item_id', $a->id)->first();
        $this->assertSame([-2.0, 10.0, 8.0], [$lineA->qty, $lineA->system_qty, $lineA->counted_qty]);
        $this->assertSame([8.0, 5.5, 8.0, 3.0], [$a->fresh()->stock_qty, $b->fresh()->stock_qty, $c->fresh()->stock_qty, $d->fresh()->stock_qty]);
        $this->assertSame('done', $sheet->fresh()->status);
        $this->assertSame($adj->id, $sheet->fresh()->adjustment_document_id);
        $this->assertSame($this->sup->id, $sheet->fresh()->approved_by);
        $this->assertSame(1, StockMovement::where('stock_document_id', $adj->id)->where('item_id', $a->id)->where('type', 'ADJ')->count());
        $this->assertNull(StockCount::lockingNow());
        $this->expectValidation(fn () => $this->counts->approve($sheet, $this->sup), 'tidak diizinkan');   // tidak bisa diposting dua kali
        $this->assertSame(8.0, $a->fresh()->stock_qty);
    }

    public function test_approve_without_any_difference_creates_no_adjustment(): void
    {
        $a = $this->item('A1', 10);
        $sheet = $this->start();
        $this->fill($sheet, $a, 10);
        $this->counts->finish($sheet, $this->sup);
        $before = StockDocument::count();

        $this->assertNull($this->counts->approve($sheet, $this->sup));
        $this->assertSame($before, StockDocument::count());
        $this->assertSame('done', $sheet->fresh()->status);
        $this->assertNull($sheet->fresh()->adjustment_document_id);
    }

    public function test_difference_quantity_percent_and_value_are_exact(): void
    {
        $a = $this->item('A1', 3, 0.3333);
        $sheet = $this->start();
        $this->fill($sheet, $a, 2.001);
        $this->counts->finish($sheet, $this->sup);

        $row = $this->counts->reviewRows($sheet, true)[0];
        $this->assertSame([-0.999, -33.3, -0.33, 'Besar'], [$row['diff'], $row['pct'], $row['value'], $row['flag']]);
        $this->assertArrayNotHasKey('value', $this->counts->reviewRows($sheet, false)[0]);
        $sum = $this->counts->summary($sheet);
        $this->assertSame([1, 0, 1, -0.33, 0.0], [$sum['under_lines'], $sum['over_lines'], $sum['diffs'], $sum['under_value'], $sum['over_value']]);
    }

    public function test_stock_moved_since_cutoff_blocks_approval_until_the_line_is_refreshed(): void
    {
        [$a, $b] = [$this->item('A1', 10), $this->item('B1', 5)];
        $sheet = $this->start(['lock_transactions' => false]);
        $this->fill($sheet, $a, 9);
        $this->fill($sheet, $b, 5);
        $this->counts->finish($sheet, $this->sup);
        $this->receive($a, 4, 100);   // stok A1 bergeser dari 10 menjadi 14 sesudah cut-off
        $this->receive($b, 1, 100);   // B1 tanpa selisih: bergeser tetapi tidak menghalangi

        try {
            $this->counts->approve($sheet, $this->sup);
            $this->fail('Seharusnya ditolak');
        } catch (ValidationException $e) {
            $msg = $e->validator->errors()->first();
            $this->assertStringContainsString('A1: sistem 10, kini 14', $msg);
            $this->assertStringNotContainsString('B1', $msg);
        }
        $this->assertSame('review', $sheet->fresh()->status);
        $this->assertSame(14.0, $a->fresh()->stock_qty);

        $this->counts->refreshLine($sheet, $this->line($sheet, $a)->id);   // perbarui: sistem 14, baris kosong, kembali ke Sedang dihitung
        $this->assertSame('counting', $sheet->fresh()->status);
        $line = $this->line($sheet, $a);
        $this->assertSame([14.0, null], [$line->system_qty, $line->counted_qty]);
        $this->fill($sheet, $a, 13);
        $this->counts->finish($sheet, $this->sup);
        $this->counts->reviewLine($sheet, $this->line($sheet, $a)->id, ['reason' => 'Barang hilang']);
        $this->counts->approve($sheet, $this->sup);
        $this->assertSame(13.0, $a->fresh()->stock_qty);
    }

    public function test_recount_wins_and_reject_returns_to_counting_with_a_note(): void
    {
        $a = $this->item('A1', 10);
        $sheet = $this->start();
        $this->fill($sheet, $a, 4);
        $this->counts->finish($sheet, $this->sup);

        $this->counts->reviewLine($sheet, $this->line($sheet, $a)->id, ['recount_qty' => 10]);   // hitung ulang menemukan 10: tidak ada selisih
        $this->assertNull($this->counts->approve($sheet, $this->sup));

        $b = $this->item('B1', 6);
        $second = $this->start();
        $this->fill($second, $a, 10);
        $this->fill($second, $b, 1);
        $this->counts->finish($second, $this->sup);
        $this->expectValidation(fn () => $this->counts->reject($second, 'x'), 'catatan pengembalian');
        $this->counts->reject($second, 'Hitung ulang rak B');
        $fresh = $second->fresh();
        $this->assertSame(['counting', 'Hitung ulang rak B'], [$fresh->status, $fresh->review_note]);
        $this->fill($second, $b, 6);   // bisa diperbaiki lagi
        $this->assertSame(6.0, $this->line($second, $b)->counted_qty);
    }

    public function test_cancel_releases_the_lock_and_changes_no_stock(): void
    {
        $a = $this->item('A1', 10);
        $sheet = $this->start();
        $this->fill($sheet, $a, 2);
        $this->counts->finish($sheet, $this->sup);
        $this->assertNotNull(StockCount::lockingNow());

        $this->counts->cancel($sheet, $this->sup);
        $this->assertNull(StockCount::lockingNow());
        $this->assertSame(10.0, $a->fresh()->stock_qty);
        $this->assertSame('cancelled', $sheet->fresh()->status);
        $this->assertSame($this->sup->id, $sheet->fresh()->cancelled_by);
        $this->expectValidation(fn () => $this->counts->cancel($sheet, $this->sup), 'tidak diizinkan');
    }

    public function test_added_item_takes_current_stock_as_system_qty_once(): void
    {
        $a = $this->item('A1', 10, 100, ['location' => 'RAK-A']);
        $found = $this->item('B1', 7, 250, ['location' => 'RAK-B']);
        $sheet = $this->start(['scope_type' => 'location', 'scope_value' => 'RAK-A']);
        $this->assertNull($this->line($sheet, $found));

        $line = $this->counts->addItem($sheet, $found);
        $this->assertSame([7.0, 250.0, true, null], [$line->system_qty, $line->unit_cost, $line->added_manually, $line->counted_qty]);
        $this->expectValidation(fn () => $this->counts->addItem($sheet, $found), 'sudah ada di lembar');
        $this->assertSame(2, $sheet->lines()->count());
    }

    public function test_document_numbers_continue_per_month_and_survive_a_collision(): void
    {
        $this->item('A1', 3);
        $first = $this->start();
        $this->counts->cancel($first, $this->sup);
        $stem = substr($first->doc_no, 0, -4);

        $done = false;
        DB::listen(function ($query) use (&$done, $stem) {
            if (! $done && str_starts_with($query->sql, 'select') && str_contains($query->sql, '"i_count"') && str_contains($query->sql, 'doc_no')) {
                $done = true;
                DB::table('i_count')->insert(['doc_no' => $stem.'0002', 'count_date' => today(), 'status' => 'cancelled', 'cutoff_at' => now(), 'created_by' => $this->sup->id, 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        $second = $this->start();   // penyelip mengambil 0002 lebih dulu: nomor kita mengulang dan mendapat 0003
        $this->assertSame($stem.'0001', $first->doc_no);
        $this->assertSame($stem.'0003', $second->doc_no);
    }
}
