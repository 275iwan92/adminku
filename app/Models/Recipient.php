<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// department_id = departemen bawaan penerima; terisi otomatis di Issuance bila departemen tidak dipilih.
#[Fillable(['name', 'department_id', 'employee_id', 'is_active'])]
class Recipient extends Model
{
    protected $table = 'm_recipient';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StockDocument::class);
    }
}
