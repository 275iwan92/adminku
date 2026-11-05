<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/* REMARK
 * Tujuan       : Trip Pengiriman: satu kendaraan + satu crew, urutan stop antar / ambil. Nomor PGK-YYMM-0001 lahir saat Tugaskan. Status draft -> assigned | cancelled.
 *                Waktu layanan stop = (5 menit + 10 menit per 100 kg) dikali 0,7 bila ada asisten; ETA dihitung di layar planner (rumus yang sama di resources/views/delivery-trips/planner.blade.php).
 * Sumber hulu  : delivery_trip, delivery_stop, m_vehicle, users.
 * Dipakai oleh : DeliveryTripController, DeliveryNoteController.
 * Status       : Aktif.
 */
#[Fillable(['doc_no', 'trip_date', 'vehicle_id', 'driver', 'assistant', 'depart_time', 'return_min', 'note', 'status', 'created_by', 'assigned_by', 'assigned_at', 'reason'])]
class DeliveryTrip extends Model
{
    protected $table = 'delivery_trip';

    public const STATUS = ['draft' => 'Draf', 'assigned' => 'Ditugaskan', 'cancelled' => 'Dibatalkan'];

    protected function casts(): array
    {
        return ['trip_date' => 'date', 'assigned_at' => 'datetime'];
    }

    public function stops(): HasMany
    {
        return $this->hasMany(DeliveryStop::class, 'trip_id')->orderBy('seq');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function number(): string
    {
        return $this->doc_no ?? 'Draf #'.$this->id;
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    // PGK-YYMM-0001, urut per bulan tanggal trip. Dipanggil di dalam transaksi.
    public static function nextNo(\DateTimeInterface $date): string
    {
        $prefix = 'PGK-'.$date->format('ym').'-';
        $max = static::query()->where('doc_no', 'like', $prefix.'%')->lockForUpdate()->pluck('doc_no')->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;

        return $prefix.sprintf('%04d', $max + 1);
    }
}
