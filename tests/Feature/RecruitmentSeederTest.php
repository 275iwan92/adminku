<?php

namespace Tests\Feature;

use App\Models\Candidate;
use Database\Seeders\RecruitmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga data contoh Rekrutmen: dua kandidat fiktif bercatatan Contoh (satu di Daftar, satu di Tes dengan riwayat dan jadwal), idempoten, dan tidak mengubah kandidat yang sudah ada.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class RecruitmentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_two_marked_candidates_once(): void
    {
        $this->seed(RecruitmentSeeder::class);
        $this->seed(RecruitmentSeeder::class);

        $this->assertSame(2, Candidate::count());
        $dedi = Candidate::with('step', 'events', 'departments')->firstWhere('name', 'Dedi Contoh');
        $maya = Candidate::with('step', 'events', 'departments')->firstWhere('name', 'Maya Contoh');
        $this->assertSame('Daftar', $dedi->step->name);
        $this->assertSame('Tes', $maya->step->name);
        $this->assertSame(['pass', 'pass', 'waiting'], $maya->events->pluck('result')->all());
        $this->assertTrue($maya->events->last()->scheduled_on->isFuture());
        $this->assertSame([16, 16], [strlen($dedi->nik), strlen($maya->nik)]);
        $this->assertStringContainsString('Contoh', $maya->note);
        $this->assertGreaterThanOrEqual(1, $maya->departments->count());
    }
}
