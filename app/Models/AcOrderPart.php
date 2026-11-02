<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// Satu spare-part yang diganti pada satu AC dalam kunjungan: nama bebas, jumlah, biaya opsional (total, bukan satuan). Tidak terhubung ke stok gudang.
#[Fillable(['line_id', 'name', 'qty', 'cost'])]
class AcOrderPart extends Model
{
    protected $table = 'g_ac_service_part';

    protected function casts(): array
    {
        return ['qty' => 'float', 'cost' => 'float'];
    }
}
