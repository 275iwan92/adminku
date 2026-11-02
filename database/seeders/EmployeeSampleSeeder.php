<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Support\SampleImages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/* REMARK
 * Tujuan       : Data contoh Karyawan: dua karyawan FIKTIF (satu laki-laki, satu perempuan) dengan SEMUA tab terisi (Profil, Pekerjaan, Dokumen, Keluarga, Kontrak), foto profil ilustrasi (SVG buatan sendiri, bukan foto orang nyata)
 *                dan scan KTP tiruan bertuliskan CONTOH / DUMMY dengan data yang sama dengan karyawannya. Tanpa akun Jays (tidak menyentuh pengguna). Idempoten menurut kode karyawan: yang sudah ada tidak diubah.
 * Sumber hulu  : m_department (bagian aktif pertama; dibuat "Bagian Contoh" bila belum ada), geo_city (wilayah KTP bila data wilayah ada).
 * Output       : m_employee (+ family, doc, contract), berkas di disk 'ga' folder employee/{id}.
 * Dipakai oleh : DatabaseSeeder; dijalankan tersendiri dengan --class=EmployeeSampleSeeder.
 * Status       : Aktif (data contoh).
 */
class EmployeeSampleSeeder extends Seeder
{
    public function run(): void
    {
        $dept = Department::query()->where('is_active', true)->orderBy('id')->first() ?? Department::create(['code' => 'CONTOH', 'name' => 'Bagian Contoh']);
        // Wilayah KTP = kota yang namanya sama dengan tempat lahir bila ada, kalau tidak kota pertama; kosong bila data wilayah belum diimpor.
        $area = function (string $place) {
            $city = DB::table('geo_city')->where('activate', true)->whereIn('city_name', [strtoupper($place), 'KOTA '.strtoupper($place)])->orderBy('id_city')->first() ?? DB::table('geo_city')->where('activate', true)->orderBy('id_city')->first();

            return $city ? ['ktp_area_level' => 'CITY', 'ktp_area_id' => $city->id_city] : [];
        };

        $people = [
            [
                'e' => ['code' => 'E-C001', 'name' => 'Budi Contoh', 'nickname' => 'Budi', 'gender' => 'L', 'birth_place' => 'Malang', 'birth_date' => '1992-04-17', 'religion' => 'Islam', 'marital' => 'kawin', 'nik_ktp' => '3573011704920001', 'npwp' => '09.876.543.2-651.000',
                    'phone' => '0812-0000-0101', 'email' => 'budi.contoh@example.test', 'ktp_address' => 'Jl. Contoh No. 1, RT 01 RW 02', 'emergency_name' => 'Rina Contoh', 'emergency_relation' => 'Istri', 'emergency_phone' => '0812-0000-0102',
                    'position' => 'Staf gudang (Contoh)', 'employment' => 'tetap', 'join_date' => '2021-02-03', 'work_status' => 'working'],
                'avatar' => ['#2f6f9f', '#f1c9a5'],
                'family' => [['relation' => 'istri', 'name' => 'Rina Contoh', 'birth_date' => '1993-08-09', 'nik' => '3573014908930002'], ['relation' => 'anak', 'name' => 'Raka Contoh', 'birth_date' => '2018-03-12', 'nik' => null]],
                'docs' => [['doc_type' => 'ktp', 'doc_no' => '3573011704920001', 'expiry_date' => null], ['doc_type' => 'kk', 'doc_no' => '3573010000000001', 'expiry_date' => null], ['doc_type' => 'npwp', 'doc_no' => '09.876.543.2-651.000', 'expiry_date' => null],
                    ['doc_type' => 'sim', 'doc_no' => 'SIM-C-0001', 'expiry_date' => now()->addDays(25)->toDateString()], ['doc_type' => 'bpjs_kes', 'doc_no' => '0001234567890', 'expiry_date' => null]],
                'contracts' => [['contract_no' => 'K-2021/001', 'employment' => 'kontrak', 'start_date' => '2021-02-03', 'end_date' => '2022-02-02', 'probation_end' => '2021-05-03'], ['contract_no' => 'T-2022/014', 'employment' => 'tetap', 'start_date' => '2022-02-03', 'end_date' => null, 'probation_end' => null]],
            ],
            [
                'e' => ['code' => 'E-C002', 'name' => 'Siti Contoh', 'nickname' => 'Siti', 'gender' => 'P', 'birth_place' => 'Surabaya', 'birth_date' => '1995-11-23', 'religion' => 'Islam', 'marital' => 'belum', 'nik_ktp' => '3578016311950003', 'npwp' => '08.765.432.1-501.000',
                    'phone' => '0857-0000-0201', 'email' => 'siti.contoh@example.test', 'ktp_address' => 'Jl. Contoh Raya No. 22, RT 03 RW 04', 'emergency_name' => 'Hadi Contoh', 'emergency_relation' => 'Ayah', 'emergency_phone' => '0857-0000-0202',
                    'position' => 'Admin HR (Contoh)', 'employment' => 'kontrak', 'join_date' => '2025-05-10', 'work_status' => 'working'],
                'avatar' => ['#a14b7a', '#f3d2b8'],
                'family' => [['relation' => 'orang_tua', 'name' => 'Hadi Contoh', 'birth_date' => '1965-01-05', 'nik' => '3578010501650004'], ['relation' => 'orang_tua', 'name' => 'Lastri Contoh', 'birth_date' => '1968-06-14', 'nik' => null]],
                'docs' => [['doc_type' => 'ktp', 'doc_no' => '3578016311950003', 'expiry_date' => null], ['doc_type' => 'kk', 'doc_no' => '3578010000000002', 'expiry_date' => null], ['doc_type' => 'ijazah', 'doc_no' => 'S1-2017/0456', 'expiry_date' => null],
                    ['doc_type' => 'skck', 'doc_no' => 'SKCK-2025/0099', 'expiry_date' => now()->addMonths(5)->toDateString()], ['doc_type' => 'bpjs_tk', 'doc_no' => '0009876543210', 'expiry_date' => null]],
                'contracts' => [['contract_no' => 'K-2025/031', 'employment' => 'kontrak', 'start_date' => '2025-05-10', 'end_date' => now()->addDays(40)->toDateString(), 'probation_end' => '2025-08-10']],
            ],
        ];

        foreach ($people as $p) {
            if (Employee::where('code', $p['e']['code'])->exists()) {
                continue;
            }
            $e = Employee::create($p['e'] + $area($p['e']['birth_place']) + ['department_id' => $dept->id]);
            $e->family()->createMany($p['family']);
            $e->docs()->createMany(array_map(fn ($d) => $d + ['note' => 'Contoh (data fiktif)'], $p['docs']));
            $e->contracts()->createMany($p['contracts']);

            $disk = Storage::disk('ga');
            $disk->put("employee/{$e->id}/foto-contoh.svg", SampleImages::avatar($p['avatar'][0], $p['avatar'][1], $p['e']['gender']));
            $disk->put("employee/{$e->id}/ktp-contoh.svg", SampleImages::ktp(['nik' => $e->nik_ktp, 'name' => $e->name, 'birth_place' => $e->birth_place, 'birth_date' => $e->birth_date->format('d-m-Y'), 'gender' => $e->gender, 'address' => $e->ktp_address, 'religion' => $e->religion, 'marital' => $e->marital]));
            $e->update(['photo_path' => "employee/{$e->id}/foto-contoh.svg", 'photo_name' => 'foto-contoh.svg', 'ktp_path' => "employee/{$e->id}/ktp-contoh.svg", 'ktp_name' => 'ktp-contoh.svg']);
        }
    }
}
