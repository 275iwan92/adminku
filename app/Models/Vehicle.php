<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Kendaraan perusahaan (mobil, small truck). Kode = plat nomor. odometer_km = odometer terakhir yang tercatat (hanya naik, lihat VehicleCare::recordOdometer).
#[Fillable(['vehicle_type_id', 'plate', 'name', 'year', 'vin', 'engine_no', 'kir_required', 'odometer_km', 'odometer_at', 'is_active', 'note'])]
class Vehicle extends Model
{
    protected $table = 'm_vehicle';

    protected function casts(): array
    {
        return ['kir_required' => 'boolean', 'is_active' => 'boolean', 'odometer_km' => 'integer', 'year' => 'integer', 'odometer_at' => 'date'];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(VehicleService::class);
    }

    // Teks pilihan: "N 1234 AB · Daihatsu Gran Max".
    public function label(): string
    {
        return "{$this->plate} · {$this->name}";
    }
}
