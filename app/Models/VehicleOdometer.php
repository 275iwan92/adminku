<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Satu catatan odometer. source: awal (saat kendaraan didaftarkan, dasar jadwal bagi butir yang belum pernah diservis), manual (Catat Odometer), servis (ikut Catat Servis). Foto bukti opsional, privat di disk "ga".
#[Fillable(['vehicle_id', 'read_at', 'km', 'source', 'recorded_by', 'photo_path', 'photo_name', 'created_by'])]
class VehicleOdometer extends Model
{
    protected $table = 'g_vehicle_odometer';

    protected function casts(): array
    {
        return ['read_at' => 'date', 'km' => 'integer'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
