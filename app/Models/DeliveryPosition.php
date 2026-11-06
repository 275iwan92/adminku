<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/* REMARK
 * Tujuan       : Satu titik GPS sopir selama trip berjalan (dikirim layar sopir tiap ~20 detik). Riwayat singkat, dihapus setelah 7 hari.
 * Sumber hulu  : delivery_position.
 * Dipakai oleh : DriverTripController (simpan), DeliveryTripController (posisi terakhir), delivery:prune-positions.
 * Status       : Aktif.
 */
#[Fillable(['trip_id', 'lat', 'lng', 'accuracy', 'recorded_at'])]
class DeliveryPosition extends Model
{
    public $timestamps = false;

    protected $table = 'delivery_position';

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime', 'lat' => 'float', 'lng' => 'float'];
    }
}
