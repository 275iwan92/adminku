<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\User;
use App\Services\StockCounts;
use Devlib2\Locale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga angka Indonesia di hitung opname HP: pembaca angka cadangan resources/js/hp-number.js SAMA PERSIS dengan Devlib2\Locale::parse (dan numStr DevLib di JS), jadi "1.250" = 1250 di HP seperti di desktop,
 *                "0.500" = 0,5 (kanonik tetap "0.500"), "1.234,5" = 1234,5; dan server menerima nilai kanonik hasilnya (1250, 0.5, 1234.5) serta menolak yang lebih dari 3 desimal.
 * Dipakai oleh : php artisan test. Bagian JS dilewati bila Node tidak ada.
 * Status       : Aktif.
 */
class HpNumberRulesTest extends TestCase
{
    use RefreshDatabase;

    private const CASES = ['1250' => '1250', '0.500' => '0.500', '1.250' => '1250', '10.500' => '10500', '1,5' => '1.5', '1.234,5' => '1234.5', '1,234.5' => '1234.5', '12,' => '12', '0.125' => '0.125',
        'Rp 1.000' => '1000', '2.5' => '2.5', '1.2.3' => '', '1,2,3' => '', 'abc' => '', '' => '', '0' => '0', '007' => '7'];

    public function test_the_phone_number_reader_gives_the_same_canonical_value_as_the_php_rules(): void
    {
        foreach (self::CASES as $typed => $canonical) {
            $this->assertSame($canonical === '' ? null : $canonical, Locale::parse($typed, 'id'), "PHP: {$typed}");
        }
    }

    public function test_the_phone_js_reader_matches_the_same_cases(): void
    {
        $node = trim((string) @shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where node 2>NUL' : 'command -v node'));
        if ($node === '') {
            $this->markTestSkipped('Node tidak ada.');
        }
        $module = 'file:///'.str_replace('\\', '/', base_path('resources/js/hp-number.js'));
        $script = "import {localNumStr, canon, validQty} from '{$module}'; const c = ".json_encode(array_keys(self::CASES), JSON_UNESCAPED_UNICODE)."; console.log(JSON.stringify({n: c.map(localNumStr), v: ['', '1,5', '1.250', '-5', 'abc', '1,2345', '1,234'].map(validQty), k: ['', ' ', '1.250'].map(canon)}));";
        $result = Process::run([strtok($node, "\r\n"), '--input-type=module', '-e', $script]);
        $this->assertTrue($result->successful(), $result->errorOutput());
        $out = json_decode($result->output(), true);

        $this->assertSame(array_values(self::CASES), $out['n']);
        $this->assertSame([true, true, true, false, false, false, true], $out['v']);   // kosong sah; negatif, teks, dan lebih dari 3 desimal tidak
        $this->assertSame(['', '', '1250'], $out['k']);
    }

    public function test_the_server_accepts_canonical_counts_from_the_phone_and_rejects_too_many_decimals(): void
    {
        $sup = User::factory()->supervisor()->create();
        $batch = $this->makeBatch(['code' => 'ATK-0001', 'name' => 'Pulpen', 'category_id' => Category::create(['code' => 'ATK', 'name' => 'ATK'])->id, 'unit' => 'pcs', 'unit_price' => 1]);
        $batch->forceFill(['stock_qty' => 5])->save();
        $count = app(StockCounts::class)->start($sup, ['count_date' => today()->toDateString(), 'scope_type' => 'all', 'blind' => true, 'lock_transactions' => false, 'threshold_pct' => 5]);
        $id = $count->lines()->where('batch_id', $batch->id)->value('id');
        $this->actingAs($sup);

        foreach (['1250' => 1250.0, '0.500' => 0.5, '1234.5' => 1234.5] as $sent => $stored) {
            $this->postJson(route('stock-counts.lines', $count), ['changed' => [['id' => $id, 'counted_qty' => $sent]], 'compact' => 1])->assertOk();
            $this->assertSame($stored, $count->lines()->find($id)->counted_qty, "kiriman {$sent}");
        }
        $this->postJson(route('stock-counts.lines', $count), ['changed' => [['id' => $id, 'counted_qty' => '1.2345']]])->assertStatus(422);
        $this->postJson(route('stock-counts.lines', $count), ['changed' => [['id' => $id, 'counted_qty' => '1,5']]])->assertStatus(422);   // koma bukan kanonik: HP selalu mengirim titik
        $this->assertInstanceOf(Batch::class, $batch);
    }
}
