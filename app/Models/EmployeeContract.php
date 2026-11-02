<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// Riwayat kontrak kerja karyawan (tanpa gaji); kontrak terbaru = baris dengan tanggal mulai terakhir.
#[Fillable(['employee_id', 'contract_no', 'employment', 'start_date', 'end_date', 'probation_end'])]
class EmployeeContract extends Model
{
    protected $table = 'm_employee_contract';

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'probation_end' => 'date'];
    }
}
