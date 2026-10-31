<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// Satu baris pengaturan aplikasi (key unik); dibaca / ditulis lewat App\Support\Constants.
#[Fillable(['key', 'value', 'group'])]
class Constant extends Model
{
    protected $table = 'sys_constant';
}
