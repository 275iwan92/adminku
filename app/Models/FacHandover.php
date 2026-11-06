<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Serah Terima Aset: loan (pinjam / serah), home (bawa pulang), return (pengembalian), lost (laporan hilang). Siklus draft > approval (Confirm) > approved (Supervisor) | rejected; cancelled.
#[Fillable(['no', 'type', 'asset_id', 'employee_id', 'department_id', 'doc_date', 'expected_return', 'purpose', 'condition_out', 'damage_note', 'status', 'photo_path', 'photo_name', 'created_by', 'submitted_at', 'approved_by', 'approved_at', 'reason'])]
class FacHandover extends Model
{
    protected $table = 'g_fac_handover';

    public const TYPES = ['loan' => 'Pinjam / serah', 'home' => 'Bawa pulang', 'return' => 'Pengembalian', 'lost' => 'Laporan hilang'];

    public const OPEN = ['draft', 'approval'];

    protected function casts(): array
    {
        return ['doc_date' => 'date', 'expected_return' => 'date', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FacAsset::class, 'asset_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
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

    public function holderText(): string
    {
        return $this->employee?->name ?? $this->department?->name ?? '-';
    }

    // ST-YYMM-0001, urut per bulan. Dipanggil di dalam transaksi Confirm.
    public static function nextNo(\DateTimeInterface $date): string
    {
        $prefix = 'ST-'.$date->format('ym').'-';
        $max = static::query()->where('no', 'like', $prefix.'%')->lockForUpdate()->pluck('no')->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;

        return $prefix.sprintf('%04d', $max + 1);
    }
}
