<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDoc;
use App\Models\VehicleOdometer;
use App\Models\VehicleType;
use App\Services\VehicleCare;
use App\Support\Constants;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga hitungan GA Perawatan Aset Kendaraan: masa berlaku dokumen (interval, berlaku-sampai terbesar per jenis), status Aman / Segera / Terlambat (ambang hari dan km di Constants),
 *                jadwal servis berikutnya (odometer + interval km, tanggal + interval bulan, mana yang lebih dulu; butir yang belum pernah diservis dihitung dari odometer awal), pencatatan servis / odometer / dokumen
 *                (odometer tidak boleh turun, pekerjaan harus dari jadwal kendaraan itu, lampiran terhapus bila gagal), dan jumlah kueri tetap.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class VehicleCareTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private VehicleType $type;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 08:00:00');
        $this->user = User::factory()->administrator()->create();
        $this->type = VehicleType::create(['code' => 'PICKUP', 'name' => 'Pickup']);
        $this->type->plans()->createMany([
            ['name' => 'Oli mesin', 'interval_km' => 5000, 'interval_months' => 6, 'sort' => 1],
            ['name' => 'Aki', 'interval_months' => 24, 'sort' => 2],
            ['name' => 'Ban', 'interval_km' => 40000, 'sort' => 3],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function vehicle(array $over = []): Vehicle
    {
        $v = Vehicle::create($over + ['vehicle_type_id' => $this->type->id, 'plate' => 'N '.random_int(1000, 9999).' AB', 'name' => 'Pickup', 'kir_required' => true, 'odometer_km' => 10000, 'odometer_at' => '2026-10-20']);
        VehicleOdometer::create(['vehicle_id' => $v->id, 'read_at' => '2026-05-01', 'km' => 10000, 'source' => 'awal', 'created_by' => $this->user->id]);

        return $v;
    }

    private function doc(Vehicle $v, string $type, string $paid, ?string $until = null): VehicleDoc
    {
        return VehicleCare::recordDoc($v, ['doc_type' => $type, 'paid_at' => $paid, 'valid_until' => $until ?? VehicleCare::validUntil($paid, $type)->toDateString(), 'cost' => 100000], null, $this->user);
    }

    private function overview(Vehicle $v): array
    {
        return VehicleCare::overview(collect([$v->fresh()]))[$v->id];
    }

    private function item(array $o, string $name): array
    {
        return collect($o['items'])->firstWhere('name', $name);
    }

    public function test_valid_until_adds_the_document_interval_in_calendar_months(): void
    {
        $this->assertSame('2027-11-02', VehicleCare::validUntil('2026-11-02', 'pajak')->toDateString());
        $this->assertSame('2031-11-02', VehicleCare::validUntil('2026-11-02', 'stnk')->toDateString());
        $this->assertSame('2027-05-02', VehicleCare::validUntil('2026-11-02', 'kir')->toDateString());
        $this->assertSame('2027-11-02', VehicleCare::validUntil(Carbon::parse('2026-11-02 23:30:00'), 'asuransi')->toDateString());
        $this->assertSame('2027-02-28', VehicleCare::validUntil('2026-08-31', 'kir')->toDateString(), '31 Agu + 6 bulan tidak meluap ke Maret');
    }

    public function test_current_validity_is_the_largest_valid_until_per_type(): void
    {
        $v = $this->vehicle();
        $this->doc($v, 'pajak', '2025-11-01', '2026-11-01');
        $this->doc($v, 'pajak', '2026-10-30', '2027-10-30');
        $this->doc($v, 'pajak', '2026-01-01', '2026-12-31');   // dimasukkan belakangan tetapi lebih kecil: bukan yang berlaku

        $pajak = $this->overview($v)['docs']['pajak'];
        $this->assertSame('2027-10-30', $pajak['valid_until']->toDateString());
        $this->assertSame(362, $pajak['days']);
        $this->assertSame('aman', $pajak['status']);
        $this->assertSame('30-10-2027 · 362 hari lagi', $pajak['text']);
    }

    public function test_document_status_thresholds_and_optional_documents(): void
    {
        $v = $this->vehicle();
        $cases = ['2026-12-02' => ['segera', 30], '2026-12-03' => ['aman', 31], '2026-11-02' => ['segera', 0], '2026-11-01' => ['terlambat', -1]];
        foreach ($cases as $until => [$status, $days]) {
            VehicleDoc::query()->delete();
            $this->doc($v, 'pajak', '2025-01-01', $until);
            $row = $this->overview($v)['docs']['pajak'];
            $this->assertSame([$status, $days], [$row['status'], $row['days']], $until);
        }
        $this->assertSame('lewat 1 hari', VehicleCare::daysText(-1));
        $this->assertSame('hari ini', VehicleCare::daysText(0));

        // Belum dicatat: pajak / STNK / KIR wajib = Segera; asuransi belum dicatat tidak menandai kendaraan.
        VehicleDoc::query()->delete();
        $o = $this->overview($v);
        $this->assertSame('Belum dicatat', $o['docs']['pajak']['text']);
        $this->assertSame('segera', $o['docs']['pajak']['status']);
        $this->assertNull($o['docs']['asuransi']['status']);

        // KIR tidak wajib: "Tidak wajib" dan tidak ikut status.
        $car = $this->vehicle(['kir_required' => false]);
        $row = $this->overview($car)['docs']['kir'];
        $this->assertSame(['Tidak wajib', null], [$row['text'], $row['status']]);
    }

    public function test_thresholds_come_from_constants(): void
    {
        $v = $this->vehicle();
        $this->doc($v, 'pajak', '2025-01-01', '2026-12-20');   // 48 hari lagi
        $this->assertSame('aman', $this->overview($v)['docs']['pajak']['status']);

        Constants::set('ga.vehicle.soon_days', 60, 'ga');
        $this->assertSame('segera', $this->overview($v)['docs']['pajak']['status']);
        $this->assertSame(60, VehicleCare::soonDays());
        $this->assertSame(1000, VehicleCare::soonKm());
    }

    public function test_never_serviced_items_count_from_the_starting_odometer(): void
    {
        $v = $this->vehicle(['odometer_km' => 12000]);   // awal 10.000 km pada 01-05-2026
        $o = $this->overview($v);

        $oli = $this->item($o, 'Oli mesin');
        $this->assertSame(15000, $oli['next_km']);
        $this->assertSame('2026-11-01', $oli['next_at']->toDateString(), '01-05-2026 + 6 bulan');
        $this->assertSame(3000, $oli['km_left']);
        $this->assertSame(-1, $oli['days_left']);
        $this->assertSame('terlambat', $oli['status'], 'tanggal sudah lewat walau km masih jauh: yang lebih dulu menentukan');
        $this->assertFalse($oli['done']);

        $ban = $this->item($o, 'Ban');
        $this->assertSame(50000, $ban['next_km']);
        $this->assertNull($ban['next_at']);
        $this->assertSame('aman', $ban['status']);
        $this->assertSame('terlambat', $o['status']);
        $this->assertSame('Oli mesin', $o['next']['name']);
    }

    public function test_service_sets_the_next_schedule_from_the_service_odometer_and_date(): void
    {
        $v = $this->vehicle(['odometer_km' => 13900]);
        $oli = $this->type->plans()->where('name', 'Oli mesin')->first();
        $aki = $this->type->plans()->where('name', 'Aki')->first();
        VehicleCare::recordService($v, ['serviced_at' => '2026-10-25', 'odometer_km' => 14000, 'workshop' => 'Bengkel Jaya', 'cost' => 350000, 'jobs' => [$oli->id, $aki->id]], $this->user);

        $o = $this->overview($v);
        $this->assertSame(14000, $v->fresh()->odometer_km, 'odometer servis menaikkan odometer kendaraan');
        $item = $this->item($o, 'Oli mesin');
        $this->assertSame([19000, '2027-04-25', 5000, 174], [$item['next_km'], $item['next_at']->toDateString(), $item['km_left'], $item['days_left']]);
        $this->assertSame('aman', $item['status']);
        $this->assertTrue($item['done']);
        $this->assertSame('Aki', $this->item($o, 'Aki')['name']);
        $this->assertSame('2028-10-25', $this->item($o, 'Aki')['next_at']->toDateString());
        $this->assertSame(50000, $this->item($o, 'Ban')['next_km'], 'butir yang tidak dikerjakan tetap dihitung dari odometer awal');

        // 4.500 km kemudian: sisa 500 km <= ambang 1.000 = Segera; lewat 19.000 = Terlambat.
        VehicleCare::recordOdometer($v, ['read_at' => '2026-11-01', 'km' => 18500], null, $this->user);
        $this->assertSame('segera', $this->item($this->overview($v), 'Oli mesin')['status']);
        $this->assertSame('500 km lagi', VehicleCare::kmText(500));
        VehicleCare::recordOdometer($v, ['read_at' => '2026-11-02', 'km' => 19001], null, $this->user);
        $late = $this->item($this->overview($v), 'Oli mesin');
        $this->assertSame('terlambat', $late['status']);
        $this->assertSame('lewat 1 km', VehicleCare::kmText($late['km_left']));
    }

    public function test_progress_follows_whichever_limit_is_closer(): void
    {
        $v = $this->vehicle(['odometer_km' => 12500]);
        $oli = $this->type->plans()->where('name', 'Oli mesin')->first();
        VehicleCare::recordService($v, ['serviced_at' => '2026-10-02', 'odometer_km' => 12500, 'cost' => 0, 'jobs' => [$oli->id]], $this->user);
        VehicleCare::recordOdometer($v, ['read_at' => '2026-11-02', 'km' => 14500], null, $this->user);   // 2.000 dari 5.000 km = 40%; 31 dari 182 hari = 17%

        $item = $this->item($this->overview($v), 'Oli mesin');
        $this->assertSame(40, $item['progress']);
        $this->assertStringContainsString('km lagi', $item['text']);
    }

    public function test_odometer_cannot_go_down_and_equal_is_allowed(): void
    {
        $v = $this->vehicle();

        try {
            VehicleCare::recordOdometer($v, ['read_at' => '2026-11-02', 'km' => 9000], null, $this->user);
            $this->fail('odometer turun harus ditolak');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('9.000 km lebih kecil dari catatan terakhir 10.000 km (20-10-2026)', $e->errors()['km'][0]);
        }
        $this->assertSame(1, VehicleOdometer::where('vehicle_id', $v->id)->count());

        VehicleCare::recordOdometer($v, ['read_at' => '2026-11-02', 'km' => 10000], null, $this->user);
        $this->assertSame('2026-11-02', $v->fresh()->odometer_at->toDateString());
        $this->expectException(ValidationException::class);
        VehicleCare::recordService($v, ['serviced_at' => '2026-11-02', 'odometer_km' => 9999, 'cost' => 0, 'jobs' => []], $this->user);
    }

    public function test_service_jobs_must_belong_to_the_vehicle_type_and_failure_changes_nothing(): void
    {
        $v = $this->vehicle();
        $other = VehicleType::create(['code' => 'BOKS', 'name' => 'Boks'])->plans()->create(['name' => 'Oli gardan', 'interval_km' => 20000]);

        try {
            VehicleCare::recordService($v, ['serviced_at' => '2026-11-02', 'odometer_km' => 10500, 'cost' => 1, 'jobs' => [$other->id]], $this->user);
            $this->fail('pekerjaan dari jenis lain harus ditolak');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('jobs', $e->errors());
        }
        $this->assertSame(0, DB::table('g_vehicle_service')->count());
        $this->assertSame(10000, $v->fresh()->odometer_km, 'transaksi dibatalkan utuh');
    }

    public function test_documents_validate_kir_requirement_dates_and_keep_cost_precision(): void
    {
        Storage::fake('ga');
        $car = $this->vehicle(['kir_required' => false]);

        try {
            $this->doc($car, 'kir', '2026-11-02');
            $this->fail('KIR untuk kendaraan tidak wajib KIR harus ditolak');
        } catch (ValidationException $e) {
            $this->assertSame('Kendaraan ini tidak wajib KIR.', $e->errors()['doc_type'][0]);
        }
        try {
            $this->doc($car, 'pajak', '2026-11-02', '2026-11-02');
            $this->fail('berlaku sampai harus setelah tanggal bayar');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('valid_until', $e->errors());
        }

        $doc = VehicleCare::recordDoc($car, ['doc_type' => 'pajak', 'paid_at' => '2026-11-02', 'valid_until' => '2027-11-02', 'cost' => '1234567.895', 'doc_no' => 'PKB-1'], UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'), $this->user);
        $this->assertSame('1234567.90', $doc->fresh()->cost);
        Storage::disk('ga')->assertExists($doc->attachment_path);
        $this->assertSame('bukti.pdf', $doc->attachment_name);
    }

    public function test_year_cost_sums_with_decimal_precision(): void
    {
        $v = $this->vehicle();
        foreach ([['2026-02-01', '100000.10'], ['2026-06-01', '200000.20'], ['2025-12-31', '999999.99']] as $i => [$at, $cost]) {
            VehicleCare::recordService($v, ['serviced_at' => $at, 'odometer_km' => 10000 + $i, 'cost' => $cost, 'jobs' => []], $this->user);
        }

        $this->assertSame(300000.30, VehicleCare::yearCost(2026));
        $this->assertSame('IDR 300.000,30', VehicleCare::rp(VehicleCare::yearCost(2026)));
        $this->assertSame('IDR 1.250.000', VehicleCare::rp('1250000'));
    }

    public function test_overview_uses_a_fixed_number_of_queries(): void
    {
        $count = function (int $n) {
            foreach (['g_vehicle_doc', 'g_vehicle_odometer'] as $table) {
                DB::table($table)->delete();
            }
            Vehicle::query()->delete();
            for ($i = 0; $i < $n; $i++) {
                $v = $this->vehicle();
                $this->doc($v, 'pajak', '2026-10-01');
            }
            $vehicles = Vehicle::query()->with('type')->get();   // type dimuat pemanggil; rencana dimuat overview
            DB::flushQueryLog();
            DB::enableQueryLog();
            VehicleCare::overview($vehicles);

            return count(DB::getQueryLog());
        };
        VehicleCare::soonDays();   // pemanasan: pengaturan dibaca sekali per permintaan

        $this->assertSame($count(2), $count(12));
    }
}
