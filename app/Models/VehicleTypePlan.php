<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// Satu butir rencana perawatan sebuah jenis kendaraan: pekerjaan dengan interval km dan/atau bulan (mana yang tercapai lebih dulu).
#[Fillable(['vehicle_type_id', 'name', 'interval_km', 'interval_months', 'sort'])]
class VehicleTypePlan extends Model
{
    protected $table = 'm_vehicle_type_plan';

    protected function casts(): array
    {
        return ['interval_km' => 'integer', 'interval_months' => 'integer', 'sort' => 'integer'];
    }

    // Teks interval untuk layar: "5.000 km / 6 bln".
    public function intervalLabel(): string
    {
        return collect([
            $this->interval_km ? number_format($this->interval_km, 0, ',', '.').' km' : null,
            $this->interval_months ? $this->interval_months.' bln' : null,
        ])->filter()->implode(' / ');
    }
}
