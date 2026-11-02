<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Ruang di dalam area. x, y, w, h = persegi ruang di denah (persen). pic_* = penanggung jawab ruang yang diberi tahu kapan vendor datang.
#[Fillable(['area_id', 'code', 'name', 'x', 'y', 'w', 'h', 'pic_name', 'pic_phone', 'pic_email', 'is_active'])]
class AcRoom extends Model
{
    protected $table = 'm_ac_room';

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'x' => 'float', 'y' => 'float', 'w' => 'float', 'h' => 'float'];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(AcArea::class, 'area_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(AcUnit::class, 'room_id');
    }
}
