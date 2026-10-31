<?php

namespace App\Services;

use App\Enums\StockDocumentType;
use App\Models\Product;
use App\Models\ReorderOrder;
use App\Models\StockMovement;
use Devlib\Engine\LeadTime;

/* REMARK
 * Tujuan       : Menyambung engine LeadTime DevLib ke Stoku: lead time per barang dari pesanan nyata (i_reorder_order: tanggal pesan sampai tanggal masuk), dengan lead time manual barang (m_item.lead_time_days) sebagai cadangan.
 *                Tanggal masuk = penerimaan PERTAMA (first_received_at), juga untuk pesanan yang masih diterima sebagian. Pesanan tanpa penerimaan (termasuk yang ditutup manual tanpa penerimaan) tidak dihitung; bila penerimaan dibatalkan, ReorderOrders::reconcile mengosongkannya lagi.
 *                Hanya SARAN dan keterangan: tidak mengubah lead time manual, titik pesan ulang, aturan penutupan pesanan, maupun perhitungan peringatan.
 * Sumber hulu  : i_reorder_order, m_item.lead_time_days (0 = belum diisi, dianggap tanpa cadangan), rm_stockcard (pemakaian harian).
 * Output       : LeadTime per produk (forMany: satu query untuk banyak produk), pemakaian harian rata-rata.
 * Dipakai oleh : ReorderOrder::note, HomeController, ProductController, ReportController (Ringkasan Stok), form barang.
 * Status       : Aktif.
 */
final class LeadTimes
{
    public const SAMPLES = 5;

    public const MIN_SAMPLES = 3;

    public const LATE_TOLERANCE_DAYS = 1;

    public const USAGE_DAYS = 90;

    public static function for(Product $product): LeadTime
    {
        return self::forMany(collect([$product]))[$product->id];
    }

    /**
     * @param  iterable<Product>  $products
     * @return array<int, LeadTime> menurut id produk
     */
    public static function forMany(iterable $products): array
    {
        $products = collect($products)->keyBy('id');
        $orders = ReorderOrder::query()->whereIn('product_id', $products->keys())->orderBy('id')->get(['id', 'product_id', 'ordered_at', 'first_received_at'])->groupBy('product_id');

        return $products->map(fn (Product $p) => LeadTime::of(
            ($orders[$p->id] ?? collect())->map(fn (ReorderOrder $o) => ['start' => $o->ordered_at->toDateString(), 'end' => $o->first_received_at?->toDateString(), 'key' => $o->id])->all(),
            ['samples' => self::SAMPLES, 'min_samples' => self::MIN_SAMPLES, 'fallback_days' => $p->lead_time_days > 0 ? $p->lead_time_days : null, 'late_tolerance_days' => self::LATE_TOLERANCE_DAYS],
        ))->all();
    }

    /** Pemakaian harian rata-rata: total Pengeluaran terkonfirmasi (tidak dibatalkan) semua batch produk dalam 90 hari terakhir dibagi 90. */
    public static function dailyUsage(Product $product): float
    {
        $out = StockMovement::query()
            ->join('i_batch', 'i_batch.id', '=', 'rm_stockcard.item_id')
            ->join('rm_stock', 'rm_stock.id', '=', 'rm_stockcard.stock_document_id')
            ->where('i_batch.product_id', $product->id)->where('rm_stockcard.type', StockDocumentType::Issuance->value)->whereNull('rm_stockcard.reversal_of')
            ->whereIn('rm_stock.status', ['confirmed', 'cancel_pending'])->where('rm_stockcard.moved_at', '>=', now()->subDays(self::USAGE_DAYS))
            ->sum('rm_stockcard.qty');

        return abs((float) $out) / self::USAGE_DAYS;
    }

    /** Kalimat tambahan untuk pesanan yang masih terbuka: perkiraan tiba dan, bila terlambat, peringatannya; kosong bila tidak ada angka lead time. */
    public static function openOrderSentence(LeadTime $lt, ReorderOrder $order): string
    {
        // Perkiraan tiba dan terlambat mengikuti kedatangan PERTAMA: setelah ada penerimaan (sebagian) pesanan tidak ditandai terlambat lagi.
        if ($order->closed_at !== null || $order->first_received_at !== null || $lt->effective() === null) {
            return '';
        }
        $late = $lt->status($order->ordered_at, today())['state'] === 'late';

        return ' '.$lt->describeExpected($order->ordered_at).'.'.($late ? ' '.$lt->describeStatus($order->ordered_at, today()) : '');
    }
}
