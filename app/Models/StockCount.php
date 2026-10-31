<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['doc_no', 'count_date', 'scope_type', 'scope_value', 'status', 'blind', 'lock_transactions', 'threshold_pct', 'cutoff_at', 'created_by', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'adjustment_document_id', 'unlocked_by', 'unlocked_at', 'locked_by', 'locked_at', 'cancelled_by', 'cancelled_at', 'review_note', 'note'])]
class StockCount extends Model
{
    protected $table = 'i_count';

    public const STATUS = ['counting' => 'Sedang dihitung', 'review' => 'Tinjau', 'done' => 'Selesai', 'cancelled' => 'Dibatalkan'];

    public const SCOPE = ['all' => 'Semua barang', 'category' => 'Per Kategori', 'location' => 'Per Lokasi'];

    protected function casts(): array
    {
        return [
            'count_date' => 'date',
            'blind' => 'boolean',
            'lock_transactions' => 'boolean',
            'threshold_pct' => 'float',
            'cutoff_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'unlocked_at' => 'datetime',
            'locked_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function unlocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unlocked_by');
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function adjustmentDocument(): BelongsTo
    {
        return $this->belongsTo(StockDocument::class, 'adjustment_document_id');
    }

    // Opname yang masih berjalan (Sedang dihitung / Tinjau).
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['counting', 'review']);
    }

    // Opname yang kini mengunci transaksi stok (berjalan DAN kunci belum dibuka); null = transaksi bebas.
    public static function lockingNow(): ?self
    {
        return self::query()->active()->where('lock_transactions', true)->first();
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    // Cakupan dalam kata: "Semua barang", "Kategori ATK · Alat Tulis", "Lokasi RAK-A · Rak A".
    public function scopeLabel(): string
    {
        return match ($this->scope_type) {
            'category' => 'Kategori '.$this->scope_value.' · '.(Category::query()->where('code', $this->scope_value)->value('name') ?? '-'),
            'location' => 'Lokasi '.$this->scope_value.' · '.(Location::query()->where('code', $this->scope_value)->value('name') ?? '-'),
            default => 'Semua barang',
        };
    }
}
