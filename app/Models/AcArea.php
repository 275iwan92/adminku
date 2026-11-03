<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Area / gedung / lantai AC. Denah: gambar unggahan (plan_path, disk "ga") atau kosong (layar Denah AC sudah dihapus; gambar dan posisi titik tetap tersimpan). plan_w : plan_h = perbandingan lebar-tinggi denah.
#[Fillable(['code', 'name', 'plan_path', 'plan_name', 'plan_w', 'plan_h', 'is_active'])]
class AcArea extends Model
{
    protected $table = 'm_ac_area';

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'plan_w' => 'integer', 'plan_h' => 'integer'];
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(AcRoom::class, 'area_id');
    }
}
