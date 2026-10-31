<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['stock_document_id', 'item_id', 'qty', 'unit_cost', 'system_qty', 'counted_qty', 'note', 'entered_qty', 'entered_unit', 'entered_unit_cost'])]
class StockDocumentLine extends Model
{
    protected $table = 'rm_stock_line';

    protected function casts(): array
    {
        return ['qty' => 'float', 'unit_cost' => 'float', 'system_qty' => 'float', 'counted_qty' => 'float', 'entered_qty' => 'float', 'entered_unit_cost' => 'float'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(StockDocument::class, 'stock_document_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
