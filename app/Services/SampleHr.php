<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Department;
use App\Models\Discipline;
use App\Models\Employee;
use App\Models\RecruitStep;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/* REMARK
 * Tujuan       : Data contoh HR KANONIK yang selalu bisa dikembalikan: dua karyawan (David E-C001 di Maintenance, Detta E-C002 di HR) dan dua kandidat (Maya Denada, Dedi Sandiaga) lengkap dengan dokumen, keluarga, kontrak, riwayat tahap, foto profil, dan gambar KTP tiruan DUMMY.
 *                Sumbernya SNAPSHOT di repo (database/sample/hr: manifest.json + foto JPG + KTP SVG, tanggal yang dekat hari ini disimpan relatif "+3d"). apply() tanpa reset hanya membuat yang belum ada (idempoten, tidak menimpa perubahan);
 *                apply(reset) mengembalikan keempatnya ke keadaan snapshot dan menghapus karyawan contoh tambahan (kode E-C*) dan kandidat contoh tambahan (catatan diawali "Contoh") yang tidak ada di snapshot. Data nyata (tanpa penanda itu) tidak disentuh. export() membuat ulang snapshot dari keadaan database sekarang.
 * Sumber hulu  : database/sample/hr/manifest.json, m_employee (+ family, doc, contract), h_recruit_candidate (+ dept, event), m_department, m_recruit_step, disk 'ga'.
 * Output       : array pesan hasil (apa yang dibuat / dikembalikan / dibuang).
 * Dipakai oleh : perintah sample:hr, EmployeeSampleSeeder, RecruitmentSeeder, layar Data Contoh (Reset data contoh HR).
 * Status       : Aktif (data contoh).
 */
final class SampleHr
{
    public const DIR = 'database/sample/hr';

    private const EMPLOYEE = ['code', 'name', 'nickname', 'gender', 'birth_place', 'birth_date', 'religion', 'marital', 'nik_ktp', 'npwp', 'phone', 'email', 'ktp_address', 'ktp_area_level', 'ktp_area_id', 'home_address', 'home_area_level', 'home_area_id',
        'emergency_name', 'emergency_relation', 'emergency_phone', 'position', 'employment', 'join_date', 'work_status', 'is_active', 'exit_reason', 'exit_date', 'exit_note'];

    private const CANDIDATE = ['nik', 'name', 'phone', 'email', 'birth_date', 'education', 'position', 'source', 'applied_at', 'status', 'note'];

    /** @return array{employees: array, candidates: array} */
    public static function manifest(): array
    {
        return json_decode(file_get_contents(base_path(self::DIR.'/manifest.json')), true);
    }

    /** @param  'employees'|'candidates'|null  $only */
    public static function apply(bool $reset = false, ?string $only = null): array
    {
        $m = self::manifest();
        $log = [];
        DB::transaction(function () use ($m, $reset, $only, &$log) {
            if ($only !== 'candidates') {
                self::employees($m['employees'], $reset, $log);
            }
            if ($only !== 'employees') {
                self::candidates($m['candidates'], $reset, $log);
            }
        });

        return $log;
    }

    // ---------------------------------------------------------------- terapkan

    private static function employees(array $rows, bool $reset, array &$log): void
    {
        $disk = Storage::disk('ga');
        foreach ($rows as $r) {
            $e = Employee::where('code', $r['code'])->first();
            if ($e && ! $reset) {
                $log[] = "Karyawan {$r['code']} {$e->name}: sudah ada, dilewati";

                continue;
            }
            $dept = self::department($r['department']['name'], $r['department']['code']);
            $data = Arr::only($r, self::EMPLOYEE);
            foreach (['ktp', 'home'] as $p) {   // wilayah hanya bila ada di master geo lingkungan ini
                if (($data[$p.'_area_level'] ?? null) === 'CITY' && ! DB::table('geo_city')->where('id_city', $data[$p.'_area_id'])->exists()) {
                    $data[$p.'_area_level'] = $data[$p.'_area_id'] = null;
                }
            }
            $data['department_id'] = $dept->id;
            $e = $e ? tap($e)->update($data) : Employee::create($data);
            foreach (['family' => 'family', 'docs' => 'docs', 'contracts' => 'contracts'] as $rel => $key) {
                $e->{$rel}()->delete();
                $e->{$rel}()->createMany(array_map(fn ($x) => self::dates($x, ['birth_date', 'expiry_date', 'start_date', 'end_date', 'probation_end']), $r[$key]));
            }
            self::files($e, $r['photo'], $r['ktp'], "employee/{$e->id}");
            $log[] = ($reset ? 'Karyawan dikembalikan: ' : 'Karyawan dibuat: ')."{$e->code} {$e->name} ({$dept->name})";
        }
        if ($reset) {
            $keep = array_column($rows, 'code');
            foreach (Employee::where('code', 'like', 'E-C%')->whereNotIn('code', $keep)->get() as $x) {
                Discipline::where('employee_id', $x->id)->delete();
                $disk->deleteDirectory("employee/{$x->id}");
                $log[] = "Karyawan contoh tambahan dibuang: {$x->code} {$x->name}";
                $x->delete();
            }
        }
    }

    private static function candidates(array $rows, bool $reset, array &$log): void
    {
        $disk = Storage::disk('ga');
        $start = RecruitStep::where('kind', 'start')->value('id');
        foreach ($rows as $r) {
            $c = Candidate::where('nik', $r['nik'])->first();
            if ($c && ! $reset) {
                $log[] = "Kandidat {$r['nik']} {$c->name}: sudah ada, dilewati";

                continue;
            }
            $data = self::dates(Arr::only($r, self::CANDIDATE), ['birth_date', 'applied_at']);
            $data['step_id'] = RecruitStep::where('name', $r['step'])->value('id') ?? $start;
            $c = $c ? tap($c)->update($data) : Candidate::create($data);
            $c->departments()->sync(array_map(fn ($n) => self::department($n)->id, $r['departments']));
            $c->events()->delete();
            $c->moves()->delete();
            foreach ($r['events'] as $ev) {
                $stepId = RecruitStep::where('name', $ev['step'])->value('id');
                if ($stepId) {
                    $c->events()->create(self::dates(['step_id' => $stepId] + Arr::except($ev, ['step']), ['scheduled_on']));
                }
            }
            self::files($c, $r['photo'], $r['ktp'], "recruit/{$c->id}");
            $log[] = ($reset ? 'Kandidat dikembalikan: ' : 'Kandidat dibuat: ')."{$c->nik} {$c->name}";
        }
        if ($reset) {
            $keep = array_column($rows, 'nik');
            foreach (Candidate::where('note', 'like', 'Contoh%')->whereNotIn('nik', $keep)->get() as $x) {
                $disk->deleteDirectory("recruit/{$x->id}");
                $log[] = "Kandidat contoh tambahan dibuang: {$x->nik} {$x->name}";
                $x->delete();
            }
        }
    }

    // Bagian dicocokkan menurut nama (atau kode); dibuat aktif bila belum ada.
    private static function department(string $name, ?string $code = null): Department
    {
        $d = Department::query()->where('name', $name)->when($code, fn ($q) => $q->orWhere('code', $code))->first() ?? Department::create(['code' => $code ?? strtoupper(substr(preg_replace('/\W/', '', $name), 0, 8)), 'name' => $name]);

        return $d->is_active ? $d : tap($d)->update(['is_active' => true]);
    }

    // Foto profil + KTP tiruan dari snapshot ke disk privat 'ga'; jalur dan nama berkas ditulis ke record.
    private static function files(Employee|Candidate $m, string $photo, string $ktp, string $folder): void
    {
        $disk = Storage::disk('ga');
        $old = $m->photo_path;
        $disk->put("$folder/profile.jpg", file_get_contents(base_path(self::DIR.'/'.$photo)));
        $disk->put("$folder/ktp-contoh.svg", file_get_contents(base_path(self::DIR.'/'.$ktp)));
        if ($old && $old !== "$folder/profile.jpg") {
            $disk->delete($old);
        }
        $m->update(['photo_path' => "$folder/profile.jpg", 'photo_name' => 'profile.jpg', 'ktp_path' => "$folder/ktp-contoh.svg", 'ktp_name' => 'ktp-contoh.svg']);
    }

    // "+3d" / "-5d" = relatif terhadap hari ini; selain itu tanggal apa adanya.
    private static function dates(array $row, array $keys): array
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && preg_match('/^([+-]\d+)d$/', $row[$k], $m)) {
                $row[$k] = today()->addDays((int) $m[1])->toDateString();
            }
        }

        return $row;
    }

    // ---------------------------------------------------------------- ekspor

    // Membuat ulang snapshot dari database sekarang: karyawan E-C*, kandidat berpenanda "Contoh". Tanggal yang dekat hari ini (-400 sampai +800 hari) disimpan relatif.
    public static function export(?string $dir = null): array
    {
        $disk = Storage::disk('ga');
        $dir ??= base_path(self::DIR);
        @mkdir($dir, 0777, true);
        foreach (glob($dir.'/*') as $f) {
            is_file($f) && unlink($f);
        }
        $rel = function ($d) {
            if (! $d) {
                return null;
            }
            $days = (int) today()->diffInDays($d, false);

            return ($days >= -400 && $days <= 800) ? sprintf('%+dd', $days) : $d->format('Y-m-d');
        };
        $m = ['employees' => [], 'candidates' => []];
        $steps = RecruitStep::pluck('name', 'id');

        foreach (Employee::with(['department', 'family', 'docs', 'contracts'])->where('code', 'like', 'E-C%')->orderBy('code')->get() as $e) {
            $k = strtolower($e->code);
            copy($disk->path($e->photo_path), "$dir/$k-photo.jpg");
            copy($disk->path($e->ktp_path), "$dir/$k-ktp.svg");
            $row = Arr::only($e->toArray(), self::EMPLOYEE);
            foreach (['birth_date', 'join_date', 'exit_date'] as $f) {
                $row[$f] = $e->{$f}?->format('Y-m-d');
            }
            $m['employees'][] = $row + [
                'department' => ['name' => $e->department->name, 'code' => $e->department->code], 'photo' => "$k-photo.jpg", 'ktp' => "$k-ktp.svg",
                'family' => $e->family->map(fn ($x) => ['relation' => $x->relation, 'name' => $x->name, 'birth_date' => $x->birth_date?->format('Y-m-d'), 'nik' => $x->nik, 'is_deceased' => $x->is_deceased])->all(),
                'docs' => $e->docs->map(fn ($x) => ['doc_type' => $x->doc_type, 'doc_no' => $x->doc_no, 'expiry_date' => $rel($x->expiry_date), 'note' => $x->note])->all(),
                'contracts' => $e->contracts->map(fn ($x) => ['contract_no' => $x->contract_no, 'employment' => $x->employment, 'start_date' => $x->start_date->format('Y-m-d'), 'end_date' => $rel($x->end_date), 'probation_end' => $x->probation_end?->format('Y-m-d')])->all(),
            ];
        }
        foreach (Candidate::with(['departments', 'events'])->where('note', 'like', 'Contoh%')->orderBy('nik')->get() as $c) {
            $k = 'nik'.$c->nik;   // NIK penuh: dua kandidat bisa berakhiran angka sama
            copy($disk->path($c->photo_path), "$dir/$k-photo.jpg");
            copy($disk->path($c->ktp_path), "$dir/$k-ktp.svg");
            $row = Arr::only($c->toArray(), self::CANDIDATE);
            $row['birth_date'] = $c->birth_date?->format('Y-m-d');
            $row['applied_at'] = $rel($c->applied_at);
            $m['candidates'][] = $row + [
                'step' => $steps[$c->step_id], 'departments' => $c->departments->pluck('name')->all(), 'photo' => "$k-photo.jpg", 'ktp' => "$k-ktp.svg",
                'events' => $c->events->map(fn ($x) => ['step' => $steps[$x->step_id], 'scheduled_on' => $rel($x->scheduled_on), 'scheduled_time' => $x->scheduled_time, 'assessor' => $x->assessor, 'result' => $x->result, 'note' => $x->note])->all(),
            ];
        }
        file_put_contents($dir.'/manifest.json', json_encode($m, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        return ['Snapshot ditulis ke '.$dir.': '.count($m['employees']).' karyawan, '.count($m['candidates']).' kandidat'];
    }
}
