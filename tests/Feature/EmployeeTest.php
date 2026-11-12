<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Models\UserGroup;
use Carbon\Carbon;
use Database\Seeders\EmployeeSampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga Karyawan (HR, HRE): simpan dengan keluarga / dokumen / kontrak, kode otomatis, NIK 16 angka dan unik, Nonaktifkan wajib alasan + tanggal (akun Jays ikut nonaktif, akun sendiri tidak),
 *                Aktifkan kembali kecuali Meninggal, catatan Perhatian (kontrak 60 hari, dokumen 30 hari, data belum lengkap), hak modul (Grup RO / RW), dan Bagian = Departemen dengan jumlah karyawan.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Department $dept;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 08:00:00');
        $this->admin = User::factory()->administrator()->create();
        $this->dept = Department::create(['code' => 'GA', 'name' => 'General Affair']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function payload(array $over = []): array
    {
        return $over + ['code' => 'E-0001', 'name' => 'Budi Santoso', 'gender' => 'L', 'department_id' => $this->dept->id, 'employment' => 'tetap', 'join_date' => '2021-02-03', 'work_status' => 'working'];
    }

    private function employee(array $over = []): Employee
    {
        return Employee::create($over + ['code' => 'E-'.random_int(1000, 9999), 'name' => 'Budi', 'gender' => 'L', 'department_id' => $this->dept->id, 'employment' => 'tetap', 'join_date' => '2021-02-03', 'nik_ktp' => '3273010101010001', 'phone' => '0812', 'ktp_area_id' => 1, 'ktp_area_level' => 'CITY']);
    }

    public function test_recipient_becomes_employee_and_gets_linked(): void
    {
        $r = \App\Models\Recipient::create(['name' => 'Agus', 'department_id' => $this->dept->id]);

        $this->actingAs($this->admin)->get(route('recipients.show', $r))->assertOk()->assertSee('Jika penerima ini karyawan');
        $this->get(route('employees.create', ['penerima' => $r->id]))->assertOk()->assertSee('value="Agus"', false)->assertSee('name="penerima" value="'.$r->id.'"', false);

        $this->post(route('employees.store'), $this->payload(['name' => 'Agus', 'penerima' => $r->id]))->assertRedirect();
        $emp = Employee::where('name', 'Agus')->firstOrFail();
        $this->assertSame($emp->id, $r->fresh()->employee_id);

        $this->get(route('recipients.show', $r))->assertOk()->assertDontSee('Jika penerima ini karyawan')->assertSee($emp->code);
        $this->get(route('employees.create', ['penerima' => $r->id]))->assertOk()->assertDontSee('name="penerima"', false);   // sudah terhubung: tidak ditawarkan lagi
    }

    public function test_saves_an_employee_with_family_documents_and_contracts(): void
    {
        $this->actingAs($this->admin)->post(route('employees.store'), $this->payload([
            'nik_ktp' => '3273-0101-0101-0001',
            'family' => [['relation' => 'anak', 'name' => 'Ani', 'birth_date' => '2015-05-05']],
            'docs' => [['doc_type' => 'sim', 'doc_no' => 'SIM-1', 'expiry_date' => '2027-01-01']],
            'contracts' => [['employment' => 'kontrak', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']],
        ]))->assertRedirect();

        $e = Employee::firstWhere('code', 'E-0001');
        $this->assertSame('3273010101010001', $e->nik_ktp);
        $this->assertSame(1, $e->family()->count());
        $this->assertSame('SIM-1', $e->docs()->first()->doc_no);
        $this->assertSame(1, $e->contracts()->count());

        $this->actingAs($this->admin)->put(route('employees.update', $e), $this->payload(['code' => 'IGNORED', 'name' => 'Budi S', 'docs' => []]))->assertRedirect();
        $e->refresh();
        $this->assertSame('E-0001', $e->code);   // kode tetap
        $this->assertSame('Budi S', $e->name);
        $this->assertSame(0, $e->docs()->count());
        $this->assertSame(0, $e->family()->count());   // daftar kosong = baris diganti kosong
        $this->actingAs($this->admin)->get(route('employees.show', $e))->assertOk();
        $this->actingAs($this->admin)->get(route('employees.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('employees.create'))->assertOk()->assertSee('E-0002');
    }

    public function test_photo_and_ktp_scan_are_private_files_that_can_be_replaced(): void
    {
        Storage::fake('ga');
        $this->actingAs($this->admin)->post(route('employees.store'), $this->payload(['photo' => UploadedFile::fake()->image('foto.jpg'), 'ktp_file' => UploadedFile::fake()->image('ktp.png')]))->assertRedirect();
        $e = Employee::firstOrFail();
        Storage::disk('ga')->assertExists([$e->photo_path, $e->ktp_path]);
        $this->get(route('employees.file', [$e, 'photo']))->assertOk();
        $this->get(route('employees.show', $e))->assertOk()->assertSee('foto.jpg');

        $old = $e->photo_path;
        $this->put(route('employees.update', $e), $this->payload(['photo' => UploadedFile::fake()->image('baru.png')]))->assertRedirect();
        $e->refresh();
        $this->assertSame('baru.png', $e->photo_name);
        Storage::disk('ga')->assertMissing($old);   // berkas lama diganti

        $this->put(route('employees.update', $e), $this->payload(['photo' => UploadedFile::fake()->create('x.exe', 10)]))->assertSessionHasErrors('photo');
        $this->put(route('employees.update', $e), $this->payload(['ktp_file' => UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf')]))->assertSessionHasErrors('ktp_file');
        $this->actingAs(User::factory()->supervisor()->create())->get(route('employees.file', [$e, 'ktp']))->assertForbidden();   // tanpa hak HRE
    }

    public function test_sample_seeder_makes_two_complete_employees_once(): void
    {
        Storage::fake('ga');
        $this->seed(EmployeeSampleSeeder::class);
        $this->seed(EmployeeSampleSeeder::class);

        $people = Employee::where('code', 'like', 'E-C%')->with(['family', 'docs', 'contracts'])->orderBy('code')->get();
        $this->assertSame(['L', 'P'], $people->pluck('gender')->all());
        foreach ($people as $p) {
            $this->assertSame(16, strlen($p->nik_ktp));
            $this->assertGreaterThan(0, $p->family->count() * $p->docs->count() * $p->contracts->count());
            Storage::disk('ga')->assertExists([$p->photo_path, $p->ktp_path]);
            $this->assertStringContainsString('DUMMY', Storage::disk('ga')->get($p->ktp_path));
        }
        $this->assertSame(0, User::where('username', 'like', '%contoh%')->count());   // tidak membuat akun
        $this->assertSame(['Staf gudang', 'Admin HR'], $people->pluck('position')->all());   // jabatan tanpa penanda (Contoh)
        $this->assertSame('HR', $people[1]->department->name);   // Admin HR di bagian HR (dibuat bila belum ada)
        $this->assertSame(1, Department::where('code', 'HR')->count());
    }

    public function test_cropped_ktp_replaces_the_plain_upload_and_a_pdf_is_kept_as_is(): void
    {
        Storage::fake('ga');
        $this->actingAs($this->admin)->post(route('employees.store'), $this->payload(['photo' => UploadedFile::fake()->image('profile.jpg', 600, 800), 'ktp_crop' => UploadedFile::fake()->image('profile.jpg', 856, 540)]))->assertRedirect();
        $e = Employee::firstOrFail();
        $this->assertSame('profile.jpg', $e->ktp_name);   // hasil crop dari kotak foto DevLib
        $this->get(route('employees.show', $e))->assertOk()->assertSee('&quot;outW&quot;:856', false)->assertSee('data-dl2="photo-crop"', false)->assertSee('data-lightbox', false);   // KTP 856 x 540 dan foto profil memakai photo-crop DevLib 2

        $this->put(route('employees.update', $e), $this->payload(['ktp_file' => UploadedFile::fake()->create('ktp.pdf', 100, 'application/pdf')]))->assertRedirect();
        $this->assertSame('ktp.pdf', $e->refresh()->ktp_name);   // PDF tanpa crop
        $this->get(route('employees.show', $e))->assertOk()->assertSee('Buka PDF');

        $both = ['ktp_file' => UploadedFile::fake()->create('lama.pdf', 50, 'application/pdf'), 'ktp_crop' => UploadedFile::fake()->image('profile.jpg', 856, 540)];
        $this->put(route('employees.update', $e), $this->payload($both))->assertRedirect();
        $this->assertSame('profile.jpg', $e->refresh()->ktp_name);   // crop menang bila keduanya dikirim
        $this->put(route('employees.update', $e), $this->payload(['ktp_crop' => UploadedFile::fake()->image('x.png')]))->assertSessionHasErrors('ktp_crop');
    }

    public function test_index_shows_cards_with_photo_tenure_and_counts_and_a_list_view(): void
    {
        Storage::fake('ga');
        $this->seed(EmployeeSampleSeeder::class);
        $this->employee(['name' => 'Rani Keluar', 'is_active' => false, 'exit_reason' => 'resigned', 'exit_date' => '2026-06-30', 'join_date' => '2024-01-15']);

        $page = $this->actingAs($this->admin)->get(route('employees.index'))->assertOk();
        $this->assertSame('cards', $page->viewData('view'));
        $this->assertSame(['all' => 3, 'attn' => 2, 'exited' => 1], $page->viewData('counts'));
        $rows = collect($page->viewData('rows'))->keyBy('code');
        $this->assertSame(route('employees.file', [Employee::firstWhere('code', 'E-C001'), 'photo']), $rows['E-C001']['photo']);
        $this->assertSame('5 th 8 bln', $rows['E-C001']['tenure']);   // masuk 03-02-2021, hari ini (uji) 02-11-2026
        $this->assertSame('2 th 5 bln', collect($rows)->firstWhere('name', 'Rani Keluar')['tenure']);   // dihitung sampai tanggal keluar
        $page->assertSee('<a class="em-card', false)->assertSee('Perlu perhatian')->assertSee('David')->assertSee('em-photo', false);

        $list = $this->get(route('employees.index', ['view' => 'list']))->assertOk();
        $this->assertSame('list', $list->viewData('view'));
        $list->assertDontSee('<a class="em-card', false);   // tanpa kartu di tampilan Daftar
    }

    public function test_rerunning_the_sample_seeder_keeps_data_that_was_replaced(): void
    {
        Storage::fake('ga');
        $this->seed(EmployeeSampleSeeder::class);
        $e = Employee::firstWhere('code', 'E-C002');
        Storage::disk('ga')->put("employee/{$e->id}/profile.jpg", 'jpeg');
        $e->update(['name' => 'Detta', 'birth_date' => '1988-09-09', 'photo_path' => "employee/{$e->id}/profile.jpg", 'photo_name' => 'profile.jpg']);

        $this->seed(EmployeeSampleSeeder::class);
        $e->refresh();
        $this->assertSame(['Detta', '1988-09-09', 'profile.jpg'], [$e->name, $e->birth_date->toDateString(), $e->photo_name]);
        Storage::disk('ga')->assertExists($e->photo_path);
    }

    public function test_nik_must_be_sixteen_digits_and_unique(): void
    {
        $this->employee(['code' => 'E-0001']);
        $this->actingAs($this->admin)->post(route('employees.store'), $this->payload(['code' => 'E-0002', 'nik_ktp' => '123']))->assertSessionHasErrors('nik_ktp');
        $this->post(route('employees.store'), $this->payload(['code' => 'E-0002', 'nik_ktp' => '3273010101010001']))->assertSessionHasErrors('nik_ktp');
        $this->post(route('employees.store'), $this->payload(['code' => 'E-0002', 'family' => [['relation' => 'anak', 'name' => 'Ani', 'nik' => '12']]]))->assertSessionHasErrors('family.0.nik');
    }

    public function test_deactivate_needs_a_reason_and_date_and_switches_off_the_login(): void
    {
        $login = User::factory()->create();
        $e = $this->employee(['user_id' => $login->id]);

        $this->actingAs($this->admin)->put(route('employees.update', $e), ['_action' => 'deactivate'])->assertSessionHasErrors(['exit_reason', 'exit_date']);
        $this->assertTrue($e->refresh()->is_active);

        $this->put(route('employees.update', $e), ['_action' => 'deactivate', 'exit_reason' => 'resigned', 'exit_date' => '2026-11-01'])->assertRedirect();
        $e->refresh();
        $this->assertFalse($e->is_active);
        $this->assertSame('resigned', $e->exit_reason);
        $this->assertFalse($login->refresh()->is_active);
        $this->get(route('employees.show', $e))->assertOk()->assertSee('Mengundurkan diri');

        $this->put(route('employees.update', $e), ['_action' => 'activate'])->assertRedirect();
        $this->assertTrue($e->refresh()->is_active);
        $this->assertNull($e->exit_reason);
    }

    public function test_own_login_stays_active_and_deceased_cannot_return(): void
    {
        $own = $this->employee(['user_id' => $this->admin->id]);
        $this->actingAs($this->admin)->put(route('employees.update', $own), ['_action' => 'deactivate', 'exit_reason' => 'transferred', 'exit_date' => '2026-11-01'])->assertRedirect();
        $this->assertTrue($this->admin->refresh()->is_active);

        $dead = $this->employee();
        $this->put(route('employees.update', $dead), ['_action' => 'deactivate', 'exit_reason' => 'deceased', 'exit_date' => '2026-11-01']);
        $this->put(route('employees.update', $dead), ['_action' => 'activate'])->assertSessionHasErrors('exit_reason');
        $this->assertFalse($dead->refresh()->is_active);
    }

    public function test_attention_shows_the_worst_note_only_for_active_employees(): void
    {
        $ok = $this->employee()->fresh(['contracts', 'docs']);   // fresh: nilai bawaan kolom (is_active) ikut terbaca
        $this->assertNull($ok->attention());

        $e = $this->employee()->fresh();
        $e->contracts()->create(['employment' => 'kontrak', 'start_date' => '2026-01-01', 'end_date' => '2026-12-15']);   // 43 hari lagi
        $e->docs()->create(['doc_type' => 'sim', 'expiry_date' => '2026-11-14']);   // 12 hari lagi
        $this->assertSame('SIM habis 12 hari', $e->load(['contracts', 'docs'])->attention());

        $e->docs()->first()->update(['expiry_date' => '2026-10-30']);
        $this->assertSame('SIM lewat 3 hari', $e->load('docs')->attention());

        $e->docs()->delete();
        $this->assertSame('Kontrak habis 43 hari', $e->load('docs')->attention());

        $bare = $this->employee(['nik_ktp' => null])->fresh(['contracts', 'docs']);
        $this->assertSame('Data belum lengkap', $bare->attention());

        $e->update(['is_active' => false]);
        $this->assertNull($e->attention());
    }

    public function test_access_follows_the_group_and_hr_is_never_a_role_default(): void
    {
        $this->assertNotContains('HRE', \App\Support\Modules::defaults('supervisor'));
        $this->actingAs(User::factory()->supervisor()->create())->get(route('employees.index'))->assertForbidden();

        $group = UserGroup::create(['code' => 'HR', 'name' => 'HR']);
        $group->syncAccess(['HRE' => 'ro']);
        $reader = User::factory()->supervisor()->create(['group_id' => $group->id]);
        $this->actingAs($reader)->get(route('employees.index'))->assertOk();
        $this->post(route('employees.store'), $this->payload())->assertForbidden();   // RO menolak tulis

        $group->syncAccess(['HRE' => 'rw']);
        $this->actingAs($reader->fresh());   // hak dibaca ulang dari Grup
        $this->post(route('employees.store'), $this->payload())->assertRedirect();
    }

    public function test_bagian_is_the_department_screen_with_an_employee_count(): void
    {
        $this->employee();
        $this->employee();
        $page = $this->actingAs($this->admin)->get(route('departments.index'))->assertOk()->viewData('rows');
        $this->assertSame(2, $page[0]['employees']);
        $this->get(route('departments.show', $this->dept))->assertOk();
        $this->get(route('opening', 'hr'))->assertOk()->viewData('page');
        $tiles = collect($this->get(route('opening', 'hr'))->viewData('page')['tiles']);
        $this->assertSame('2', $tiles->firstWhere('code', 'HRE')['n']);
        $this->assertSame('1', $tiles->firstWhere('code', 'HR-DEP')['n']);
    }
}
