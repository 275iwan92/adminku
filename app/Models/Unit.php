<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Satuan barang. Batch menyimpan kode satuan di i_batch.unit (foreign key ke m_unit.code); kode tidak diubah setelah disimpan.
#[Fillable(['code', 'name', 'kind', 'is_active'])]
class Unit extends Model
{
    protected $table = 'm_unit';

    // Jenis satuan; menentukan warna kotak di grid (App\Support\Palette).
    public const KINDS = ['count' => 'Hitungan (pcs, box, set ...)', 'weight' => 'Berat (kg, gram ...)', 'length' => 'Panjang (meter ...)', 'volume' => 'Volume (liter ...)', 'area' => 'Luas (m2 ...)', 'other' => 'Lainnya'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'unit', 'code');
    }

    // Konversi keluar dari satuan ini: 1 {code} = factor {to_unit}.
    public function conversions(): HasMany
    {
        return $this->hasMany(UnitConversion::class, 'from_unit', 'code')->orderBy('to_unit');
    }
}
