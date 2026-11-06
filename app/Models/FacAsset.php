<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Satu aset perusahaan. asset_code permanen (AST-0001); dinonaktifkan, tidak dihapus.
#[Fillable(['asset_code', 'name', 'category_id', 'place_id', 'bought_at', 'price', 'warranty_until', 'cond', 'pic_name', 'holder_employee_id', 'holder_department_id', 'position', 'due_back', 'monitored', 'photo_path', 'photo_name', 'note', 'is_active'])]
class FacAsset extends Model
{
    protected $table = 'm_fac_asset';

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'bought_at' => 'date', 'warranty_until' => 'date', 'due_back' => 'date', 'monitored' => 'boolean', 'price' => 'decimal:2'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FacCategory::class, 'category_id');
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(FacPlace::class, 'place_id');
    }

    public function holderEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'holder_employee_id');
    }

    public function holderDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'holder_department_id');
    }

    public function handovers(): HasMany
    {
        return $this->hasMany(FacHandover::class, 'asset_id');
    }

    // "Budi (E-0001)" / "Departemen X" / null bila ada di kantor tanpa pemegang.
    public function holderText(): ?string
    {
        return $this->holderEmployee?->name ?? $this->holderDepartment?->name;
    }

    // Di luar kantor dan lewat tanggal wajib kembali.
    public function overdue(): bool
    {
        return in_array($this->position, ['loan', 'home'], true) && $this->due_back !== null && $this->due_back->lt(today());
    }

    public function orders(): HasMany
    {
        return $this->hasMany(FacOrder::class, 'asset_id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(FacPlan::class, 'asset_id');
    }

    // Kode berikutnya AST-0001, AST-0002, ...
    public static function nextCode(): string
    {
        $max = static::query()->where('asset_code', 'like', 'AST-%')->pluck('asset_code')->map(fn ($c) => ctype_digit(substr($c, 4)) ? (int) substr($c, 4) : 0)->max() ?? 0;

        return sprintf('AST-%04d', $max + 1);
    }

    public function inWarranty(): bool
    {
        return $this->warranty_until !== null && $this->warranty_until->gte(today());
    }
}
