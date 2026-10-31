<?php

namespace Tests\Feature;

use App\Enums\StockDocumentType;
use App\Models\Category;
use App\Models\Batch;
use App\Models\StockCount;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CountSchedule;
use App\Services\StockCounts;
use App\Services\StockDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga jejak audit dan penjaga input Stok Opname: entered_by / entered_at / rev per baris, peringatan "baris sudah diubah orang lain" (menimpa hanya dengan alasan, mode Replace),
 *                barang di luar lembar dan baris Tidak dihitung tidak disentuh persetujuan, hasil Selesai permanen, opname tanpa selisih tetap Selesai (jadi acuan jadwal), kolom Diinput oleh dan peringatan penyetuju = penginput.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class StockCountAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $sup;

    private User $ana;

    private User $budi;

    /** @var array<string, Batch> */
    private array $items = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->sup = User::factory()->supervisor()->create(['name' => 'Sinta Supervisor']);
        $this->ana = User::factory()->create(['name' => 'Ana Entry']);
        $this->budi = User::factory()->create(['name' => 'Budi Entry']);
        $cat = Category::create(['code' => 'ATK', 'name' => 'ATK']);
        $docs = app(StockDocuments::class);
        $supplier = Supplier::create(['code' => 'S1', 'name' => 'S']);
        foreach ([['A1', 'RAK-A', 10], ['B1', 'RAK-A', 20], ['C1', 'RAK-A', 5], ['D1', 'RAK-B', 8]] as [$code, $loc, $qty]) {
            $item = $this->makeBatch(['code' => $code, 'name' => "Barang {$code}", 'category_id' => $cat->id, 'unit' => 'pcs', 'unit_price' => 100, 'location' => $loc]);
            $docs->confirm($docs->create(StockDocumentType::Receiving, ['supplier_id' => $supplier->id], [['batch_id' => $item->id, 'qty' => $qty, 'unit_cost' => 100]], $this->sup), $this->sup);
            $this->items[$code] = $item;
        }
    }

    private function start(array $over = []): StockCount
    {
        return app(StockCounts::class)->start($this->sup, $over + ['count_date' => today()->toDateString(), 'scope_type' => 'all', 'blind' => true, 'lock_transactions' => false, 'threshold_pct' => 5]);
    }

    private function lineId(StockCount $count, string $code): int
    {
        return $count->lines()->where('batch_id', $this->items[$code]->id)->value('id');
    }

    private function save(StockCount $count, array $row, ?string $reason = null)
    {
        return $this->postJson(route('stock-counts.lines', $count), ['changed' => [$row], 'reason' => $reason]);
    }

    public function test_every_save_records_who_entered_it_and_bumps_the_revision(): void
    {
        $count = $this->start();
        $id = $this->lineId($count, 'A1');

        $this->actingAs($this->ana)->save($count, ['id' => $id, 'counted_qty' => 9, 'counted_by' => 'Pak Joko', 'rev' => 0])->assertOk();
        $line = $count->lines()->find($id);
        $this->assertSame([$this->ana->id, 1, 'Pak Joko'], [$line->entered_by, $line->rev, $line->counted_by]);   // akun penginput terpisah dari teks penghitung di kertas
        $this->assertNotNull($line->entered_at);

        $rows = $this->save($count, ['id' => $id, 'counted_qty' => 9, 'rev' => 1])->assertOk()->json('rows');   // tanpa perubahan: rev tidak naik
        $this->assertSame(1, $count->lines()->find($id)->rev);
        $this->assertSame(1, collect($rows)->firstWhere('id', $id)['rev']);
    }

    public function test_a_row_changed_by_someone_else_is_not_overwritten_silently_and_overwriting_replaces_not_adds(): void
    {
        $count = $this->start();
        $id = $this->lineId($count, 'A1');
        $this->actingAs($this->ana)->save($count, ['id' => $id, 'counted_qty' => 9, 'rev' => 0])->assertOk();   // Ana menyimpan lebih dulu (rev 0 -> 1)

        $res = $this->actingAs($this->budi)->save($count, ['id' => $id, 'counted_qty' => 7, 'rev' => 0])->assertStatus(409);   // Budi masih memegang tampilan lama
        $res->assertJson(['reason_required' => true]);
        $this->assertStringContainsString('Baris A1 sudah diubah oleh Ana Entry pada', $res->json('error'));
        $this->assertSame(9.0, $count->lines()->find($id)->counted_qty);   // tidak ditimpa diam-diam

        $this->save($count, ['id' => $id, 'counted_qty' => 7, 'rev' => 0], 'Saya yang benar')->assertOk();   // Timpa: alasan diisi
        $line = $count->lines()->find($id);
        $this->assertSame([7.0, $this->budi->id, 2], [$line->counted_qty, $line->entered_by, $line->rev]);   // Replace: 7, bukan 9 + 7
    }

    public function test_a_conflict_in_one_row_of_a_batch_saves_none_of_the_batch(): void
    {
        $count = $this->start();
        [$a, $b] = [$this->lineId($count, 'A1'), $this->lineId($count, 'B1')];
        $this->actingAs($this->ana)->save($count, ['id' => $b, 'counted_qty' => 1, 'rev' => 0]);

        $this->actingAs($this->budi)->postJson(route('stock-counts.lines', $count), ['changed' => [['id' => $a, 'counted_qty' => 5, 'rev' => 0], ['id' => $b, 'counted_qty' => 6, 'rev' => 0]]])->assertStatus(409);
        $this->assertNull($count->lines()->find($a)->counted_qty);
        $this->assertSame(1.0, $count->lines()->find($b)->counted_qty);
    }

    public function test_items_outside_the_sheet_and_skipped_lines_are_never_touched_by_approval(): void
    {
        $counts = app(StockCounts::class);
        $count = $this->start(['scope_type' => 'location', 'scope_value' => 'RAK-A']);   // D1 (RAK-B) tidak ada di lembar
        $this->assertNull($count->lines()->where('batch_id', $this->items['D1']->id)->first());
        $counts->saveLine($count, $this->lineId($count, 'A1'), ['counted_qty' => 8], null, $this->ana);   // selisih -2
        $counts->saveLine($count, $this->lineId($count, 'B1'), ['counted_qty' => 20], null, $this->ana);   // sama
        $counts->saveLine($count, $this->lineId($count, 'C1'), ['skipped' => true, 'note' => 'rak terkunci'], null, $this->ana);   // Tidak dihitung
        $counts->finish($count, $this->ana);
        $counts->reviewLine($count, $this->lineId($count, 'A1'), ['reason' => 'Rusak']);

        $adj = $counts->approve($count, $this->sup);

        $this->assertSame([$this->items['A1']->id], $adj->lines()->pluck('batch_id')->all());   // hanya baris berselisih
        $this->assertSame([8.0, 20.0, 5.0, 8.0], collect(['A1', 'B1', 'C1', 'D1'])->map(fn ($c) => $this->items[$c]->fresh()->stock_qty)->all());   // C1 (Tidak dihitung) dan D1 (di luar lembar) utuh
    }

    public function test_a_done_count_is_permanent_and_a_count_without_difference_is_still_recorded_as_done(): void
    {
        $counts = app(StockCounts::class);
        $count = $this->start();
        foreach (['A1' => 10, 'B1' => 20, 'C1' => 5, 'D1' => 8] as $code => $qty) {
            $counts->saveLine($count, $this->lineId($count, $code), ['counted_qty' => $qty], null, $this->ana);
        }
        $counts->finish($count, $this->ana);
        $this->assertNull($counts->approve($count, $this->sup));   // tanpa selisih: tanpa Penyesuaian, tetap Selesai
        $this->assertSame('done', $count->fresh()->status);

        $line = $this->lineId($count, 'A1');
        $blocked = [
            fn () => $counts->saveLine($count, $line, ['counted_qty' => 1]),
            fn () => $counts->reviewLine($count, $line, ['reason' => 'x']),
            fn () => $counts->reject($count, 'salah hitung'),
            fn () => $counts->cancel($count, $this->sup),
            fn () => $counts->unlock($count, $this->sup),
            fn () => $counts->lock($count, $this->sup),
            fn () => $counts->refreshLine($count, $line),
            fn () => $counts->approve($count, $this->sup),
        ];
        foreach ($blocked as $i => $action) {
            try {
                $action();
                $this->fail("Aksi {$i} seharusnya ditolak pada opname Selesai");
            } catch (HttpException $e) {
                $this->assertSame(422, $e->getStatusCode());
            }
        }
        $this->assertSame('done', $count->fresh()->status);

        $this->assertSame(today()->toDateString(), CountSchedule::lastDone()->toDateString());   // dipakai pengingat jadwal
    }

    public function test_review_and_minutes_show_who_entered_and_warn_when_the_approver_entered_most_rows(): void
    {
        $counts = app(StockCounts::class);
        $count = $this->start();
        foreach (['A1' => 8, 'B1' => 20, 'C1' => 5] as $code => $qty) {
            $counts->saveLine($count, $this->lineId($count, $code), ['counted_qty' => $qty], null, $this->sup);   // Supervisor sendiri menginput tiga dari empat baris
        }
        $counts->saveLine($count, $this->lineId($count, 'D1'), ['counted_qty' => 8], null, $this->ana);   // satu baris oleh Ana
        $counts->finish($count, $this->sup);
        $counts->reviewLine($count, $this->lineId($count, 'A1'), ['reason' => 'Rusak']);

        $this->actingAs($this->sup)->get(route('stock-counts.show', $count))->assertOk()->assertSee('Diinput oleh')->assertSee('Ana Entry')
            ->assertSee('Anda sendiri yang menginput 75 persen baris opname ini')->assertSee('tetap boleh disetujui');
        $this->post(route('stock-counts.approve', $count))->assertRedirect();   // tidak diblokir
        $this->assertSame('done', $count->fresh()->status);

        $this->get(route('stock-counts.report', $count))->assertOk()->assertSee('Diinput oleh (akun)')->assertSee('Sinta Supervisor, Ana Entry', false);
        $this->get(route('stock-counts.show', $count))->assertDontSee('Anda sendiri yang menginput');
    }

    public function test_no_warning_when_a_data_entry_user_did_the_entering(): void
    {
        $counts = app(StockCounts::class);
        $count = $this->start();
        foreach (['A1' => 10, 'B1' => 20, 'C1' => 5, 'D1' => 8] as $code => $qty) {
            $counts->saveLine($count, $this->lineId($count, $code), ['counted_qty' => $qty], null, $this->ana);
        }
        $counts->finish($count, $this->ana);

        $this->actingAs($this->sup)->get(route('stock-counts.show', $count))->assertOk()->assertDontSee('Anda sendiri yang menginput');
    }
}
