<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\RecruitStep;
use Database\Seeders\RecruitmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga RecruitmentSeeder (snapshot HR kanonik): dua kandidat contoh (Dedi Sandiaga di Daftar, Maya Denada di Tes dengan riwayat dan jadwal) bercatatan Contoh, dengan foto dan KTP tiruan DUMMY, idempoten, dan tidak mengembalikan yang sudah diganti.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class RecruitmentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_two_marked_candidates_once(): void
    {
        Storage::fake('ga');
        $this->seed(RecruitmentSeeder::class);
        $this->seed(RecruitmentSeeder::class);

        $this->assertSame(2, Candidate::count());
        $dedi = Candidate::with('events', 'departments')->firstWhere('name', 'Dedi Sandiaga');
        $maya = Candidate::with('events', 'departments')->firstWhere('name', 'Maya Denada');
        $this->assertSame(['Daftar', 'Tes'], [RecruitStep::find($dedi->step_id)->name, RecruitStep::find($maya->step_id)->name]);
        $this->assertSame(['pass', 'pass', 'waiting'], $maya->events->pluck('result')->all());
        $this->assertTrue($maya->events->last()->scheduled_on->isFuture());
        $this->assertSame([16, 16], [strlen($dedi->nik), strlen($maya->nik)]);
        $this->assertStringContainsString('Contoh', $maya->note);
        $this->assertSame(['Maintenance'], $dedi->departments->pluck('name')->all());
        foreach ([$dedi, $maya] as $c) {   // foto profil dan KTP tiruan bertanda DUMMY
            Storage::disk('ga')->assertExists([$c->photo_path, $c->ktp_path]);
            $this->assertStringContainsString('DUMMY', Storage::disk('ga')->get($c->ktp_path));
            $this->assertStringContainsString($c->nik, Storage::disk('ga')->get($c->ktp_path));
        }
    }

    public function test_rerunning_keeps_a_candidate_that_was_replaced(): void
    {
        Storage::fake('ga');
        $this->seed(RecruitmentSeeder::class);
        $maya = Candidate::firstWhere('name', 'Maya Denada');
        $maya->update(['name' => 'Maya Lain']);

        $this->seed(RecruitmentSeeder::class);
        $this->assertSame('Maya Lain', $maya->refresh()->name);
        $this->assertSame(2, Candidate::count());   // tidak dibuat ulang
    }
}
