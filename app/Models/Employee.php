<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Karyawan (kepegawaian saja, tanpa gaji). Tidak dihapus: keluar = is_active false + alasan (exit_reason). work_status hanya untuk yang masih aktif.
#[Fillable(['code', 'name', 'nickname', 'gender', 'birth_place', 'birth_date', 'religion', 'marital', 'nik_ktp', 'npwp', 'phone', 'email', 'ktp_address', 'ktp_area_level', 'ktp_area_id', 'home_address', 'home_area_level', 'home_area_id', 'emergency_name', 'emergency_relation', 'emergency_phone', 'department_id', 'position', 'employment', 'join_date', 'work_status', 'user_id', 'is_active', 'exit_reason', 'exit_date', 'exit_note'])]
class Employee extends Model
{
    protected $table = 'm_employee';

    public const GENDERS = ['L' => 'Laki-laki', 'P' => 'Perempuan'];

    public const EMPLOYMENTS = ['tetap' => 'Tetap', 'kontrak' => 'Kontrak', 'harian' => 'Harian', 'magang' => 'Magang'];

    public const WORK_STATUSES = ['working' => 'Bekerja', 'leave' => 'Cuti', 'furlough' => 'Dirumahkan'];

    public const MARITALS = ['belum' => 'Belum kawin', 'kawin' => 'Kawin', 'cerai' => 'Cerai'];

    public const RELATIONS = ['suami' => 'Suami', 'istri' => 'Istri', 'anak' => 'Anak', 'orang_tua' => 'Orang tua'];

    public const DOC_TYPES = ['ktp' => 'KTP', 'kk' => 'KK', 'npwp' => 'NPWP', 'sim' => 'SIM', 'skck' => 'SKCK', 'paspor' => 'Paspor', 'ijazah' => 'Ijazah', 'bpjs_kes' => 'BPJS Kesehatan', 'bpjs_tk' => 'BPJS Ketenagakerjaan', 'lainnya' => 'Lainnya'];

    public const EXIT_REASONS = ['resigned' => 'Mengundurkan diri', 'contract_ended' => 'Kontrak berakhir', 'probation_failed' => 'Gagal masa percobaan', 'laid_off' => 'PHK', 'fired' => 'Dipecat', 'absconded' => 'Mangkir', 'medical' => 'Sakit', 'retired' => 'Pensiun', 'deceased' => 'Meninggal', 'transferred' => 'Mutasi'];

    public const CONTRACT_WARN_DAYS = 60;

    public const DOC_WARN_DAYS = 30;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'birth_date' => 'date', 'join_date' => 'date', 'exit_date' => 'date'];
    }

    // Kode bawaan form baru: E-0001, E-0002, ... (kode lain yang tidak berpola diabaikan).
    public static function nextCode(): string
    {
        $max = static::query()->where('code', 'like', 'E-%')->pluck('code')->map(fn ($c) => ctype_digit(substr($c, 2)) ? (int) substr($c, 2) : 0)->max() ?? 0;

        return sprintf('E-%04d', $max + 1);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function family(): HasMany
    {
        return $this->hasMany(EmployeeFamily::class)->orderBy('id');
    }

    public function docs(): HasMany
    {
        return $this->hasMany(EmployeeDoc::class)->orderBy('id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class)->orderByDesc('start_date');
    }

    // Kunci wilayah "LEVEL:id" untuk GeoArea / kotak cari.
    public function areaKey(string $which): ?string
    {
        $level = $this->{$which.'_area_level'};

        return $level && $this->{$which.'_area_id'} ? $level.':'.$this->{$which.'_area_id'} : null;
    }

    // Satu catatan perhatian (terburuk dulu): kontrak yang berjalan hampir habis, dokumen hampir habis, atau data pokok belum lengkap. Butuh relasi contracts dan docs sudah dimuat.
    public function attention(): ?string
    {
        if (! $this->is_active) {
            return null;
        }
        $left = fn ($date) => (int) today()->diffInDays($date, false);
        $words = fn (string $what, int $days) => $days < 0 ? $what.' lewat '.abs($days).' hari' : $what.' habis '.$days.' hari';
        $found = [];
        $contract = $this->contracts->first();
        if ($contract?->end_date && $left($contract->end_date) <= self::CONTRACT_WARN_DAYS) {
            $found[] = [$left($contract->end_date), $words('Kontrak', $left($contract->end_date))];
        }
        foreach ($this->docs as $doc) {
            if ($doc->expiry_date && $left($doc->expiry_date) <= self::DOC_WARN_DAYS) {
                $found[] = [$left($doc->expiry_date), $words(self::DOC_TYPES[$doc->doc_type] ?? $doc->doc_type, $left($doc->expiry_date))];
            }
        }
        if ($found) {
            usort($found, fn ($a, $b) => $a[0] <=> $b[0]);

            return $found[0][1];
        }

        return ! $this->nik_ktp || ! $this->ktp_area_id || ! $this->phone ? 'Data belum lengkap' : null;
    }
}
