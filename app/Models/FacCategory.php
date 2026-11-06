<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Kategori aset (Bangunan, Listrik, Plumbing, Mebel, Elektronik, Kendaraan ringan, ...). icon = kelas Font Awesome (mis. fa-bolt), dipakai bersama fa-solid.
#[Fillable(['code', 'name', 'icon', 'is_active'])]
class FacCategory extends Model
{
    protected $table = 'm_fac_category';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(FacAsset::class, 'category_id');
    }
}
