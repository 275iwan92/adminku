<?php

namespace Tests\Feature;

use App\Enums\StockDocumentType;
use App\Models\Category;
use App\Models\Batch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\UnitConversion;
use App\Models\User;
use App\Services\StockDocuments;
use App\Support\Decimal;
use App\Support\UnitConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga nilai (biaya, rata-rata bergerak, nilai persediaan, konversi biaya) dihitung presisi dengan bcmath dan dibulatkan sekali pada skala kolom database, bukan dengan float.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class PreciseValueTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private StockDocuments $docs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->supervisor()->create();
        $this->docs = app(StockDocuments::class);
    }

    private function item(string $code = 'P1'): Batch
    {
        return $this->makeBatch(['code' => $code, 'name' => 'Item '.$code, 'category_id' => Category::firstOrCreate(['code' => 'ATK'], ['name' => 'ATK'])->id, 'unit' => 'pcs', 'unit_price' => 1, 'reorder_point' => 0, 'reorder_qty' => 0]);
    }

    private function posted(StockDocumentType $type, Batch $item, float $qty, ?float $cost = null, array $unit = []): void
    {
        $header = match ($type) {
            StockDocumentType::Receiving => ['supplier_id' => Supplier::firstOrCreate(['code' => 'S1'], ['name' => 'S'])->id],
            StockDocumentType::Opening => ['doc_at' => now()->toDateTimeString()],
            default => [],
        };
        $doc = $this->docs->create($type, $header, [['batch_id' => $item->id, 'qty' => $qty, 'unit_cost' => $cost] + $unit], $this->user);
        $this->docs->confirm($doc, $this->user);
    }

    public function test_moving_average_ties_round_half_up_exactly(): void
    {
        // (98,859 x 403,827 + 98,859 x 773,2307) / 197,718 = 588,52885 tepat: float menghasilkan 588,5288.
        $item = $this->item();
        $this->posted(StockDocumentType::Opening, $item, 98.859, 403.827);
        $this->posted(StockDocumentType::Receiving, $item, 98.859, 773.2307);
        $this->assertSame(588.5289, $item->fresh()->avg_cost);

        // (0,045 x 0,0125 + 0,005 x 0,057) / 0,05 = 0,01695 tepat: float menghasilkan 0,0169.
        $small = $this->item('P2');
        $this->posted(StockDocumentType::Opening, $small, 0.045, 0.0125);
        $this->posted(StockDocumentType::Receiving, $small, 0.005, 0.057);
        $this->assertSame(0.017, $small->fresh()->avg_cost);
    }

    public function test_inventory_value_equals_the_sum_of_movement_values_when_averages_are_exact(): void
    {
        $a = $this->item('A1');
        $b = $this->item('B1');
        $this->posted(StockDocumentType::Receiving, $a, 10, 1.5);
        $this->posted(StockDocumentType::Receiving, $a, 10, 2.5);   // rata-rata 2,0
        $this->posted(StockDocumentType::Issuance, $a, 5);
        $this->posted(StockDocumentType::Receiving, $b, 3, 33.3333);
        $this->posted(StockDocumentType::Issuance, $b, 1);

        $inventory = Decimal::total(Batch::all()->map(fn (Batch $i) => [$i->stock_qty, $i->avg_cost]), 4);
        $movements = Decimal::total(StockMovement::all()->map(fn (StockMovement $m) => [$m->qty, $m->unit_cost]), 4);

        $this->assertSame(30.0 + 66.6666, $inventory);
        $this->assertSame($inventory, $movements);
        $this->assertSame(30.0, $a->fresh()->stockValue());
    }

    public function test_one_box_of_three_keeps_its_value_through_unit_conversion_and_back(): void
    {
        UnitConversion::create(['from_unit' => 'box', 'to_unit' => 'pcs', 'factor' => 3]);
        $item = $this->item();

        $this->posted(StockDocumentType::Receiving, $item, 1, 100, ['unit' => 'box']);   // 1 box = 100 -> 3 pcs @ 33,3333

        $this->assertSame([3.0, 33.3333], [$item->fresh()->stock_qty, $item->fresh()->avg_cost]);
        $this->assertSame(100.0, $item->fresh()->stockValue());   // 3 x 33,3333 = 99,9999 tampil 100,00
        $this->assertSame(1.0, UnitConverter::convert(3, 'pcs', 'box'));                       // lewat baris yang sama, tanpa 1/3 yang sudah dibulatkan
    }

    public function test_cost_conversion_through_an_inverse_row_is_exact(): void
    {
        UnitConversion::create(['from_unit' => 'pcs', 'to_unit' => 'box', 'factor' => 0.25]);   // hanya arah pcs -> box: 1 pcs = 0,25 box
        $this->assertSame(16.0, UnitConverter::convert(4, 'box', 'pcs'));
        $this->assertSame(0.625, Decimal::round(UnitConverter::exact('2.5', 'pcs', 'box'), 4));
        $this->assertSame(10.0, Decimal::round(UnitConverter::exact('2.5', 'pcs', 'box', true), 4));
    }

    public function test_decimal_helpers_round_half_away_from_zero_once(): void
    {
        $this->assertSame(1.01, Decimal::round('1.005', 2));
        $this->assertSame(-1.01, Decimal::round('-1.005', 2));
        $this->assertSame(0.3, Decimal::amount(0.1, 3));
        $this->assertSame(0.3, Decimal::total([[0.1, 1], [0.2, 1]]));
        $this->assertSame(0.0, Decimal::total([]));
        $this->assertSame(1235.0, Decimal::total([[1234.5, 1]], 0));
    }
}
