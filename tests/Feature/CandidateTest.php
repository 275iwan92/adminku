<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Department;
use App\Models\Employee;
use App\Models\RecruitStep;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga Rekrutmen (HR, HRR): NIK 16 angka wajib, minimal satu bagian (boleh banyak), kandidat baru mulai di Daftar, pindah tahap bebas (loncat / mundur) menambah riwayat,
 *                peringatan NIK kembar / sudah karyawan, Jadikan karyawan terisi dari kandidat, berkas KTP privat, Setup tahap (awal dan akhir tetap), papan dan daftar terbuka, dan hak modul.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class CandidateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Department $dept;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('ga');
        $this->admin = User::factory()->administrator()->create();
        $this->dept = Department::create(['code' => 'PRD', 'name' => 'Produksi']);
    }

    private function payload(array $over = []): array
    {
        return $over + ['nik' => '3273010101010001', 'name' => 'Dedi Kurnia', 'applied_at' => '2026-11-02', 'departments' => [$this->dept->id]];
    }

    private function step(string $name): RecruitStep
    {
        return RecruitStep::firstWhere('name', $name);
    }

    public function test_saves_a_candidate_at_the_first_step_with_several_departments_and_files(): void
    {
        $gudang = Department::create(['code' => 'GDG', 'name' => 'Gudang']);
        $this->actingAs($this->admin)->post(route('candidates.store'), $this->payload([
            'nik' => '3273-0101-0101-0001', 'departments' => [$this->dept->id, $gudang->id], 'ktp' => UploadedFile::fake()->image('ktp.jpg'),
        ]))->assertRedirect();

        $c = Candidate::firstOrFail();
        $this->assertSame('3273010101010001', $c->nik);
        $this->assertSame('Daftar', $c->step->name);
        $this->assertSame(2, $c->departments()->count());
        $this->assertSame(1, $c->events()->count());
        Storage::disk('ga')->assertExists($c->ktp_path);
        $this->get(route('candidates.file', [$c, 'ktp']))->assertOk();
        $this->get(route('candidates.show', $c))->assertOk();
        $this->get(route('candidates.create'))->assertOk();
    }

    public function test_nik_and_department_are_required(): void
    {
        $this->actingAs($this->admin)->post(route('candidates.store'), $this->payload(['nik' => '123']))->assertSessionHasErrors('nik');
        $this->post(route('candidates.store'), $this->payload(['nik' => '']))->assertSessionHasErrors('nik');
        $this->post(route('candidates.store'), $this->payload(['departments' => []]))->assertSessionHasErrors('departments');
        $this->assertSame(0, Candidate::count());
    }

    public function test_move_is_free_and_adds_history(): void
    {
        $this->actingAs($this->admin)->post(route('candidates.store'), $this->payload());
        $c = Candidate::firstOrFail();

        $this->postJson(route('candidates.move'), ['card' => $c->id, 'from' => $c->step_id, 'to' => $this->step('TTD penawaran')->id])->assertOk()->assertJson(['ok' => true]);   // loncat
        $this->postJson(route('candidates.move'), ['card' => $c->id, 'to' => $this->step('Interview 1')->id])->assertStatus(422);   // tahap berjadwal: tanggal + jam wajib
        $this->postJson(route('candidates.move'), ['card' => $c->id, 'to' => $this->step('Interview 1')->id, 'scheduled_on' => '2026-11-05', 'scheduled_time' => '09:30', 'assessor' => 'Pak Hadi'])->assertOk();   // mundur
        $c->refresh();
        $this->assertSame('Interview 1', $c->step->name);
        $this->assertSame(3, $c->events()->count());
        $this->assertSame('waiting', $c->events()->reorder('id', 'desc')->first()->result);

        $this->postJson(route('candidates.move'), ['card' => $c->id, 'to' => $this->step('Diterima')->id])->assertOk();
        $this->assertSame('pass', $c->events()->reorder('id', 'desc')->first()->result);

        $log = $c->moves()->with(['from', 'to'])->get();   // terbaru dulu
        $this->assertSame(['Diterima', 'Interview 1', 'TTD penawaran'], $log->map(fn ($m) => $m->to->name)->all());   // yang ditolak (422) tidak tercatat
        $this->assertSame(['Interview 1', 'TTD penawaran', 'Daftar'], $log->map(fn ($m) => $m->from->name)->all());
        $this->assertSame('Pak Hadi', $log[1]->assessor);
        $this->assertSame($this->admin->id, $log[0]->moved_by);
        $this->step('Tes')->update(['is_active' => false]);
        $this->postJson(route('candidates.move'), ['card' => $c->id, 'to' => $this->step('Tes')->id])->assertStatus(422);
    }

    public function test_update_replaces_history_and_validates_time(): void
    {
        $this->actingAs($this->admin)->post(route('candidates.store'), $this->payload());
        $c = Candidate::firstOrFail();
        $row = ['step_id' => $this->step('Interview 1')->id, 'scheduled_on' => '2026-11-05', 'scheduled_time' => '09:30', 'assessor' => 'Pak Hadi', 'result' => 'pass'];

        $this->put(route('candidates.update', $c), $this->payload(['step_id' => $c->step_id, 'status' => 'active', 'events' => [$row]]))->assertRedirect();
        $this->assertSame('Pak Hadi', $c->events()->first()->assessor);
        $this->assertSame(1, $c->events()->count());

        $this->put(route('candidates.update', $c), $this->payload(['events' => [['scheduled_time' => '9.30'] + $row]]))->assertSessionHasErrors('events.0.scheduled_time');

        $this->put(route('candidates.update', $c), $this->payload(['status' => 'rejected', 'events' => []]))->assertRedirect();
        $page = $this->get(route('candidates.index'))->assertOk()->viewData('cards');
        $this->assertSame([], $page);   // ditolak: tidak ada di papan
        $this->get(route('candidates.index', ['view' => 'list']))->assertOk();
    }

    public function test_warns_about_repeated_nik_and_existing_employee_and_prefills_the_employee_form(): void
    {
        $this->actingAs($this->admin)->post(route('candidates.store'), $this->payload());
        $this->post(route('candidates.store'), $this->payload(['name' => 'Dedi lagi']));
        Employee::create(['code' => 'E-0001', 'name' => 'Dedi K', 'gender' => 'L', 'department_id' => $this->dept->id, 'employment' => 'tetap', 'join_date' => '2026-01-01', 'nik_ktp' => '3273010101010001']);

        $last = Candidate::orderByDesc('id')->first();
        $warnings = $this->get(route('candidates.show', $last))->assertOk()->viewData('warnings');
        $this->assertCount(2, $warnings);
        $this->assertStringContainsString('pernah melamar', $warnings[0]);
        $this->assertStringContainsString('E-0001', $warnings[1]);

        $this->get(route('employees.create', ['kandidat' => $last->id]))->assertOk()->assertSee('Dedi lagi')->assertSee('3273010101010001');
    }

    public function test_steps_setup_keeps_first_and_last_fixed(): void
    {
        $start = RecruitStep::firstWhere('kind', 'start');
        $end = RecruitStep::firstWhere('kind', 'end');
        $this->actingAs($this->admin)->get(route('recruit-steps.index'))->assertOk()->assertSee('Kembali')->assertSee(route('candidates.index'), false);
        $this->put(route('recruit-steps.update', $start), ['name' => 'Lain', 'sort' => 999])->assertRedirect();
        $this->assertSame('Daftar', $start->refresh()->name);
        $this->put(route('recruit-steps.update', $end), ['_action' => 'deactivate'])->assertSessionHasErrors('name');
        $this->assertTrue($end->refresh()->is_active);

        $this->post(route('recruit-steps.store'), ['name' => 'Medical check', 'sort' => 500])->assertRedirect();
        $names = RecruitStep::ordered()->pluck('name')->all();
        $this->assertSame('Daftar', $names[0]);
        $this->assertSame('Diterima', end($names));   // Diterima tetap terakhir walau urutan tengah lebih besar
        $this->post(route('recruit-steps.store'), ['name' => 'Tes', 'sort' => 5])->assertSessionHasErrors('name');
    }

    public function test_board_follows_the_devlib_kanban_design(): void
    {
        $this->actingAs($this->admin)->post(route('candidates.store'), $this->payload(['position' => 'Operator']));
        $c = Candidate::firstOrFail();
        $this->postJson(route('candidates.move'), ['card' => $c->id, 'to' => $this->step('Tes')->id, 'scheduled_on' => today()->toDateString(), 'scheduled_time' => '10:00', 'assessor' => 'Bu Sari']);
        $this->step('Interview 2')->update(['tone' => null]);

        $page = $this->get(route('candidates.index'))->assertOk()->assertSee('flex: 1 1 0', false)->assertSee('.dl2-lm-fold', false);   // kolom sama lebar; panel kiri mengecil otomatis lewat tombol lipat Layer Menu v2
        $cols = collect($page->viewData('columns'));
        $this->assertSame(['neutral', 'accent', 'violet', 'warn', 'accent', 'success'], $cols->pluck('tone')->map(fn ($t, $i) => $i === 3 ? 'warn' : $t)->all());   // Interview 2 tanpa warna: bergilir, bukan abu-abu
        $this->assertNotSame('neutral', $cols[3]['tone']);
        $this->assertTrue($cols->last()['done']);

        $card = $page->viewData('cards')[0];
        $this->assertSame('Bu Sari', $card['assignee']);
        $this->assertSame('High', $card['priority']);   // jadwal hari ini
        $this->assertStringContainsString(today()->toDateString().' 10:00', $card['due']);
        $this->assertSame(['Produksi'], collect($card['tags'])->where('tone', 'accent')->pluck('label')->all());
        $this->assertSame(['Interview 1', 'Tes', 'Interview 2', 'TTD penawaran', 'Diterima'], array_column($card['checklist'], 'text'));
        $this->assertSame('Operator · 3273 **** **** 0001', $card['desc']);   // NIK samar di kartu
        $meta = $page->viewData('meta')[$card['id']];
        $this->assertSame(['src' => 'other', 'assessor' => 'Bu Sari', 'att' => false], collect($meta)->only(['src', 'assessor', 'att'])->all());
        $kpi = $page->viewData('kpi');
        $this->assertSame([1, 1, 1, 0], [$kpi['active'], $kpi['interviews'], $kpi['waiting'], $kpi['hired']]);
        $this->step('Tes')->update(['wip_limit' => 5]);
        $this->assertSame(5, collect($this->get(route('candidates.index'))->viewData('columns'))->firstWhere('label', 'Tes')['limit']);

        $this->put(route('recruit-steps.update', $this->step('Tes')), ['name' => 'Tes', 'sort' => 30, 'tone' => 'pink'])->assertSessionHasErrors('tone');
        $this->put(route('recruit-steps.update', $this->step('Tes')), ['name' => 'Tes', 'sort' => 30, 'tone' => 'danger', 'needs_schedule' => 1])->assertRedirect();
        $this->assertSame('danger', $this->step('Tes')->tone);
    }

    public function test_leaving_a_scheduled_step_without_result_is_flagged_and_logged(): void
    {
        $this->actingAs($this->admin)->post(route('candidates.store'), $this->payload());
        $c = Candidate::firstOrFail();
        $when = ['scheduled_on' => '2026-11-05', 'scheduled_time' => '09:30'];
        $this->postJson(route('candidates.move'), ['card' => $c->id, 'to' => $this->step('Interview 1')->id] + $when)->assertOk();

        $meta = $this->get(route('candidates.index'))->viewData('meta')[(string) $c->id];
        $this->assertTrue($meta['pending']);   // hasil Interview 1 belum diisi: papan menampilkan peringatan
        $this->assertSame('Interview 1', $meta['stepName']);

        $this->postJson(route('candidates.result', $c), ['result' => ''])->assertStatus(422);
        $this->postJson(route('candidates.result', $c), ['result' => 'pass', 'note' => 'Baik'])->assertOk();
        $this->assertFalse($this->get(route('candidates.index'))->viewData('meta')[(string) $c->id]['pending']);
        $this->assertSame('pass', $c->events()->reorder('id', 'desc')->first()->result);
        $this->postJson(route('candidates.result', $c), ['result' => 'fail'])->assertStatus(422);   // tidak ada yang menunggu lagi

        $this->postJson(route('candidates.move'), ['card' => $c->id, 'to' => $this->step('Tes')->id] + $when + ['no_result' => 1])->assertOk();
        $this->postJson(route('candidates.move'), ['card' => $c->id, 'to' => $this->step('TTD penawaran')->id, 'no_result' => 1])->assertOk();   // keluar dari Tes tanpa hasil
        $flags = $c->moves()->get()->pluck('no_result')->all();
        $this->assertSame([true, true, false], $flags);   // terbaru dulu: TTD, Tes (keduanya ditandai), Interview 1
        $this->get(route('candidates.show', $c))->assertOk()->assertSee('dipindah tanpa hasil');
    }

    public function test_candidate_photo_is_a_private_file_shown_as_board_avatar_and_forms_have_the_lightbox(): void
    {
        $this->actingAs($this->admin)->post(route('candidates.store'), $this->payload(['photo' => UploadedFile::fake()->image('foto.jpg'), 'ktp' => UploadedFile::fake()->image('ktp.png')]))->assertRedirect();
        $c = Candidate::firstOrFail();
        Storage::disk('ga')->assertExists([$c->photo_path, $c->ktp_path]);
        $this->get(route('candidates.file', [$c, 'photo']))->assertOk();
        $this->assertSame(route('candidates.file', [$c, 'photo']), $this->get(route('candidates.index'))->viewData('meta')[(string) $c->id]['photo']);
        $this->get(route('candidates.show', $c))->assertOk()->assertSee('lb-ov', false)->assertSee('foto.jpg');   // lightbox terpasang
        $this->put(route('candidates.update', $c), $this->payload(['photo' => UploadedFile::fake()->create('x.exe', 10)]))->assertSessionHasErrors('photo');
    }

    public function test_candidate_ktp_crop_wins_over_the_plain_file_and_form_uses_the_photo_boxes(): void
    {
        $this->actingAs($this->admin)->post(route('candidates.store'), $this->payload(['ktp' => UploadedFile::fake()->create('lama.pdf', 50, 'application/pdf'), 'ktp_crop' => UploadedFile::fake()->image('profile.jpg', 856, 540)]))->assertRedirect();
        $c = Candidate::firstOrFail();
        $this->assertSame('profile.jpg', $c->ktp_name);
        $this->get(route('candidates.show', $c))->assertOk()->assertSee('&quot;outW&quot;:856', false)->assertSee('&quot;outH&quot;:540', false);   // photo-crop DevLib 2: ukuran hasil crop di konfigurasi
        $this->put(route('candidates.update', $c), $this->payload(['ktp_crop' => UploadedFile::fake()->image('x.png')]))->assertSessionHasErrors('ktp_crop');
    }

    public function test_access_follows_the_group(): void
    {
        $this->actingAs(User::factory()->supervisor()->create())->get(route('candidates.index'))->assertForbidden();

        $group = UserGroup::create(['code' => 'HRG', 'name' => 'HR']);
        $group->syncAccess(['HRR' => 'ro']);
        $reader = User::factory()->supervisor()->create(['group_id' => $group->id]);
        $this->actingAs($reader)->get(route('candidates.index'))->assertOk();
        $this->post(route('candidates.store'), $this->payload())->assertForbidden();

        $group->syncAccess(['HRR' => 'rw']);
        $this->actingAs($reader->fresh())->post(route('candidates.store'), $this->payload())->assertRedirect();
    }
}
