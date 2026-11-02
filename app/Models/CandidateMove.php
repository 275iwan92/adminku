<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Log satu perpindahan kartu kandidat di papan: waktu persis, pemindah, tahap asal dan tujuan, dan jadwal yang diisi di pop-up (bila tahap tujuan berjadwal). Hanya ditambah, tidak diubah.
#[Fillable(['candidate_id', 'from_step_id', 'to_step_id', 'moved_by', 'scheduled_on', 'scheduled_time', 'assessor', 'no_result', 'moved_at'])]
class CandidateMove extends Model
{
    protected $table = 'h_recruit_move';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['scheduled_on' => 'date', 'moved_at' => 'datetime', 'no_result' => 'boolean'];
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(RecruitStep::class, 'from_step_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(RecruitStep::class, 'to_step_id');
    }

    public function mover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }
}
