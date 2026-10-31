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
use App\Support\Menu;
use Devlib\Device;
use Illuminate\Http\Request;

/* REMARK
 * Tujuan       : Layar utama setelah login: Layer Shell (menu per peran) dengan dashboard KPI dan peringatan Reorder Point.
 * Sumber hulu  : m_item + items (stok tersedia per produk, batch hampir kedaluwarsa), rm_stock (hari ini, menunggu persetujuan).
 * Output       : view home; peringatan item bawah ROP tampil sebagai kartu alert di dashboard (tanpa popup).
 * Dipakai oleh : route home (tujuan redirect login).
 * Status       : Aktif.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        if (Device::view($request) === Device::PHONE) {
            return app(HpController::class)->home($request);
        }
        $user = $request->user();
        $below = Item::query()->belowReorderPoint()->withAvailable()->with('category', 'openOrder.creator', 'openOrder.supplier')->orderByRaw(Item::availableSql().' / m_item.reorder_point', [Batch::usableFrom()])->get();

        return view('home', [
            'modules' => Menu::layers($user),
            'user' => ['name' => $user->name, 'email' => $user->email, 'role' => $user->roleLabel()],
            'open' => $request->query('open'),
            'fresh' => (bool) session('fresh'),
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
