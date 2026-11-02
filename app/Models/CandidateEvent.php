<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Satu baris riwayat tahap kandidat: tahap, jadwal (tanggal + jam), penilai, hasil, catatan. Ditambah otomatis saat kandidat dipindah tahap.
#[Fillable(['candidate_id', 'step_id', 'scheduled_on', 'scheduled_time', 'assessor', 'result', 'note', 'created_by'])]
class CandidateEvent extends Model
{
    protected $table = 'h_recruit_event';

    protected function casts(): array
    {
        return ['scheduled_on' => 'date'];
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(RecruitStep::class, 'step_id');
    }
}
