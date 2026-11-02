<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Satu pesanan kunjungan vendor AC. Status: draft > sent > confirmed > done; rescheduled (tanggal dipindah, perlu dikirim ulang) dan cancelled. OPEN = status yang masih memegang AC-nya dan kapasitas harinya (selain done / cancelled).
#[Fillable(['no', 'vendor_id', 'visit_date', 'visit_time', 'status', 'type', 'complaint', 'priority', 'override_reason', 'note', 'reschedule_reason', 'cancel_reason', 'sent_at', 'sent_by', 'confirmed_at', 'confirmed_by', 'done_at', 'done_by', 'cancelled_at', 'cancelled_by', 'created_by'])]
class AcOrder extends Model
{
    protected $table = 'g_ac_service_order';

    public const OPEN = ['draft', 'sent', 'confirmed', 'rescheduled'];

    // Tipe: preventive = jadwal rutin (cuci / servis besar), breakdown = perbaikan AC rusak.
    public const TYPES = ['preventive' => 'Preventif', 'breakdown' => 'Breakdown'];

    public const PRIORITIES = ['urgent' => 'Mendesak', 'normal' => 'Biasa'];

    protected function casts(): array
    {
        return ['visit_date' => 'date', 'sent_at' => 'datetime', 'confirmed_at' => 'datetime', 'done_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(AcVendor::class, 'vendor_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AcOrderLine::class, 'order_id');
    }

    public function reschedules(): HasMany
    {
        return $this->hasMany(AcReschedule::class, 'order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function finisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isBreakdown(): bool
    {
        return $this->type === 'breakdown';
    }

    public function typeText(): string
    {
        return self::TYPES[$this->type ?? 'preventive'] ?? (string) $this->type;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    // Jam mulai "09:00" (kolom time di MySQL bisa berbentuk 09:00:00).
    public function timeText(): string
    {
        return substr((string) $this->visit_time, 0, 5);
    }
}
