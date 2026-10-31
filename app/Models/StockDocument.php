<?php

namespace App\Models;

use App\Enums\StockDocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['doc_no', 'type', 'doc_at', 'status', 'supplier_id', 'ref_no', 'recipient', 'recipient_id', 'department', 'department_id', 'reason', 'note', 'created_by', 'approved_by', 'approved_at', 'confirmed_by', 'confirmed_at', 'cancelled_by', 'cancelled_at', 'cancel_reason', 'cancel_requested_by', 'cancel_requested_at', 'reversal_of_document_id'])]
class StockDocument extends Model
{
    protected $table = 'rm_stock';

    protected function casts(): array
    {
        return [
            'type' => StockDocumentType::class,
            'doc_at' => 'datetime',
            'approved_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancel_requested_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockDocumentLine::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function cancelRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancel_requested_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
