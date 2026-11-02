<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// Anggota keluarga karyawan (suami / istri / anak / orang tua).
#[Fillable(['employee_id', 'relation', 'name', 'birth_date', 'nik', 'is_deceased'])]
class EmployeeFamily extends Model
{
    protected $table = 'm_employee_family';

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'is_deceased' => 'boolean'];
    }
}
