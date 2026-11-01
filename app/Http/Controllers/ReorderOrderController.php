<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ReorderOrder;
use App\Rules\Quantity;
use App\Models\Supplier;
use App\Services\ReorderOrders;
use Devlib\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Mencatat bahwa barang di bawah Reorder Point sudah dipesan (catatan sederhana, bukan PO): supplier, jumlah dalam satuan stok, tanggal, referensi dan catatan opsional. Pesanan ditutup otomatis bila jumlah yang diterima (Receiving) memenuhi jumlah dipesan, atau ditutup manual bila tidak akan dipenuhi penuh.
 *                Layar Riwayat Pemesanan (modul ORD): semua pesanan dengan semua status (Dipesan, Diterima sebagian, Selesai, Ditutup manual), detail beserta penerimaan yang dialokasikan, dan tombol Tutup Pesanan yang sama dengan di Beranda.
 * Sumber hulu  : form di opening screen (item_id, supplier_id, qty, ordered_at, ref_no, note); popup Tutup Pesanan (alasan opsional); i_reorder_order, ReorderOrders::allocations.
 * Output       : i_reorder_order; kembali ke opening screen (atau ke detail pesanan bila ditutup dari Riwayat Pemesanan). Tanpa nilai uang.
 * Dipakai oleh : routes/web.php (reorder-orders.store, close di Beranda; reorder-orders.index, show di modul ORD).
 * Status       : Aktif.
 */
class ReorderOrderController extends Controller
{
    private const LABEL_DATE = 'd/m/Y';

    public function __construct(private readonly ReorderOrders $orders) {}

    // Riwayat: filter rentang tanggal pesan lewat GET (kosong = semua); status, supplier, cari, kolom, dan ekspor dikerjakan grid.
    public function index(Request $request)
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $rows = ReorderOrder::query()->with('item', 'supplier', 'creator')
            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('ordered_at', '>=', $d))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('ordered_at', '<=', $d))
            ->latest('ordered_at')->latest('id')->limit(2000)->get()
            ->map(fn (ReorderOrder $o) => $this->row($o))->all();

        return view('reorder-orders.index', ['rows' => $rows, 'from' => $data['from'] ?? '', 'to' => $data['to'] ?? '']);
    }

    public function show(Request $request, ReorderOrder $order)
    {
        $order->load('item', 'supplier', 'creator');

        return view('reorder-orders.show', [
            'order' => $order, 'row' => $this->row($order), 'unit' => $order->item->unit,
            'allocations' => $this->orders->allocations($order), 'canClose' => $order->closed_at === null && $request->user()->canWrite('ORD'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer', 'exists:m_item,id'],
            'supplier_id' => ['required', Rule::exists('m_supplier', 'id')->where('is_active', true)],
            'qty' => ['bail', 'required', 'numeric', 'gt:0', 'max:999999999999', new Quantity],
            'ordered_at' => ['required', 'date', 'before_or_equal:today'],
            'ref_no' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['qty.gt' => 'Jumlah dipesan harus lebih dari 0.']);
        $product = Item::findOrFail($data['item_id']);
        abort_unless($product->isBelowReorderPoint(), 422, 'Barang ini tidak berada di bawah titik pesan ulang.');
        abort_if($product->openOrder()->exists(), 422, 'Barang ini sudah dicatat dipesan dan belum diterima.');

        $data['ref_no'] ??= '';   // kolom ref_no tidak boleh null; kosong = tanpa referensi
        ReorderOrder::create($data + ['created_by' => $request->user()->id]);
        $this->orders->reconcile($product->id);   // penerimaan sejak tanggal pesan (termasuk hari yang sama) langsung dihitung

        return redirect()->route('home')->with('status', "Pemesanan {$product->name} dicatat.");
    }

    // Pesanan yang tidak akan dipenuhi penuh (supplier mengirim kurang): ditutup tanpa menghapus apa pun; hanya yang masih terbuka.
    // Dari Riwayat Pemesanan (return=history) kembali ke detail pesanan; dari Beranda kembali ke Beranda.
    public function close(Request $request, ReorderOrder $order): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:150']]);
        $this->orders->closeManually($order, $data['note'] ?? null);
        $status = "Pesanan {$order->item->name} ditutup.";

        return $request->input('return') === 'history' ? redirect()->route('reorder-orders.show', $order)->with('status', $status) : redirect()->route('home')->with('status', $status);
    }

    /** @return array<string, mixed> satu baris grid / detail; jumlah, diterima, dan sisa kosong ("-") untuk pesanan lama tanpa jumlah. */
    private function row(ReorderOrder $o): array
    {
        $qty = $o->qty;
        $num = fn (?float $v) => $v === null ? '-' : Locale::decimal($v, 3);
        $received = $qty === null ? null : (float) $o->received_qty;
        $manual = $o->close_reason === 'manual';

        return [
            'id' => $o->id, 'ordered_at' => $o->ordered_at->format('Y-m-d'), 'ref_no' => $o->ref_no ?: '-', 'product' => $o->item->name, 'code' => $o->item->code,
            'supplier' => $o->supplier?->name ?? '-', 'qty' => $num($qty).($qty === null ? '' : ' '.$o->item->unit), 'received' => $num($received), 'remaining' => $num($qty === null ? null : max(0, round($qty - (float) $o->received_qty, 3))),
            'status' => $o->statusLabel(), 'first_received' => $o->first_received_at?->format('Y-m-d'),
            'lead' => $o->first_received_at ? (string) (int) $o->ordered_at->copy()->startOfDay()->diffInDays($o->first_received_at->copy()->startOfDay()) : '-',   // teks: grid angka menampilkan kosong sebagai 0; '-' = belum ada penerimaan pertama
            'creator' => $o->creator?->name ?? '-',
            'closed' => $o->closed_at ? $o->closed_at->format(self::LABEL_DATE).($manual && $o->note ? ' · '.$o->note : '') : '-', 'note' => $o->note ?: '-', 'active' => true,
            'open' => $o->closed_at === null,
        ];
    }
}
