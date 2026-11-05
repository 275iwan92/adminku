<?php

namespace App\Http\Controllers;

use App\Enums\StockDocumentType;
use App\Models\StockDocument;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Surat Jalan (SJL): daftar Pengeluaran Barang sebagai surat jalan, dengan pemakaiannya di trip Pengiriman (nomor trip, plat, sopir). Membuat dan membuka surat jalan memakai form Pengeluaran Barang (hak modul ISS);
 *                menu ini hanya jalan pintas dan pemantauan.
 * Sumber hulu  : rm_stock (Issuance), delivery_stop_doc, delivery_stop, delivery_trip, m_vehicle.
 * Output       : grid Surat Jalan.
 * Dipakai oleh : routes/web.php (delivery-notes.index).
 * Status       : Aktif.
 */
class DeliveryNoteController extends Controller
{
    private const STATUS = ['draft' => 'Draf', 'confirmed' => 'Terkonfirmasi', 'cancel_pending' => 'Pembatalan Menunggu Persetujuan', 'cancelled' => 'Dibatalkan'];

    public function index()
    {
        $use = DB::table('delivery_stop_doc as d')->join('delivery_stop as s', 's.id', '=', 'd.stop_id')->join('delivery_trip as t', 't.id', '=', 's.trip_id')->join('m_vehicle as v', 'v.id', '=', 't.vehicle_id')
            ->select('d.stock_document_id', 't.id as trip_id', 't.doc_no', 't.driver', 'v.plate')->get()->keyBy('stock_document_id');

        $rows = StockDocument::query()->where('type', StockDocumentType::Issuance)->withCount('lines')->latest('doc_at')->latest('id')->limit(1000)->get()->map(function (StockDocument $d) use ($use) {
            $u = $use[$d->id] ?? null;

            return [
                'id' => $d->id, 'doc_no' => $d->doc_no, 'doc_at' => $d->doc_at->format('Y-m-d H:i:s'), 'party' => $d->recipient ?? '—', 'lines' => $d->lines_count,
                'trip' => $u ? ($u->doc_no ?? 'Draf #'.$u->trip_id) : 'Belum dipakai', 'plate' => $u?->plate, 'driver' => $u?->driver, 'status' => self::STATUS[$d->status] ?? $d->status,
            ];
        })->all();

        return view('delivery-notes.index', ['rows' => $rows]);
    }
}
