<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\Supplier;
use App\Support\Text;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Keputusan Pesanan (PUK): layar kerja (tidak menyimpan dokumen) untuk SATU barang: dipilih / diketik dari master, atau diambil dari baris PP yang Disetujui dan belum terpesan penuh. Klik Proses menampilkan riwayat beli per pemasok
 *                (terakhir beli, harga terakhir, berapa kali, rincian tiap pembelian lewat tombol Riwayat). Memilih pemasok lalu Buat PO menambahkan barang itu ke draf PO pemasok tersebut milik pengguna (atau membuat draf baru).
 *                Barang yang belum pernah dibeli hanya menampilkan info; pemasok dipilih lewat Pemasok lain. Harga awal PO = harga terakhir di pemasok itu, lalu harga terakhir barang, lalu 0.
 * Sumber hulu  : Penerimaan Barang terkonfirmasi (PurchaseOrder::history), p_request_line (PP Disetujui / Dipesan sebagian), m_supplier, p_order.
 * Output       : layar Keputusan Pesanan; draf PO.
 * Dipakai oleh : routes/web.php (po-decision.*).
 * Status       : Aktif.
 */
class PurchaseDecisionController extends Controller
{
    public function index(Request $request)
    {
        $result = null;
        if ($request->boolean('proses')) {
            if ($request->input('mode') === 'pp' && ($pl = PurchaseRequestLine::query()->find($request->input('line')))) {   // barang diambil dari baris PP: isi Barang, Qty (sisa), Unit
                $request->merge(['item_id' => $pl->item_id, 'item_name' => $pl->item_name, 'qty' => $pl->qty - $pl->ordered_qty, 'unit' => $pl->unit]);
            } else {
                $request->request->remove('line');
                $request->query->remove('line');
            }
            $data = $request->validate([
                'item_id' => ['nullable', Rule::exists('m_item', 'id')->where('is_active', true)],
                'item_name' => ['nullable', 'required_without:item_id', 'string', 'max:150'],
                'qty' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
                'unit' => ['nullable', 'string', 'max:20', 'required_without:item_id'],
            ], ['item_name.required_without' => 'Pilih barang.', 'qty.gt' => 'Qty harus lebih dari 0.', 'unit.required_without' => 'Isi satuan untuk barang yang diketik.']);
            $data['qty'] ??= 1;
            $item = isset($data['item_id']) ? Item::query()->find($data['item_id']) : null;
            $result = [
                'item' => $item, 'name' => $item ? $item->code.' · '.$item->name : Text::of($data['item_name'] ?? ''), 'qty' => $data['qty'], 'unit' => filled($data['unit'] ?? null) ? $data['unit'] : $item->unit,
                'history' => $item ? PurchaseOrder::history($item->id) : collect(), 'line' => $request->input('line'),
                'others' => Supplier::query()->forSelect()->where('is_active', true)->pluck('name', 'id')->all(),
            ];
        }

        $outstanding = PurchaseRequestLine::query()->with(['request', 'item'])
            ->whereHas('request', fn ($q) => $q->whereIn('status', ['approved', 'partial']))->whereColumn('ordered_qty', '<', 'qty')->orderBy('request_id')->orderBy('id')->get();

        return view('purchase-decision.index', ['result' => $result, 'outstanding' => $outstanding, 'mode' => $request->query('mode') === 'pp' ? 'pp' : 'master', 'line' => $request->query('line')]);
    }

    // Buat PO: tambahkan barang ke draf PO pemasok terpilih milik pengguna, atau buat draf baru.
    public function make(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->canWrite('PUO'), 403, 'Anda tidak punya hak menulis Pesanan Pembelian.');
        $data = $request->validate([
            'supplier_id' => ['required', Rule::exists('m_supplier', 'id')->where('is_active', true)],
            'item_id' => ['nullable', Rule::exists('m_item', 'id')->where('is_active', true)],
            'item_name' => ['nullable', 'required_without:item_id', 'string', 'max:150'],
            'qty' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'unit' => ['nullable', 'string', 'max:20', 'required_without:item_id'],
            'line' => ['nullable', 'integer'],
        ], ['supplier_id.required' => 'Pilih pemasok.']);

        $po = DB::transaction(function () use ($data, $user) {
            $supplier = Supplier::query()->findOrFail($data['supplier_id']);
            $item = isset($data['item_id']) ? Item::query()->find($data['item_id']) : null;
            $link = isset($data['line']) ? PurchaseRequestLine::query()->whereHas('request', fn ($q) => $q->whereIn('status', ['approved', 'partial']))->find($data['line']) : null;
            $price = $item ? (PurchaseOrder::history($item->id)->firstWhere('supplier_id', $supplier->id)?->last_price ?? PurchaseRequest::lastCost($item->id) ?? 0) : 0;

            $po = PurchaseOrder::query()->where('status', 'draft')->where('supplier_id', $supplier->id)->where('created_by', $user->id)->latest('id')->lockForUpdate()->first();
            if (! $po) {
                $po = new PurchaseOrder(['doc_date' => today(), 'supplier_id' => $supplier->id, 'payment_term_days' => $supplier->payment_term_days, 'vat_pct' => 0, 'status' => 'draft', 'created_by' => $user->id]);
                $po->save();   // draf belum bernomor: nomor lahir saat Confirm
            }
            $po->lines()->create([
                'request_line_id' => $link?->id, 'item_id' => $item?->id, 'item_name' => $item ? null : Text::of($data['item_name'] ?? ''),
                'qty' => $data['qty'], 'unit' => filled($data['unit'] ?? null) ? $data['unit'] : $item->unit, 'price' => $price,
            ]);
            $link?->request->syncOrdered();

            return $po;
        });

        return redirect()->route('po.show', $po)->with('status', 'Barang ditambahkan ke draf PO ('.$po->number().'). Periksa harga lalu Confirm.');
    }
}
