<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Gedung (parent_id kosong) atau ruang / area di dalam gedung (parent_id terisi). Dua tingkat saja.
#[Fillable(['parent_id', 'code', 'name', 'pic_name', 'note', 'is_active'])]
class FacPlace extends Model
{
    protected $table = 'm_fac_place';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(FacAsset::class, 'place_id');
    }

    // "Gedung A › Ruang Server" atau "Gedung A".
    public function path(): string
    {
        return $this->parent_id ? $this->parent->name.' › '.$this->name : $this->name;
    }
}
