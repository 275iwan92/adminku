<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use App\Models\Vehicle;
use App\Models\VehicleDoc;
use App\Models\VehicleOdometer;
use App\Models\VehicleService;
use App\Models\VehicleType;
use App\Support\Menu;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga tiga form input GA Perawatan Aset Kendaraan: Perpanjang Dokumen (KIR hanya untuk kendaraan wajib KIR, berlaku sampai setelah tanggal bayar, lampiran jpg / png / pdf maks 5 MB, biaya maks 2 desimal),
 *                Catat Servis (pekerjaan dari jadwal kendaraan itu, odometer tidak boleh turun, jadwal berikutnya, stok tidak tersentuh) dan Catat Odometer (pesan jelas, foto opsional); nilai bawaan terisi;
 *                hak Grup per peran: petugas admin GA boleh menulis, GA lain hanya melihat (403 pada form), sopir hanya Catat Odometer; masukan buruk (array) tidak menjadi 500.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class VehicleFormsTest extends TestCase
{
    use RefreshDatabase;

    private Vehicle $truck;

    private Vehicle $car;

    private VehicleType $type;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 08:00:00');
        Storage::fake('ga');
        $this->type = VehicleType::create(['code' => 'PICKUP', 'name' => 'Pickup']);
        $this->type->plans()->createMany([['name' => 'Oli mesin', 'interval_km' => 5000, 'interval_months' => 6, 'sort' => 1], ['name' => 'Aki', 'interval_months' => 24, 'sort' => 2]]);
        $admin = User::factory()->administrator()->create();
        $this->truck = Vehicle::create(['vehicle_type_id' => $this->type->id, 'plate' => 'N 1 TRK', 'name' => 'Pickup', 'kir_required' => true, 'odometer_km' => 14000, 'odometer_at' => '2026-10-20']);
        $this->car = Vehicle::create(['vehicle_type_id' => $this->type->id, 'plate' => 'N 2 CAR', 'name' => 'Mobil', 'kir_required' => false, 'odometer_km' => 5000, 'odometer_at' => '2026-10-20']);
        foreach ([$this->truck, $this->car] as $v) {
            VehicleOdometer::create(['vehicle_id' => $v->id, 'read_at' => '2026-05-01', 'km' => $v->odometer_km - 100, 'source' => 'awal', 'created_by' => $admin->id]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(array $access): User
    {
        $group = UserGroup::create(['code' => 'G'.UserGroup::count(), 'name' => 'GA uji']);
        $group->syncAccess($access);

        return User::factory()->create(['group_id' => $group->id]);
    }

    private function adminGa(): User
    {
        return $this->user(['GVM' => 'ro', 'GVD' => 'rw', 'GVS' => 'rw', 'GVO' => 'rw']);
    }

    private function doc(array $over = []): array
    {
        return $over + ['vehicle_id' => $this->truck->id, 'doc_type' => 'pajak', 'paid_at' => '2026-11-02', 'valid_until' => '2027-11-02', 'cost' => '1250000', 'doc_no' => 'PKB-1', 'note' => ''];
    }

    private function service(array $over = []): array
    {
        return $over + ['vehicle_id' => $this->truck->id, 'serviced_at' => '2026-11-02', 'odometer_km' => '14200', 'jobs' => [$this->type->plans[0]->id], 'workshop' => 'Bengkel Jaya', 'cost' => '350000.5', 'note' => ''];
    }

    public function test_forms_open_with_defaults(): void
    {
        $this->actingAs($this->adminGa());

        $this->get(route('vehicle-docs.create'))->assertOk()->assertSee('Perpanjang Dokumen Kendaraan')->assertSee('value="2026-11-02"', false)->assertSee('value="2027-11-02"', false)->assertSee('N 1 TRK · Pickup')
            ->assertSee('PDF');
        $this->get(route('vehicle-docs.create', ['vehicle' => $this->car->id]))->assertOk();
        $this->get(route('vehicle-services.create', ['vehicle' => $this->truck->id]))->assertOk()->assertSee('Oli mesin')->assertSee('14.000')->assertSee('Catat Servis Kendaraan');
        $this->get(route('vehicle-odometers.create'))->assertOk()->assertSee('Catat Odometer')->assertSee('14.000');
    }

    public function test_renewing_a_document_stores_it_with_the_attachment_and_redirects_to_the_detail(): void
    {
        $this->actingAs($this->adminGa());
        $file = UploadedFile::fake()->create('stnk.pdf', 200, 'application/pdf');

        $this->post(route('vehicle-docs.store'), $this->doc(['attachment' => $file]))->assertRedirect(route('fleet.show', $this->truck))->assertSessionHas('status');

        $doc = VehicleDoc::firstWhere('vehicle_id', $this->truck->id);
        $this->assertSame(['pajak', '2027-11-02', '1250000.00', 'PKB-1'], [$doc->doc_type, $doc->valid_until->toDateString(), $doc->cost, $doc->doc_no]);
        Storage::disk('ga')->assertExists($doc->attachment_path);
        $this->assertStringStartsWith('doc/'.$this->truck->id.'/', $doc->attachment_path);
        $this->get(route('fleet.show', $this->truck))->assertOk()->assertSee('PKB-1')->assertSee('02-11-2027');
    }

    public function test_document_rules(): void
    {
        $this->actingAs($this->adminGa());

        $this->post(route('vehicle-docs.store'), $this->doc(['doc_type' => 'kir', 'vehicle_id' => $this->car->id]))->assertSessionHasErrors(['doc_type' => 'Kendaraan ini tidak wajib KIR.']);
        $this->post(route('vehicle-docs.store'), $this->doc(['doc_type' => 'kir']))->assertSessionHasNoErrors();   // truck wajib KIR
        $this->post(route('vehicle-docs.store'), $this->doc(['valid_until' => '2026-11-02']))->assertSessionHasErrors(['valid_until' => 'Berlaku sampai harus setelah tanggal bayar / uji.']);
        $this->post(route('vehicle-docs.store'), $this->doc(['paid_at' => '2026-11-03']))->assertSessionHasErrors('paid_at');
        $this->post(route('vehicle-docs.store'), $this->doc(['valid_until' => '2099-01-01']))->assertSessionHasErrors('valid_until');
        $this->post(route('vehicle-docs.store'), $this->doc(['doc_type' => 'sim']))->assertSessionHasErrors('doc_type');
        $this->post(route('vehicle-docs.store'), $this->doc(['cost' => '10.005']))->assertSessionHasErrors('cost');
        $this->post(route('vehicle-docs.store'), $this->doc(['cost' => '-1']))->assertSessionHasErrors('cost');
        $this->post(route('vehicle-docs.store'), $this->doc(['vehicle_id' => 999]))->assertSessionHasErrors('vehicle_id');
        $this->assertSame(1, VehicleDoc::count(), 'hanya perpanjangan KIR yang sah tersimpan');

        $this->post(route('vehicle-docs.store'), $this->doc(['cost' => '']))->assertSessionHasNoErrors();
        $this->assertSame('0.00', VehicleDoc::latest('id')->first()->cost, 'biaya kosong = 0');
    }

    public function test_attachment_type_and_size_are_validated(): void
    {
        $this->actingAs($this->adminGa());

        $this->post(route('vehicle-docs.store'), $this->doc(['attachment' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')]))->assertSessionHasErrors(['attachment' => 'Lampiran harus berupa foto JPG / PNG atau berkas PDF.']);
        $this->post(route('vehicle-docs.store'), $this->doc(['attachment' => UploadedFile::fake()->create('besar.pdf', 5121, 'application/pdf')]))->assertSessionHasErrors(['attachment' => 'Lampiran paling besar 5 MB.']);
        $this->post(route('vehicle-docs.store'), $this->doc(['attachment' => UploadedFile::fake()->create('skrip.pdf', 10, 'text/html')]))->assertSessionHasErrors('attachment');   // isinya bukan PDF
        $this->assertSame(0, VehicleDoc::count());
        $this->assertSame([], Storage::disk('ga')->allFiles());

        $this->post(route('vehicle-docs.store'), $this->doc(['attachment' => UploadedFile::fake()->image('foto.jpg')]))->assertSessionHasNoErrors();
        $this->post(route('vehicle-docs.store'), $this->doc(['doc_type' => 'asuransi', 'attachment' => UploadedFile::fake()->create('polis.pdf', 5120, 'application/pdf')]))->assertSessionHasNoErrors();
        $this->assertCount(2, Storage::disk('ga')->allFiles());
    }

    public function test_service_records_jobs_odometer_and_schedule_without_touching_stock(): void
    {
        $this->actingAs($this->adminGa());
        $stock = fn () => [DB::table('rm_stock')->count(), DB::table('rm_stockcard')->count(), DB::table('i_batch')->count()];
        $before = $stock();

        $this->post(route('vehicle-services.store'), $this->service())->assertRedirect(route('fleet.show', $this->truck))->assertSessionHasNoErrors();

        $service = VehicleService::firstWhere('vehicle_id', $this->truck->id);
        $this->assertSame([14200, '350000.50', 'Bengkel Jaya'], [$service->odometer_km, $service->cost, $service->workshop]);
        $this->assertSame(['Oli mesin'], $service->lines->pluck('name')->all());
        $this->assertSame(14200, $this->truck->fresh()->odometer_km);
        $this->assertDatabaseHas('g_vehicle_odometer', ['vehicle_id' => $this->truck->id, 'km' => 14200, 'source' => 'servis']);
        $this->assertSame($before, $stock(), 'servis tidak menyentuh stok gudang');
        $this->get(route('fleet.show', $this->truck))->assertSee('19.200 km / 02-05-2027');   // 14.200 + 5.000 km, 02-11-2026 + 6 bulan
    }

    public function test_service_rules(): void
    {
        $this->actingAs($this->adminGa());
        $other = VehicleType::create(['code' => 'BOKS', 'name' => 'Boks'])->plans()->create(['name' => 'Oli gardan', 'interval_km' => 20000]);

        $this->post(route('vehicle-services.store'), $this->service(['odometer_km' => '13999']))->assertSessionHasErrors(['odometer_km' => 'Odometer 13.999 km lebih kecil dari catatan terakhir 14.000 km (20-10-2026). Periksa angkanya: odometer tidak boleh turun.']);
        $this->post(route('vehicle-services.store'), $this->service(['jobs' => [$other->id]]))->assertSessionHasErrors('jobs');
        $this->post(route('vehicle-services.store'), $this->service(['jobs' => [], 'note' => '']))->assertSessionHasErrors(['note' => 'Centang pekerjaan yang dikerjakan, atau isi catatan bila servisnya di luar jadwal.']);
        $this->post(route('vehicle-services.store'), $this->service(['serviced_at' => '2026-11-03']))->assertSessionHasErrors('serviced_at');
        $this->post(route('vehicle-services.store'), $this->service(['cost' => '1.5.5']))->assertSessionHasErrors('cost');
        $this->assertSame(0, VehicleService::count());
        $this->assertSame(14000, $this->truck->fresh()->odometer_km);

        $this->post(route('vehicle-services.store'), $this->service(['jobs' => [], 'note' => 'Ganti lampu depan', 'odometer_km' => '14000']))->assertSessionHasNoErrors();   // di luar jadwal; odometer sama boleh
        $this->assertSame(1, VehicleService::count());
    }

    public function test_odometer_record_with_photo_and_clear_message_when_lower(): void
    {
        $user = $this->user(['GVO' => 'rw']);
        $this->actingAs($user);

        $this->post(route('vehicle-odometers.store'), ['vehicle_id' => $this->truck->id, 'read_at' => '2026-11-02', 'km' => '13000'])
            ->assertSessionHasErrors(['km' => 'Odometer 13.000 km lebih kecil dari catatan terakhir 14.000 km (20-10-2026). Periksa angkanya: odometer tidak boleh turun.']);

        $this->post(route('vehicle-odometers.store'), ['vehicle_id' => $this->truck->id, 'read_at' => '2026-11-02', 'km' => '14350', 'recorded_by' => '', 'photo' => UploadedFile::fake()->image('odo.png')])
            ->assertRedirect(route('vehicle-odometers.create', ['vehicle' => $this->truck->id]))->assertSessionHas('status');   // sopir tanpa Monitoring: kembali ke form

        $row = VehicleOdometer::where('source', 'manual')->first();
        $this->assertSame([14350, $user->name], [$row->km, $row->recorded_by], 'dicatat oleh bawaan = nama pengguna');
        Storage::disk('ga')->assertExists($row->photo_path);
        $this->assertSame(14350, $this->truck->fresh()->odometer_km);
        $this->assertSame('2026-11-02', $this->truck->fresh()->odometer_at->toDateString());
    }

    public function test_rights_per_role(): void
    {
        $sopir = $this->user(['GVO' => 'rw']);
        $this->actingAs($sopir);
        $this->get(route('vehicle-odometers.create'))->assertOk();
        foreach (['fleet.index', 'vehicle-docs.create', 'vehicle-services.create', 'vehicles.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->post(route('vehicle-docs.store'), $this->doc())->assertForbidden();
        $this->post(route('vehicle-services.store'), $this->service())->assertForbidden();

        $ga = $this->user(['GVM' => 'rw', 'GVD' => 'ro', 'GVS' => 'ro', 'GVO' => 'ro']);   // GA lain: hanya melihat
        $this->actingAs($ga);
        $this->get(route('fleet.show', $this->truck))->assertOk()->assertDontSee('Perpanjang Dokumen')->assertDontSee('Catat Servis');
        foreach (['vehicle-docs.create', 'vehicle-services.create', 'vehicle-odometers.create'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->post(route('vehicle-docs.store'), $this->doc())->assertForbidden();
        $this->post(route('vehicle-odometers.store'), ['vehicle_id' => $this->truck->id, 'read_at' => '2026-11-02', 'km' => 15000])->assertForbidden();

        $admin = $this->adminGa();
        $this->actingAs($admin);
        $this->get(route('fleet.show', $this->truck))->assertOk()->assertSee('Perpanjang Dokumen')->assertSee('Catat Servis')->assertSee('Catat Odometer');
        $this->get(route('fleet.index'))->assertOk()->assertSee('Catat Odometer');
        // tombol aksi berwarna (teal / koral / kuning / slate); bilah form bawaan DevLib tidak memakainya
        $this->get(route('fleet.show', $this->truck))->assertSee('sstock-btn-slate', false)->assertSee('sstock-btn-teal', false)->assertSee('sstock-btn-coral', false)->assertSee('sstock-btn-amber', false);
        $this->get(route('vehicle-docs.create'))->assertOk()->assertDontSee('sstock-btn-', false);
        $this->assertSame(0, VehicleDoc::count() + VehicleService::count());

        $this->actingAs(User::factory()->supervisor()->create());   // tanpa Grup: GA tidak otomatis, walau Supervisor
        $this->get(route('vehicle-docs.create'))->assertForbidden();
    }

    public function test_menu_shows_ga_modules_by_right(): void
    {
        $labels = fn (User $u) => collect(Menu::layers($u))->firstWhere('code', 'GA')['children'] ?? [];

        $this->assertSame(['Catat Odometer'], collect($labels($this->user(['GVO' => 'rw'])))->pluck('label')->all(), 'sopir: hanya Catat Odometer, tanpa layar sementara');
        $this->assertSame([], $labels($this->user(['ITM' => 'rw'])));
        $this->assertSame(['Monitoring Kendaraan', 'Perpanjang Dokumen Kendaraan', 'Catat Servis Kendaraan', 'Catat Odometer', 'Master Kendaraan', 'Jenis Kendaraan', 'Penjadwalan AC', 'Kunjungan AC', 'Denah AC', 'Master AC', 'Area dan Gedung AC', 'Ruang AC', 'Vendor AC', 'Pengaturan GA', 'Perawatan Gedung'],
            collect($labels(User::factory()->administrator()->create()))->pluck('label')->all());
    }

    public function test_bad_input_never_becomes_a_server_error(): void
    {
        $this->actingAs($this->adminGa());
        $junk = ['x' => ['y']];

        foreach (['vehicle-docs.store' => $this->doc(), 'vehicle-services.store' => $this->service(), 'vehicle-odometers.store' => ['vehicle_id' => $this->truck->id, 'read_at' => '2026-11-02', 'km' => 15000]] as $route => $ok) {
            foreach (array_keys($ok) as $field) {
                $res = $this->post(route($route), array_merge($ok, [$field => $junk]));
                $this->assertLessThan(500, $res->status(), "$route $field");
            }
        }
        $this->get(route('vehicle-docs.create', ['vehicle' => $junk]))->assertOk();
        $this->get(route('vehicle-services.create', ['vehicle' => 'abc']))->assertOk();
        $this->from(route('vehicle-docs.create'))->post(route('vehicle-docs.store'), $this->doc(['paid_at' => 'bukan-tanggal', 'doc_type' => $junk, 'vehicle_id' => $junk]))->assertRedirect();
        $this->followingRedirects()->get(route('vehicle-docs.create'))->assertOk();
    }
}
