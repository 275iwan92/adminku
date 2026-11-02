<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Pesanan Pembelian (PO): satu pemasok, banyak baris barang, PPN opsional. Nomor PO-YYMM-0001 (urut per bulan). Status draft -> approval (diajukan) -> approved | rejected; approved boleh cancelled selama belum ada penerimaan. partial / done diisi PV nanti.
 *                Jumlah PO tidak menghitung baris cancelled / rejected: PurchaseRequest::syncOrdered membaca baris PO yang masih hidup. history() = riwayat beli satu barang per pemasok untuk layar Keputusan Pesanan.
 * Sumber hulu  : p_order, p_order_line, m_supplier, users; riwayat beli dari Penerimaan Barang terkonfirmasi (rm_stockcard).
 * Dipakai oleh : PurchaseOrderController, PurchaseDecisionController, PurchaseOrderPrint.
 * Status       : Aktif.
 */
#[Fillable(['doc_no', 'doc_date', 'supplier_id', 'payment_term_days', 'need_date', 'ship_to', 'vat_pct', 'note', 'status', 'created_by', 'submitted_at', 'approved_by', 'approved_at', 'reason'])]
class PurchaseOrder extends Model
{
    protected $table = 'p_order';

    public const STATUS = ['draft' => 'Draf', 'approval' => 'Diajukan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'partial' => 'Diterima sebagian', 'done' => 'Selesai', 'cancelled' => 'Dibatalkan'];

    // Status yang masih menghitung jumlah dipesan di PP.
    public const LIVE = ['draft', 'approval', 'approved', 'partial', 'done'];

    protected function casts(): array
    {
        return ['doc_date' => 'date', 'need_date' => 'date', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'vat_pct' => 'float'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'order_id')->orderBy('id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function subtotal(): float
    {
        return round((float) $this->lines->sum(fn (PurchaseOrderLine $l) => (float) $l->qty * (float) $l->price), 2);
    }

    public function vat(): float
    {
        return round($this->subtotal() * $this->vat_pct / 100, 2);
    }

    public function total(): float
    {
        return $this->subtotal() + $this->vat();
    }

    // Persen diterima = jumlah diterima / jumlah dipesan, semua baris.
    public function receivedPct(): int
    {
        $qty = (float) $this->lines->sum('qty');

        return $qty > 0 ? (int) round(100 * (float) $this->lines->sum('received_qty') / $qty) : 0;
    }

    // PO-YYMM-0001, urut per bulan tanggal PO. Dipanggil di dalam transaksi simpan.
    public static function nextNo(\DateTimeInterface $date): string
    {
        $prefix = 'PO-'.$date->format('ym').'-';
        $max = static::query()->where('doc_no', 'like', $prefix.'%')->lockForUpdate()->pluck('doc_no')->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;

        return $prefix.sprintf('%04d', $max + 1);
    }

    /**
     * Riwayat beli satu barang per pemasok dari Penerimaan terkonfirmasi yang tidak dibatalkan: terakhir beli, harga terakhir, berapa kali (jumlah dokumen), dan rincian (terbaru di atas).
     * Harga per satuan stok barang. Terurut: pemasok yang terakhir dipakai paling atas.
     *
     * @return Collection<int, object>
     */
    public static function history(int $itemId): Collection
    {
        return DB::table('rm_stockcard as c')
            ->join('rm_stock as d', 'd.id', '=', 'c.stock_document_id')->join('i_batch as b', 'b.id', '=', 'c.batch_id')->join('m_supplier as s', 's.id', '=', 'd.supplier_id')
            ->where('b.item_id', $itemId)->where('c.type', 'IN')->whereNull('c.reversal_of')->whereIn('d.status', ['confirmed', 'cancel_pending'])
            ->orderByDesc('c.moved_at')->orderByDesc('c.id')
            ->get(['s.id as supplier_id', 's.name as supplier', 'd.id as doc_id', 'd.doc_no', 'c.moved_at', 'c.qty', 'c.unit_cost'])
            ->groupBy('supplier_id')
            ->map(fn ($g) => (object) [
                'supplier_id' => $g[0]->supplier_id, 'supplier' => $g[0]->supplier, 'last_at' => $g[0]->moved_at, 'last_price' => (float) $g[0]->unit_cost,
                'times' => $g->pluck('doc_id')->unique()->count(), 'rows' => $g,
            ])
            ->sortByDesc('last_at')->values();
    }
}
