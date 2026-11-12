<?php

namespace App\Models;

use App\Enums\StockDocumentType;
use App\Services\LeadTimes;
use Devlib2\Engine\LeadTime;
use Devlib2\Locale;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['item_id', 'ordered_at', 'ref_no', 'created_by', 'closed_at', 'receiving_id', 'supplier_id', 'qty', 'note', 'received_qty', 'first_received_at', 'close_reason'])]
class ReorderOrder extends Model
{
    protected $table = 'i_reorder_order';

    protected function casts(): array
    {
        return ['ordered_at' => 'date', 'closed_at' => 'datetime', 'qty' => 'float', 'received_qty' => 'float', 'first_received_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Penerimaan sejak tanggal pesan, dalam satuan stok, semua batch produk: pergerakan masuk dari dokumen Penerimaan yang terkonfirmasi dan tidak dibatalkan.
     * Hanya untuk pesanan lama tanpa jumlah (qty kosong); pesanan ber-jumlah memakai received_qty hasil ReorderOrders::reconcile (alokasi FIFO antar pesanan).
     */
    public function receivedSince(): float
    {
        return round((float) StockMovement::query()
            ->join('i_batch', 'i_batch.id', '=', 'rm_stockcard.batch_id')
            ->join('rm_stock', 'rm_stock.id', '=', 'rm_stockcard.stock_document_id')
            ->where('i_batch.item_id', $this->item_id)->where('rm_stockcard.type', StockDocumentType::Receiving->value)->whereNull('rm_stockcard.reversal_of')
            ->whereIn('rm_stock.status', ['confirmed', 'cancel_pending'])->where('rm_stockcard.moved_at', '>=', $this->ordered_at->copy()->startOfDay())
            ->sum('rm_stockcard.qty'), 3);
    }

    /** ordered (belum ada yang diterima) | partial (diterima sebagian, masih terbuka) | done (selesai diterima) | manual (ditutup manual). */
    public function status(): string
    {
        return match (true) {
            $this->closed_at === null && ! $this->first_received_at => 'ordered',
            $this->closed_at === null => 'partial',
            $this->close_reason === 'manual' => 'manual',
            default => 'done',
        };
    }

    public function statusLabel(): string
    {
        return ['ordered' => 'Dipesan', 'partial' => 'Diterima sebagian', 'done' => 'Selesai', 'manual' => 'Ditutup manual'][$this->status()];
    }

    // Keterangan untuk peringatan stok menipis (beranda, laporan Ringkasan Stok, form barang). Pesanan yang masih terbuka ditambah perkiraan tiba (engine LeadTime) bila ada angkanya; $leadTime dari LeadTimes::forMany menghindari query per baris.
    public function note(?LeadTime $leadTime = null): string
    {
        $this->loadMissing('item', 'creator', 'supplier');
        $unit = $this->item->unit;
        $qty = $this->attributes['qty'] ?? null;   // model yang baru dibuat belum membawa kolom yang kosong
        $note = $this->attributes['note'] ?? null;

        // Baris lama tidak punya jumlah / supplier: bagian itu dilewati.
        return ($qty !== null || $this->supplier ? 'Sudah dipesan' : 'Sudah dicatat dipesan').($qty !== null ? ' '.Locale::decimal($qty, 3)." {$unit}" : '').($this->supplier ? " ke {$this->supplier->name}" : '')
            .' pada '.$this->ordered_at->format('d/m/Y').($this->ref_no ? ", ref {$this->ref_no}" : '').($this->creator ? " oleh {$this->creator->name}" : '')
            .'. Diterima sejak dipesan: '.($qty !== null ? Locale::decimal((float) ($this->attributes['received_qty'] ?? 0), 3).' dari '.Locale::decimal($qty, 3) : Locale::decimal($this->receivedSince(), 3))." {$unit}.".($note ? " Catatan: {$note}." : '')
            .($this->closed_at === null ? LeadTimes::openOrderSentence($leadTime ?? LeadTimes::for($this->item), $this) : '');
    }
}
