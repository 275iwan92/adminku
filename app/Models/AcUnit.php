<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Satu unit AC. asset_code = kode aset permanen (AC-0004); kode ruang ada di ruangnya dan berubah bila AC dipindah. pos_x / pos_y = titik di denah (persen).
#[Fillable(['asset_code', 'room_id', 'brand', 'model', 'capacity_pk', 'installed_at', 'pos_x', 'pos_y', 'last_wash_at', 'last_major_at', 'note', 'is_active'])]
class AcUnit extends Model
{
    protected $table = 'm_ac_unit';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean', 'capacity_pk' => 'float', 'pos_x' => 'float', 'pos_y' => 'float',
            'installed_at' => 'date', 'last_wash_at' => 'date', 'last_major_at' => 'date',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(AcRoom::class, 'room_id');
    }

    // Kode berikutnya AC-0001, AC-0002, ...
    public static function nextCode(): string
    {
        $max = static::query()->where('asset_code', 'like', 'AC-%')->pluck('asset_code')->map(fn ($c) => ctype_digit(substr($c, 3)) ? (int) substr($c, 3) : 0)->max() ?? 0;

        return sprintf('AC-%04d', $max + 1);
    }

    // Teks tampil di stiker / layar: "MTG · AC-0004" (kode ruang · kode aset).
    public function label(): string
    {
        return $this->room->code.' · '.$this->asset_code;
    }

    // "Daikin FTV25 1 PK".
    public function typeText(): string
    {
        return trim($this->brand.' '.$this->model).' '.rtrim(rtrim(number_format($this->capacity_pk, 1, ',', '.'), '0'), ',').' PK';
    }
}
