<?php

namespace App\Http\Controllers;

use App\Enums\StockDocumentType;
use App\Models\Batch;
use App\Models\Item;
use App\Models\StockDocument;
use App\Models\Supplier;
use App\Services\CountSchedule;
use App\Services\LeadTimes;
use App\Services\StockValuation;
use Devlib\Device;
use Illuminate\Http\Request;

/* REMARK
 * Tujuan       : Beranda Stok (hak STB; dibuka dengan mengklik induk Stok di panel kiri, bukan butir menu): isi Beranda lama sebelum Beranda menjadi global: pengingat Stok Opname, angka ringkasan stok, tabel barang di titik pesan ulang dan hampir kedaluwarsa, grafik nilai stok.
 * Sumber hulu  : m_item + i_batch (stok tersedia per barang, batch hampir kedaluwarsa), rm_stock (hari ini, menunggu persetujuan), CountSchedule, LeadTimes, StockValuation.
 * Output       : view stock-home (desktop, dibuka sebagai tab Layer Shell) atau hp.stock-home.
 * Dipakai oleh : route stock.home (open pada induk Stok di Menu::layers, kartu Stok Beranda); Catat Pemesanan dan Tutup Pesanan kembali ke sini.
 * Status       : Aktif.
 */
class StockHomeController extends Controller
{
    public function __invoke(Request $request)
    {
        if (Device::view($request) === Device::PHONE) {
            return app(HpController::class)->stockHome($request);
        }
        $user = $request->user();
        $below = Item::query()->belowReorderPoint()->withAvailable()->with('category', 'openOrder.creator', 'openOrder.supplier')->orderByRaw(Item::availableSql().' / m_item.reorder_point', [Batch::usableFrom()])->get();

        return view('stock-home', [
            'below' => $below,
            'countReminder' => $user->canAccess('SO') && CountSchedule::available() ? CountSchedule::state() : null,   // pengingat Stok Opname (hanya yang punya modul SO)
            'canStartCount' => $user->isSupervisor() && $user->canWrite('SO'),
            'leadTimes' => LeadTimes::forMany($below),
            'suppliers' => Supplier::query()->where('is_active', true)->forSelect()->pluck('name', 'id')->all(),
            'expiring' => Batch::query()->nearExpiry()->orderBy('expiry_date')->get(),
            'itemCount' => Item::query()->where('is_active', true)->count(),
            'stockValue' => $user->isSupervisor() ? round(Batch::query()->selectRaw('SUM(stock_qty * avg_cost) v')->value('v') ?? 0, 2) : null,
            'receivingToday' => StockDocument::query()->where('type', StockDocumentType::Receiving)->whereIn('status', ['confirmed', 'cancel_pending'])->whereDate('doc_at', today())->count(),
            'issuanceToday' => StockDocument::query()->where('type', StockDocumentType::Issuance)->whereIn('status', ['confirmed', 'cancel_pending'])->whereDate('doc_at', today())->count(),
            'valuation' => $user->isSupervisor() ? StockValuation::series($request->query('month')) : null,
            'pending' => $user->isSupervisor() ? StockDocument::query()->whereIn('status', ['approval', 'cancel_pending'])->count() : null,
        ]);
    }
}
