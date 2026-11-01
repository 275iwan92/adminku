<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['code', 'name', 'is_active'])]
class Department extends Model
{
    protected $table = 'm_department';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    // Kode bawaan form baru: DEP-001, DEP-002, ... (kode lain yang tidak berpola diabaikan).
    public static function nextCode(): string
    {
        $max = static::query()->where('code', 'like', 'DEP-%')->pluck('code')->map(fn ($c) => ctype_digit(substr($c, 4)) ? (int) substr($c, 4) : 0)->max() ?? 0;

        return sprintf('DEP-%03d', $max + 1);
    }

    // Kode otomatis dari nama (tambah cepat dari form Issuance): huruf besar tanpa spasi, angka di belakang bila kembar.
    public static function codeFor(string $name): string
    {
        $base = strtoupper(Str::limit(Str::slug($name, ''), 12, '')) ?: 'DEP';
        $code = $base;
        for ($n = 2; static::query()->where('code', $code)->exists(); $n++) {
            $code = $base.$n;
        }

        return $code;
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(Recipient::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StockDocument::class);
    }
}
