<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// 1 {from_unit} = {factor} {to_unit}, sama untuk semua item.
#[Fillable(['from_unit', 'to_unit', 'factor', 'created_by', 'updated_by'])]
class UnitConversion extends Model
{
    protected $table = 'm_unit_conversion';

    protected function casts(): array
    {
        return ['factor' => 'float'];
    }
}
