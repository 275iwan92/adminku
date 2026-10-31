<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['batch_id', 'stock_document_id', 'type', 'moved_at', 'qty', 'unit_cost', 'balance_qty', 'balance_avg_cost', 'user_id', 'reversal_of'])]
class StockMovement extends Model
{
    protected $table = 'rm_stockcard';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['moved_at' => 'datetime', 'qty' => 'float', 'unit_cost' => 'float', 'balance_qty' => 'float', 'balance_avg_cost' => 'float'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(StockDocument::class, 'stock_document_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
