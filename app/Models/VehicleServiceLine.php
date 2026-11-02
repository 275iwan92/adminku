<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Satu pekerjaan dalam sebuah servis. vehicle_type_plan_id menghubungkannya ke butir rencana (jadwal); name adalah salinan nama saat dikerjakan.
#[Fillable(['vehicle_service_id', 'vehicle_type_plan_id', 'name'])]
class VehicleServiceLine extends Model
{
    protected $table = 'g_vehicle_service_line';

    public $timestamps = false;

    public function service(): BelongsTo
    {
        return $this->belongsTo(VehicleService::class, 'vehicle_service_id');
    }
}
