<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Tahap rekrutmen (kolom papan). start (Daftar) selalu pertama dan end (Diterima) selalu terakhir; tahap tengah bebas ditambah, diubah, dan diurutkan lewat sort.
#[Fillable(['name', 'sort', 'kind', 'needs_schedule', 'tone', 'wip_limit', 'is_active'])]
class RecruitStep extends Model
{
    protected $table = 'm_recruit_step';

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'needs_schedule' => 'boolean', 'sort' => 'integer', 'wip_limit' => 'integer'];
    }

    public const TONES = ['neutral' => 'Abu-abu', 'accent' => 'Biru', 'violet' => 'Ungu', 'warn' => 'Kuning', 'success' => 'Hijau', 'danger' => 'Merah'];

    public function scopeOrdered($query)
    {
        return $query->orderByRaw("case kind when 'start' then 0 when 'end' then 2 else 1 end")->orderBy('sort')->orderBy('id');
    }

    public function isFixed(): bool
    {
        return $this->kind !== 'mid';
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class, 'step_id');
    }
}
