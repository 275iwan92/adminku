<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Jadwal perawatan berkala untuk satu aset ATAU satu gedung / ruang. Jatuh tempo dihitung FacCare::overview dari last_done_at (atau start_at).
#[Fillable(['asset_id', 'place_id', 'title', 'period', 'start_at', 'last_done_at', 'vendor_id', 'note', 'is_active'])]
class FacPlan extends Model
{
    protected $table = 'g_fac_plan';

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'start_at' => 'date', 'last_done_at' => 'date'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FacAsset::class, 'asset_id');
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(FacPlace::class, 'place_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(AcVendor::class, 'vendor_id');
    }

    public function targetText(): string
    {
        return $this->asset ? $this->asset->asset_code.' · '.$this->asset->name : ($this->place?->path() ?? '-');
    }
}
