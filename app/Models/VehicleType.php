<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Jenis kendaraan (mobil penumpang, pickup, boks, ...) beserta rencana perawatannya (m_vehicle_type_plan). Kode tidak diubah setelah disimpan; jenis tidak dihapus, hanya dinonaktifkan.
#[Fillable(['code', 'name', 'is_active'])]
class VehicleType extends Model
{
    protected $table = 'm_vehicle_type';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function plans(): HasMany
    {
        return $this->hasMany(VehicleTypePlan::class, 'vehicle_type_id')->orderBy('sort')->orderBy('id');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'vehicle_type_id');
    }
}
