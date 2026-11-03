<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Department;
use App\Models\Discipline;
use App\Models\Employee;
use App\Models\RecruitStep;
use App\Models\User;
use App\Services\SampleHr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga data contoh HR kanonik (snapshot database/sample/hr): apply membuat dua karyawan dan dua kandidat lengkap dan idempoten, reset mengembalikan yang diubah dan membuang yang bertanda contoh tambahan tanpa menyentuh data nyata,
 *                perintah sample:hr dan aksi Reset data contoh HR di layar Data Contoh (konfirmasi RESET).
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class SampleHrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('ga');
    }

    public function test_apply_creates_the_canonical_set_once_without_overwriting(): void
    {
        $log = SampleHr::apply();
        $this->assertCount(5, $log);   // dua karyawan, tiga kandidat

        $david = Employee::firstWhere('code', 'E-C001');
        $detta = Employee::firstWhere('code', 'E-C002');
        $this->assertSame(['David', 'Maintenance', 'Staf gudang'], [$david->name, $david->department->name, $david->position]);
        $this->assertSame(['Detta', 'HR', 'Admin HR'], [$detta->name, $detta->department->name, $detta->position]);
        $this->assertGreaterThan(0, $david->family()->count() * $david->docs()->count() * $david->contracts()->count());
        Storage::disk('ga')->assertExists([$david->photo_path, $david->ktp_path, $detta->photo_path]);
        $this->assertStringContainsString('DUMMY', Storage::disk('ga')->get($detta->ktp_path));

        $maya = Candidate::firstWhere('name', 'Maya Denada');
        $dedi = Candidate::firstWhere('name', 'Dedi Sandiaga');
        $lucy = Candidate::firstWhere('name', 'Lucy Sempi');
        $this->assertSame('2002-11-22', $lucy->birth_date->toDateString());
        $this->assertSame(['Admin', 'Maintenance'], $lucy->departments()->pluck('name')->all());
        Storage::disk('ga')->assertExists([$lucy->photo_path, $lucy->ktp_path]);
        $this->assertSame(3, $maya->events()->count());
        $this->assertSame('Tes', RecruitStep::find($maya->step_id)->name);
        $this->assertSame(['Maintenance'], $dedi->departments()->pluck('name')->all());
        $this->assertTrue($maya->events()->reorder('id', 'desc')->first()->scheduled_on->isFuture());   // jadwal relatif hari ini
        Storage::disk('ga')->assertExists([$maya->photo_path, $dedi->ktp_path]);

        $david->update(['name' => 'Diubah']);
        $this->assertStringContainsString('dilewati', implode(' ', SampleHr::apply()));
        $this->assertSame('Diubah', $david->refresh()->name);   // tanpa reset tidak menimpa
    }

    public function test_every_person_in_the_canonical_set_has_their_own_files_matching_the_manifest(): void
    {
        SampleHr::apply();
        SampleHr::apply(true);   // reset berulang tidak mengacak berkas

        $dir = base_path(SampleHr::DIR);
        $m = SampleHr::manifest();
        $people = [];
        foreach ($m['employees'] as $r) {
            $people[] = [Employee::firstWhere('code', $r['code']), $r];
        }
        foreach ($m['candidates'] as $r) {
            $people[] = [Candidate::firstWhere('nik', $r['nik']), $r];
        }
        $this->assertCount(5, $people);

        $paths = $hashes = [];
        foreach ($people as [$model, $r]) {
            $this->assertNotNull($model, $r['name']);
            foreach (['photo' => 'photo_path', 'ktp' => 'ktp_path'] as $key => $col) {
                $stored = Storage::disk('ga')->get($model->{$col});
                $this->assertSame(md5_file($dir.'/'.$r[$key]), md5($stored), "{$r['name']}: berkas {$key} harus sama dengan snapshot");
                $paths[] = $model->{$col};
                $hashes[] = md5($stored);
            }
            $this->assertStringContainsString($r['name'], Storage::disk('ga')->get($model->ktp_path), 'KTP bernama pemiliknya sendiri');
        }
        $this->assertSame(10, count(array_unique($paths)), 'jalur berkas tiap orang unik');
        $this->assertSame(10, count(array_unique($hashes)), 'isi berkas tiap orang berbeda');
        $this->assertSame(10, count(array_unique(array_map(fn ($f) => md5_file($dir.'/'.$f), array_merge(array_column($m['employees'], 'photo'), array_column($m['employees'], 'ktp'), array_column($m['candidates'], 'photo'), array_column($m['candidates'], 'ktp'))))), 'berkas snapshot unik');
    }

    public function test_reset_restores_changes_and_removes_only_extra_marked_samples(): void
    {
        SampleHr::apply();
        $dept = Department::first();
        $david = Employee::firstWhere('code', 'E-C001');
        $david->update(['name' => 'Diubah', 'position' => 'X']);
        $david->docs()->delete();
        Candidate::firstWhere('name', 'Dedi Sandiaga')->update(['name' => 'Salah']);

        $extra = Employee::create(['code' => 'E-C099', 'name' => 'Tambahan Contoh', 'gender' => 'L', 'department_id' => $dept->id, 'employment' => 'tetap', 'join_date' => '2026-01-01']);
        Discipline::create(['doc_no' => '001/SP1/XI/2026', 'year' => 2026, 'seq' => 1, 'type' => 'sp1', 'employee_id' => $extra->id, 'issued_on' => '2026-11-01', 'until_on' => '2027-05-01', 'reason' => 'x', 'signer_name' => 'a', 'signer_title' => 'b']);
        $real = Employee::create(['code' => 'E-0500', 'name' => 'Karyawan Nyata', 'gender' => 'P', 'department_id' => $dept->id, 'employment' => 'tetap', 'join_date' => '2026-01-01']);
        $step = RecruitStep::where('kind', 'start')->value('id');
        $extraCandidate = Candidate::create(['nik' => '3273019999999999', 'name' => 'Kandidat Contoh Lain', 'applied_at' => today(), 'step_id' => $step, 'note' => 'Contoh (tambahan)']);
        $realCandidate = Candidate::create(['nik' => '3273018888888888', 'name' => 'coba', 'applied_at' => today(), 'step_id' => $step, 'note' => null]);

        $log = SampleHr::apply(true);

        $this->assertSame(['David', 'Staf gudang'], [$david->refresh()->name, $david->position]);
        $this->assertGreaterThan(0, $david->docs()->count());
        $this->assertNotNull(Candidate::firstWhere('name', 'Dedi Sandiaga'));
        $this->assertNull(Candidate::firstWhere('name', 'Salah'));
        $this->assertNull(Employee::find($extra->id));
        $this->assertSame(0, Discipline::count());   // SP milik karyawan contoh tambahan ikut dibuang
        $this->assertNull(Candidate::find($extraCandidate->id));
        $this->assertNotNull(Employee::find($real->id));   // data nyata tidak disentuh
        $this->assertNotNull(Candidate::find($realCandidate->id));
        $this->assertStringContainsString('dibuang', implode(' ', $log));
        $this->assertSame(3, Candidate::where('note', 'like', 'Contoh%')->count());
    }

    public function test_artisan_command_and_snapshot_export_round_trip(): void
    {
        $this->artisan('sample:hr')->assertSuccessful();
        $this->assertSame(2, Employee::where('code', 'like', 'E-C%')->count());
        Employee::firstWhere('code', 'E-C002')->update(['name' => 'Diubah']);
        $this->artisan('sample:hr', ['--reset' => true])->assertSuccessful();
        $this->assertSame('Detta', Employee::firstWhere('code', 'E-C002')->name);

        // Ekspor ke folder sementara (tidak menimpa snapshot di repo): isi dan tanggal relatif terbawa.
        $tmp = sys_get_temp_dir().'/sample-hr-'.uniqid();
        SampleHr::export($tmp);
        $m = json_decode(file_get_contents($tmp.'/manifest.json'), true);
        $this->assertSame(['E-C001', 'E-C002'], array_column($m['employees'], 'code'));
        $this->assertSame(['Dedi Sandiaga', 'Maya Denada', 'Lucy Sempi'], array_column($m['candidates'], 'name'));
        $this->assertMatchesRegularExpression('/^[+-]\d+d$/', $m['candidates'][1]['events'][2]['scheduled_on']);
        $this->assertFileExists($tmp.'/e-c001-photo.jpg');
        array_map('unlink', glob($tmp.'/*'));
        rmdir($tmp);
    }

    public function test_sample_screen_reset_needs_the_confirmation_word_and_an_administrator(): void
    {
        SampleHr::apply();
        Employee::firstWhere('code', 'E-C001')->update(['name' => 'Diubah']);

        $this->actingAs(User::factory()->supervisor()->create())->post(route('sample.hr'), ['confirm_hr' => 'RESET'])->assertForbidden();
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->get(route('sample.index'))->assertOk()->assertSee('Reset Data Contoh HR');
        $this->post(route('sample.hr'), ['confirm_hr' => 'reset'])->assertSessionHasErrors('confirm_hr');
        $this->assertSame('Diubah', Employee::firstWhere('code', 'E-C001')->name);

        $this->post(route('sample.hr'), ['confirm_hr' => 'RESET'])->assertRedirect(route('sample.index'));
        $this->assertSame('David', Employee::firstWhere('code', 'E-C001')->name);
        $this->assertAuthenticatedAs($admin);   // tidak keluar
    }
}
