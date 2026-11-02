<?php

namespace App\Models;

use App\Services\VehicleCare;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Satu perpanjangan dokumen kendaraan (pajak tahunan, STNK 5 tahunan, KIR, asuransi). Masa berlaku sekarang = berlaku-sampai terbesar per jenis; baris lama tetap sebagai riwayat. Lampiran privat di disk "ga".
#[Fillable(['vehicle_id', 'doc_type', 'doc_no', 'paid_at', 'valid_until', 'cost', 'attachment_path', 'attachment_name', 'note', 'created_by'])]
class VehicleDoc extends Model
{
    protected $table = 'g_vehicle_doc';

    protected function casts(): array
    {
        return ['paid_at' => 'date', 'valid_until' => 'date', 'cost' => 'decimal:2'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function typeLabel(): string
    {
        return VehicleCare::DOCS[$this->doc_type][0] ?? $this->doc_type;
    }
}
