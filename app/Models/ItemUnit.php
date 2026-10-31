<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 1 {unit} = {factor} satuan stok barang ini (m_item.unit). Faktor disimpan sebagai string desimal (6 digit) supaya perhitungan tetap presisi.
#[Fillable(['item_id', 'unit', 'factor', 'created_by', 'updated_by'])]
class ItemUnit extends Model
{
    protected $table = 'm_item_unit';

    protected function casts(): array
    {
        return ['factor' => 'decimal:6'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unitMaster(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit', 'code');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
