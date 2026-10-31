<?php

namespace App\Services;

use App\Enums\StockDocumentType;
use App\Models\Batch;
use App\Models\ReorderOrder;
use App\Models\StockDocument;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Aturan penutupan pesanan ulang (i_reorder_order). Pesanan yang punya jumlah ditutup HANYA saat jumlah diterima memenuhi jumlah dipesan; penerimaan sebagian = "diterima sebagian", tetap terbuka.
 *                Pesanan lama tanpa jumlah: penerimaan pertama sejak tanggal pesan menutupnya (aturan lama). Pesanan yang tak akan dipenuhi penuh ditutup manual (close_reason 'manual'), tidak ikut dialokasikan lagi.
 *                Perkiraan tiba / terlambat memakai kedatangan PERTAMA: setelah ada penerimaan pertama pesanan tidak ditandai terlambat lagi.
 * Sumber hulu  : penerimaan terkonfirmasi yang tidak dibatalkan (rm_stockcard jenis Penerimaan, satuan stok, semua batch produk), i_reorder_order.
 * Output       : i_reorder_order.received_qty, first_received_at, closed_at, close_reason, receiving_id. reconcile() idempoten: hasilnya hanya bergantung pada data penerimaan, jadi pembatalan lalu reconcile membuka kembali pesanan yang seharusnya terbuka.
 * Dipakai oleh : StockDocuments (konfirmasi dan pembatalan Penerimaan), ReorderOrderController.
 * Status       : Aktif.
 */
final class ReorderOrders
{
    /** Pergerakan masuk dari Penerimaan yang terkonfirmasi dan tidak dibatalkan, semua batch produk, menurut urutan waktu. */
    public static function receipts(int $productId)
    {
        return StockMovement::query()
            ->join('i_batch', 'i_batch.id', '=', 'rm_stockcard.batch_id')
            ->join('rm_stock', 'rm_stock.id', '=', 'rm_stockcard.stock_document_id')
            ->where('i_batch.product_id', $productId)->where('rm_stockcard.type', StockDocumentType::Receiving->value)->whereNull('rm_stockcard.reversal_of')
            ->whereIn('rm_stock.status', ['confirmed', 'cancel_pending'])
            ->orderBy('rm_stockcard.moved_at')->orderBy('rm_stockcard.id')
            ->get(['rm_stockcard.id', 'rm_stockcard.stock_document_id', 'rm_stockcard.moved_at', 'rm_stockcard.qty']);
    }

    /** Hitung ulang semua pesanan produk dari data penerimaan; dipanggil setelah Penerimaan dikonfirmasi atau dibatalkan. */
    public function reconcileDocument(StockDocument $doc): void
    {
        if ($doc->type !== StockDocumentType::Receiving) {
            return;
        }
        $products = Batch::query()->whereIn('id', $doc->lines()->pluck('batch_id'))->whereNotNull('product_id')->distinct()->pluck('product_id');
        foreach ($products as $productId) {
            $this->reconcile((int) $productId);
        }
    }

    public function reconcile(int $productId): void
    {
        DB::transaction(function () use ($productId) {
            $receipts = self::receipts($productId)->map(fn ($m) => ['at' => $m->moved_at, 'doc' => $m->stock_document_id, 'left' => (float) $m->qty])->all();
            $orders = ReorderOrder::query()->where('product_id', $productId)->where(fn ($q) => $q->whereNull('close_reason')->orWhere('close_reason', '!=', 'manual'))
                ->orderBy('ordered_at')->orderBy('id')->lockForUpdate()->get();

            foreach ($orders as $order) {
                $from = $order->ordered_at->copy()->startOfDay();   // tanggal pesan tanpa jam: penerimaan pada hari itu ikut dihitung
                $got = 0.0;
                $first = $last = null;

                if ($order->qty === null) {
                    // Pesanan lama tanpa jumlah: penerimaan pertama sejak tanggal pesan menutupnya; tidak memakai jatah penerimaan.
                    $first = $last = collect($receipts)->first(fn ($r) => $r['at'] >= $from);
                } else {
                    foreach ($receipts as &$r) {
                        $take = min($r['left'], round($order->qty - $got, 3));
                        if ($r['at'] < $from || $take <= 0) {
                            continue;
                        }
                        $r['left'] = round($r['left'] - $take, 3);
                        $got = round($got + $take, 3);
                        $first ??= $r;
                        $last = $r;
                    }
                    unset($r);
                }

                $done = $order->qty === null ? $last !== null : $got >= $order->qty;
                $order->update([
                    'received_qty' => $got,
                    'first_received_at' => $first['at'] ?? null,
                    'closed_at' => $done ? $last['at'] : null,
                    'close_reason' => $done ? 'received' : null,
                    'receiving_id' => $done ? $last['doc'] : null,
                ]);
            }
        });
    }

    /**
     * Penerimaan yang dialokasikan ke satu pesanan, untuk layar Riwayat Pemesanan (HANYA BACA, tidak menulis apa pun). Aturannya sama dengan reconcile: FIFO menurut tanggal pesan,
     * hanya penerimaan pada / sesudah tanggal pesan, pesanan yang ditutup manual tidak ikut dialokasikan (daftarnya kosong), pesanan lama tanpa jumlah memakai penerimaan pertama sejak tanggal pesan.
     * Tes menjaga agar jumlahnya sama dengan received_qty hasil reconcile.
     *
     * @return list<array{doc_id: int, doc_no: string, at: \Illuminate\Support\Carbon, qty: float}>
     */
    public function allocations(ReorderOrder $order): array
    {
        if ($order->close_reason === 'manual') {
            return [];
        }
        $receipts = self::receipts($order->product_id)->map(fn ($m) => ['at' => $m->moved_at, 'doc' => $m->stock_document_id, 'left' => (float) $m->qty])->all();
        $orders = ReorderOrder::query()->where('product_id', $order->product_id)->where(fn ($q) => $q->whereNull('close_reason')->orWhere('close_reason', '!=', 'manual'))->orderBy('ordered_at')->orderBy('id')->get();

        $mine = [];
        foreach ($orders as $o) {
            $from = $o->ordered_at->copy()->startOfDay();
            $got = 0.0;
            if ($o->qty === null) {
                $first = collect($receipts)->first(fn ($r) => $r['at'] >= $from);
                if ($o->id === $order->id && $first) {
                    $mine[] = ['doc' => $first['doc'], 'at' => $first['at'], 'qty' => $first['left']];
                }

                continue;
            }
            foreach ($receipts as &$r) {
                $take = min($r['left'], round($o->qty - $got, 3));
                if ($r['at'] < $from || $take <= 0) {
                    continue;
                }
                $r['left'] = round($r['left'] - $take, 3);
                $got = round($got + $take, 3);
                if ($o->id === $order->id) {
                    $mine[] = ['doc' => $r['doc'], 'at' => $r['at'], 'qty' => $take];
                }
            }
            unset($r);
        }

        $numbers = StockDocument::query()->whereIn('id', collect($mine)->pluck('doc'))->pluck('doc_no', 'id');

        return collect($mine)->groupBy('doc')->map(fn ($rows, $doc) => ['doc_id' => (int) $doc, 'doc_no' => (string) ($numbers[$doc] ?? '-'), 'at' => $rows->min('at'), 'qty' => round($rows->sum('qty'), 3)])->sortBy('at')->values()->all();
    }

    /** Tutup manual pesanan yang tidak akan dipenuhi penuh: tidak menghapus apa pun; alasan opsional ditambahkan ke catatan. */
    public function closeManually(ReorderOrder $order, ?string $reason): void
    {
        DB::transaction(function () use ($order, $reason) {
            $order->setRawAttributes(ReorderOrder::query()->lockForUpdate()->findOrFail($order->id)->getAttributes(), true);
            abort_if($order->closed_at !== null, 422, 'Pesanan ini sudah tertutup.');
            $reason = trim((string) $reason);
            $order->update([
                'closed_at' => now(), 'close_reason' => 'manual',
                'note' => $reason === '' ? $order->note : mb_substr(trim(($order->note ? $order->note.' ' : '')."Ditutup: {$reason}"), 0, 255),
            ]);
        });
    }
}
