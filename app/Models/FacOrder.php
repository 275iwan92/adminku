<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Perintah Kerja (tiket) perawatan. Siklus: draft > approval (Confirm, nomor lahir) > approved (Supervisor, = Selesai) | rejected; cancelled dari approval.
#[Fillable(['no', 'asset_id', 'place_id', 'plan_id', 'title', 'description', 'priority', 'status', 'reported_at', 'handler', 'vendor_id', 'technician', 'labor_cost', 'material_cost', 'finished_at', 'note',
    'photo_before_path', 'photo_before_name', 'photo_after_path', 'photo_after_name', 'created_by', 'submitted_at', 'approved_by', 'approved_at', 'reason'])]
class FacOrder extends Model
{
    protected $table = 'g_fac_order';

    public const OPEN = ['draft', 'approval'];

    protected function casts(): array
    {
        return ['reported_at' => 'date', 'finished_at' => 'date', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'labor_cost' => 'decimal:2', 'material_cost' => 'decimal:2'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FacAsset::class, 'asset_id');
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(FacPlace::class, 'place_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(FacPlan::class, 'plan_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(AcVendor::class, 'vendor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function number(): string
    {
        return $this->no ?? 'Draf #'.$this->id;
    }

    public function total(): float
    {
        return (float) $this->labor_cost + (float) $this->material_cost;
    }

    public function targetText(): string
    {
        return $this->asset ? $this->asset->asset_code.' · '.$this->asset->name : ($this->place?->path() ?? '-');
    }

    // WO-YYMM-0001, urut per bulan. Dipanggil di dalam transaksi Confirm.
    public static function nextNo(\DateTimeInterface $date): string
    {
        $prefix = 'WO-'.$date->format('ym').'-';
        $max = static::query()->where('no', 'like', $prefix.'%')->lockForUpdate()->pluck('no')->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;

        return $prefix.sprintf('%04d', $max + 1);
    }
}
