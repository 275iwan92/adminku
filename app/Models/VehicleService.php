<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Satu kali servis kendaraan: tanggal, odometer, bengkel, biaya total, dan pekerjaan yang dikerjakan (g_vehicle_service_line). Jadwal berikutnya dihitung dari sini (odometer + interval km, tanggal + interval bulan).
#[Fillable(['vehicle_id', 'serviced_at', 'odometer_km', 'workshop', 'cost', 'note', 'created_by'])]
class VehicleService extends Model
{
    protected $table = 'g_vehicle_service';

    protected function casts(): array
    {
        return ['serviced_at' => 'date', 'odometer_km' => 'integer', 'cost' => 'decimal:2'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(VehicleServiceLine::class, 'vehicle_service_id')->orderBy('id');
    }
}
