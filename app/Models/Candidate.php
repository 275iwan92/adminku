<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Kandidat / pelamar. Kunci = NIK KTP (16 angka, wajib); melamar lagi = baris baru dengan NIK sama (diberi peringatan). step_id = tahap sekarang; riwayat tahap ada di events.
#[Fillable(['nik', 'name', 'phone', 'email', 'birth_date', 'education', 'position', 'source', 'applied_at', 'step_id', 'status', 'ktp_path', 'ktp_name', 'cv_path', 'cv_name', 'photo_path', 'photo_name', 'note', 'created_by'])]
class Candidate extends Model
{
    protected $table = 'h_recruit_candidate';

    public const STATUSES = ['active' => 'Aktif', 'rejected' => 'Ditolak', 'withdrawn' => 'Mundur'];

    public const SOURCES = ['walkin' => 'Walk-in', 'referral' => 'Referral', 'online' => 'Situs lowongan', 'other' => 'Lainnya'];

    public const EDUCATIONS = ['SD' => 'SD', 'SMP' => 'SMP', 'SMA/SMK' => 'SMA/SMK', 'D3' => 'D3', 'S1' => 'S1', 'S2' => 'S2'];

    public const RESULTS = ['waiting' => 'Menunggu', 'pass' => 'Lulus', 'fail' => 'Tidak lulus'];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'applied_at' => 'date'];
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(RecruitStep::class, 'step_id');
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'h_recruit_candidate_dept', 'candidate_id', 'department_id')->orderBy('name');
    }

    public function moves(): HasMany
    {
        return $this->hasMany(CandidateMove::class)->orderByDesc('moved_at')->orderByDesc('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CandidateEvent::class)->orderBy('id');
    }

    // Peristiwa terakhir (hasil terakhir) memakai relasi events yang sudah dimuat.
    public function lastEvent(): ?CandidateEvent
    {
        return $this->events->last();
    }
}
