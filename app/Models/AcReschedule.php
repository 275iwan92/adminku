<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Riwayat jadwal ulang satu pesanan: dari tanggal / jam mana ke tanggal / jam mana, alasannya, siapa yang memindahkan.
#[Fillable(['order_id', 'from_date', 'from_time', 'to_date', 'to_time', 'reason', 'created_by'])]
class AcReschedule extends Model
{
    protected $table = 'g_ac_service_reschedule';

    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
