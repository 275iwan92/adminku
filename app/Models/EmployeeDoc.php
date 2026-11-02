<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// Dokumen karyawan (KTP, KK, NPWP, SIM, BPJS, dst.): nomor dan masa berlaku; berkas pindaian belum disimpan.
#[Fillable(['employee_id', 'doc_type', 'doc_no', 'expiry_date', 'note'])]
class EmployeeDoc extends Model
{
    protected $table = 'm_employee_doc';

    protected function casts(): array
    {
        return ['expiry_date' => 'date'];
    }
}
