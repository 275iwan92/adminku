<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Lokasi rak / penyimpanan. Batch menyimpan kodenya di i_batch.location; kode tidak diubah setelah disimpan.
#[Fillable(['code', 'name', 'type', 'is_active'])]
class Location extends Model
{
    protected $table = 'm_location';

    // Jenis tempat simpan; kosong = belum ditentukan. Menentukan kelompok kartu di daftar lokasi.
    public const TYPES = ['lemari' => 'Lemari', 'gudang_tertutup' => 'Gudang tertutup', 'gudang_terbuka' => 'Gudang terbuka', 'area_luas' => 'Area luas'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'location', 'code');
    }

    // Teks pilihan: "A1-01" atau "A1-01 · Rak A1 level 1".
    public function label(): string
    {
        return $this->code === $this->name ? $this->code : "{$this->code} · {$this->name}";
    }
}
