<?php

namespace Database\Seeders;

use App\Services\SampleHr;
use Illuminate\Database\Seeder;

/* REMARK
 * Tujuan       : Data contoh Rekrutmen dari snapshot HR kanonik (database/sample/hr): kandidat Maya Denada (tahap Tes, riwayat Interview 1 Lulus dan Tes Menunggu) dan Dedi Sandiaga (tahap Daftar, bagian Maintenance), dengan foto profil dan KTP tiruan DUMMY, bercatatan "Contoh".
 *                Idempoten: kandidat yang sudah ada tidak diubah (untuk mengembalikan ke snapshot: php artisan sample:hr --reset).
 * Sumber hulu  : App\Services\SampleHr, m_recruit_step (diisi migrasi).
 * Output       : h_recruit_candidate, h_recruit_candidate_dept, h_recruit_event, berkas di disk 'ga' folder recruit/{id}.
 * Dipakai oleh : DatabaseSeeder; dijalankan tersendiri dengan --class=RecruitmentSeeder.
 * Status       : Aktif (data contoh).
 */
class RecruitmentSeeder extends Seeder
{
    public function run(): void
    {
        SampleHr::apply(false, 'candidates');
    }
}
