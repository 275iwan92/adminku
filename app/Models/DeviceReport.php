<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/* REMARK
 * Tujuan       : Satu laporan pemeriksaan lengkap perangkat (More details) dari agen Python: kolom ringkas + payload JSON lengkap. Tiap laporan baru = baris baru (riwayat).
 * Dipakai oleh : DeviceController (details, agent), AgentReportController (terima laporan).
 * Status       : Aktif.
 */
class DeviceReport extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'checked_at' => 'datetime'];
    }
}
