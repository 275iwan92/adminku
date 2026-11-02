<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Satu AC dalam pesanan kunjungan. job: wash = cuci rutin, major = servis besar (sudah mencakup cuci). done_at terisi saat kunjungan selesai. Foto bukti sebelum / sesudah privat di disk "ga".
#[Fillable(['order_id', 'unit_id', 'job', 'done_at', 'photo_before_path', 'photo_before_name', 'photo_after_path', 'photo_after_name', 'note'])]
class AcOrderLine extends Model
{
    protected $table = 'g_ac_service_line';

    protected function casts(): array
    {
        return ['done_at' => 'date'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(AcOrder::class, 'order_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(AcUnit::class, 'unit_id');
    }
}
