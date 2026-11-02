<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\Department;
use App\Models\RecruitStep;
use App\Support\SampleImages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/* REMARK
 * Tujuan       : Data contoh Rekrutmen: dua kandidat FIKTIF bercatatan "Contoh": Dedi Contoh di tahap Daftar, dan Maya Contoh di tahap Tes dengan riwayat Interview 1 (Lulus) dan Tes (Menunggu, jadwal beberapa hari lagi).
 *                Tiap kandidat contoh punya foto profil ilustrasi dan foto KTP tiruan (SVG buatan sendiri, bertuliskan DUMMY / CONTOH, data fiktif sama dengan kandidatnya).
 *                Idempoten menurut NIK (kandidat yang sudah ada tidak diubah, tetapi kandidat contoh yang belum punya foto / KTP dilengkapi); tidak menyentuh akun atau master lain selain bagian contoh bila belum ada bagian aktif.
 * Sumber hulu  : m_recruit_step (diisi migrasi), m_department.
 * Output       : h_recruit_candidate, h_recruit_candidate_dept, h_recruit_event, berkas di disk 'ga' folder recruit/{id}.
 * Dipakai oleh : DatabaseSeeder; dijalankan tersendiri dengan --class=RecruitmentSeeder.
 * Status       : Aktif (data contoh).
 */
class RecruitmentSeeder extends Seeder
{
    public function run(): void
    {
        $start = RecruitStep::where('kind', 'start')->value('id');
        $step = fn (string $name) => RecruitStep::where('name', $name)->value('id') ?? $start;
        $depts = Department::query()->where('is_active', true)->orderBy('id')->pluck('id');
        $dept = fn (int $i) => $depts->isEmpty() ? Department::firstOrCreate(['code' => 'CONTOH'], ['name' => 'Bagian Contoh'])->id : $depts[$i % $depts->count()];

        $rows = [
            ['3273019901010001', 'Dedi Contoh', 'Operator mesin', '0812-0000-0001', 'dedi.contoh@example.test', 'walkin', 'SMA/SMK', $start, 1, 'L', '1999-01-01', ['#2f6f9f', '#f1c9a5'], [[$start, null, null, null, 'pass']]],
            ['3273019902020002', 'Maya Contoh', 'Admin gudang', '0857-0000-0002', 'maya.contoh@example.test', 'referral', 'D3', $step('Tes'), 6, 'P', '1999-02-02', ['#a14b7a', '#f3d2b8'], [
                [$start, null, null, null, 'pass'],
                [$step('Interview 1'), -3, '09:30', 'Pak Hadi', 'pass'],
                [$step('Tes'), 3, '10:00', 'Bu Sari', 'waiting'],
            ]],
        ];

        foreach ($rows as $i => [$nik, $name, $position, $phone, $email, $source, $education, $stepId, $daysAgo, $gender, $born, $colors, $events]) {
            $c = Candidate::where('nik', $nik)->first();
            if (! $c) {
                $c = Candidate::create(['nik' => $nik, 'name' => $name, 'phone' => $phone, 'email' => $email, 'education' => $education, 'position' => $position, 'source' => $source, 'birth_date' => $born,
                    'applied_at' => today()->subDays($daysAgo), 'step_id' => $stepId, 'status' => 'active', 'note' => 'Contoh (data fiktif)']);
                $c->departments()->sync(array_unique([$dept($i), $dept($i + 1)]));
                foreach ($events as [$eventStep, $offset, $time, $assessor, $result]) {
                    $c->events()->create(['step_id' => $eventStep, 'scheduled_on' => $offset === null ? null : today()->addDays($offset), 'scheduled_time' => $time, 'assessor' => $assessor, 'result' => $result, 'note' => $result === 'pass' && $assessor ? 'Contoh: komunikasi baik' : null]);
                }
            }
            if (str_starts_with((string) $c->note, 'Contoh') && ! $c->birth_date) {   // kandidat contoh lama dari versi seeder sebelumnya
                $c->update(['birth_date' => $born]);
            }
            if (str_starts_with((string) $c->note, 'Contoh') && ! $c->photo_path) {
                $disk = Storage::disk('ga');
                $disk->put("recruit/{$c->id}/foto-contoh.svg", SampleImages::avatar($colors[0], $colors[1], $gender));
                $disk->put("recruit/{$c->id}/ktp-contoh.svg", SampleImages::ktp(['nik' => $c->nik, 'name' => $c->name, 'birth_place' => 'KOTA CONTOH', 'birth_date' => $c->birth_date->format('d-m-Y'), 'gender' => $gender, 'address' => 'JL. CONTOH NO. '.($i + 7), 'religion' => 'Islam', 'marital' => 'belum']));
                $c->update(['photo_path' => "recruit/{$c->id}/foto-contoh.svg", 'photo_name' => 'foto-contoh.svg', 'ktp_path' => "recruit/{$c->id}/ktp-contoh.svg", 'ktp_name' => 'ktp-contoh.svg']);
            }
        }
    }
}
