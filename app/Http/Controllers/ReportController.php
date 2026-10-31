<?php

namespace App\Http\Controllers;

use App\Enums\StockDocumentType;
use App\Models\Category;
use App\Models\Batch;
use App\Models\ReorderOrder;
use App\Models\StockDocumentLine;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\LeadTimes;
use App\Support\Decimal;
use Devlib\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/* REMARK
 * Tujuan       : Laporan utama: Kartu Stok, Summary Stock, Kedaluwarsa, Penerimaan, Pengeluaran, Opname & Penyesuaian. Semua tampil sebagai x-dl::report (kop, kriteria, ringkasan, kelompok, cetak).
 * Sumber hulu  : rm_stockcard (kartu stok), items (summary), rm_stock_line + rm_stock (laporan dokumen). Kolom harga / nilai hanya untuk Supervisor.
 * Output       : view reports.* dengan filter GET (periode, item, dst) + x-dl::report.
 * Dipakai oleh : routes/web.php (reports.*).
 * Status       : Aktif.
 */
class ReportController extends Controller
{
    private const TYPE_LABEL = ['OPENING' => 'AWAL', 'IN' => 'MASUK', 'OUT' => 'KELUAR', 'ADJ' => 'PENY'];

    public function stockCard(Request $request)
    {
        $sup = $request->user()->isSupervisor();
        [$from, $to] = $this->period($request);
        $item = $request->filled('batch_id') ? Batch::with('category')->find($request->query('batch_id')) : null;
        $rows = [];
        $opening = 0.0;

        if ($item) {
            $before = StockMovement::query()->where('batch_id', $item->id)->where('moved_at', '<', $from)->orderByDesc('moved_at')->orderByDesc('id')->first();
            $opening = $before?->balance_qty ?? 0.0;
            $rows = StockMovement::query()->where('batch_id', $item->id)->whereBetween('moved_at', [$from, $to])->with(['document.supplier', 'user'])
                ->orderBy('moved_at')->orderBy('id')->get()->map(fn (StockMovement $m) => [
                    'date' => Locale::stamp($m->moved_at), 'month' => Locale::at($m->moved_at)->translatedFormat('F Y'),
                    'doc' => $m->document->doc_no, 'src' => $m->document->supplier?->name ?? $m->document->recipient ?? $m->document->reason,
                    'type' => self::TYPE_LABEL[$m->type].($m->reversal_of ? ' (BATAL)' : ''), 'ref' => $m->document->ref_no ?? $m->document->department ?? $m->document->reason ?? '', 'user' => $m->user->name,
                    'in' => $m->type !== 'ADJ' ? max($m->qty, 0) : 0, 'out' => $m->type !== 'ADJ' ? max(-$m->qty, 0) : 0, 'adj' => $m->type === 'ADJ' ? $m->qty : 0,
                    'cost' => $sup ? $m->unit_cost : null, 'bal_value' => $sup ? Decimal::amount($m->balance_qty, $m->balance_avg_cost) : null,
                ])->all();
        }

        return view('reports.stock-card', [
            'sup' => $sup, 'from' => $from, 'to' => $to, 'item' => $item, 'rows' => $rows, 'opening' => $opening,
            'items' => Batch::query()->orderBy('code')->get()->mapWithKeys(fn ($i) => [$i->id => $i->label()])->all(),
        ]);
    }

    public function summary(Request $request)
    {
        $sup = $request->user()->isSupervisor();
        [$from, $to] = $this->period($request);

        $items = Batch::query()->with('category', 'product')->orderBy('code')
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->query('category_id')))
            ->when(! $request->boolean('inactive'), fn ($q) => $q->where('is_active', true))->get();

        // Saldo awal = saldo sesudah pergerakan terakhir sebelum periode; saldo akhir = sesudah pergerakan terakhir sampai akhir periode.
        $opening = $this->lastMovements('<', $from);
        $closing = $this->lastMovements('<=', $to);
        $moves = StockMovement::query()->whereBetween('moved_at', [$from, $to])->selectRaw('batch_id, type, sum(qty) as total')->groupBy('batch_id', 'type')->get()->groupBy('batch_id');

        // Titik pesan ulang per produk: stok akhir batch yang belum kedaluwarsa pada akhir periode dijumlahkan (batch tanpa produk berdiri sendiri).
        $available = [];
        foreach ($items as $i) {
            if (! $i->expiry_date || $i->expiry_date->gte($to->copy()->startOfDay())) {
                $key = $i->product_id ?? 'i'.$i->id;
                $available[$key] = ($available[$key] ?? 0) + ($closing[$i->id]->balance_qty ?? 0.0);
            }
        }

        $orders = ReorderOrder::query()->whereNull('closed_at')->with('creator', 'product', 'supplier')->orderBy('id')->get()->keyBy('product_id');   // pesanan terbuka terbaru per produk
        $leadTimes = LeadTimes::forMany($items->pluck('product')->filter()->unique('id'));   // satu query untuk semua produk di laporan

        $rows = $items->map(function (Batch $i) use ($opening, $closing, $moves, $sup, $available, $orders, $leadTimes) {
            $m = ($moves[$i->id] ?? collect())->pluck('total', 'type');
            $end = $closing[$i->id]->balance_qty ?? 0.0;
            $cost = $closing[$i->id]->balance_avg_cost ?? 0.0;
            $status = $i->is_active && $i->reorder_point > 0 && ($available[$i->product_id ?? 'i'.$i->id] ?? 0) <= $i->reorder_point ? 'PESAN ULANG' : 'AMAN';

            return [
                'code' => $i->code, 'name' => $i->name, 'loc' => $i->location, 'cat' => $i->category->name, 'unit' => $i->unit,
                'open' => $opening[$i->id]->balance_qty ?? 0.0,
                'in' => (float) ($m['IN'] ?? 0) + (float) ($m['OPENING'] ?? 0), 'out' => abs((float) ($m['OUT'] ?? 0)), 'adj' => (float) ($m['ADJ'] ?? 0),
                'end' => $end, 'rop' => $i->reorder_point, 'roq' => $i->reorder_qty, 'lead' => $i->lead_time_days, 'lead_auto' => ($lt = $leadTimes[$i->product_id ?? 0] ?? null) && $lt->source() === 'data' ? $lt->effective() : null,
                'status' => $status, 'order' => $status === 'PESAN ULANG' ? ($orders[$i->product_id ?? 0] ?? null)?->note() : null,
                'order_supplier' => $status === 'PESAN ULANG' ? ($orders[$i->product_id ?? 0] ?? null)?->supplier?->name : null,
                'order_qty' => $status === 'PESAN ULANG' && ($o = $orders[$i->product_id ?? 0] ?? null) && $o->qty !== null ? Locale::decimal($o->qty, 3).' '.$i->unit : null,
                'cost' => $sup ? $cost : null, 'value' => $sup ? Decimal::amount($end, $cost) : null,
            ];
        });
        if ($request->boolean('only_reorder')) {
            $rows = $rows->where('status', 'PESAN ULANG');
        }

        return view('reports.summary', [
            'sup' => $sup, 'from' => $from, 'to' => $to, 'categories' => Category::orderBy('name')->pluck('name', 'id')->all(), 'rows' => $rows->values()->all(),
        ]);
    }

    /** @return Collection<int, StockMovement> pergerakan terakhir tiap item (kunci = batch_id) pada batas waktu */
    private function lastMovements(string $operator, Carbon $at)
    {
        $ids = StockMovement::query()->where('moved_at', $operator, $at)->selectRaw('max(id) as id')->groupBy('batch_id')->pluck('id');

        return StockMovement::query()->whereIn('id', $ids)->get()->keyBy('batch_id');
    }

    // Barang bersisa stok yang sudah kedaluwarsa atau akan kedaluwarsa dalam N hari (bawaan 30), dari yang paling dulu.
    public function expiry(Request $request)
    {
        $sup = $request->user()->isSupervisor();
        $days = min(max((int) $request->query('days', Batch::EXPIRY_WARN_DAYS), 0), 365);
        $rows = Batch::query()->nearExpiry($days)->with('category')->orderBy('expiry_date')->orderBy('code')
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->query('category_id')))->get()
            ->map(fn (Batch $i) => [
                'code' => $i->code, 'name' => $i->name, 'loc' => $i->location, 'cat' => $i->category->name, 'unit' => $i->unit,
                'expiry' => Locale::d($i->expiry_date), 'left' => today()->diffInDays($i->expiry_date, false), 'stock' => $i->stock_qty,
                'value' => $sup ? $i->stockValue() : null, 'status' => $i->expiryState($days),
            ])->all();

        return view('reports.expiry', ['sup' => $sup, 'days' => $days, 'categories' => Category::orderBy('name')->pluck('name', 'id')->all(), 'rows' => $rows]);
    }

    public function receiving(Request $request)
    {
        return $this->documentReport($request, StockDocumentType::Receiving, 'reports.receiving', fn ($q) => $q
            ->when($request->filled('supplier_id'), fn ($w) => $w->whereHas('document', fn ($d) => $d->where('supplier_id', $request->query('supplier_id')))),
            ['suppliers' => Supplier::forSelect()->pluck('name', 'id')->all()]);
    }

    public function issuance(Request $request)
    {
        return $this->documentReport($request, StockDocumentType::Issuance, 'reports.issuance', fn ($q) => $q
            ->when($request->filled('who'), fn ($w) => $w->whereHas('document', fn ($d) => $d->where(fn ($x) => $x
                ->where('recipient', 'like', '%'.$request->query('who').'%')->orWhere('department', 'like', '%'.$request->query('who').'%')))));
    }

    public function adjustment(Request $request)
    {
        return $this->documentReport($request, StockDocumentType::Adjustment, 'reports.adjustment', fn ($q) => $q
            ->when($request->filled('status'), fn ($w) => $w->whereHas('document', fn ($d) => $d->where('status', $request->query('status')))));
    }

    private function documentReport(Request $request, StockDocumentType $type, string $view, \Closure $filter, array $extra = [])
    {
        $sup = $request->user()->isSupervisor();
        [$from, $to] = $this->period($request);

        $lines = StockDocumentLine::query()->with(['batch', 'document.supplier', 'document.creator', 'document.approver'])
            ->whereHas('document', fn ($d) => $d->where('type', $type)->whereBetween('doc_at', [$from, $to])->when($type !== StockDocumentType::Adjustment, fn ($w) => $w->whereIn('status', ['confirmed', 'cancel_pending'])));   // masuk / keluar: yang sudah Confirm (pembatalan menunggu persetujuan masih berlaku)
        $lines = $filter($lines)->get()->sortBy([fn ($a, $b) => $a->document->doc_at <=> $b->document->doc_at, fn ($a, $b) => $a->id <=> $b->id]);

        $rows = $lines->map(function (StockDocumentLine $l) use ($sup) {
            $d = $l->document;
            $cost = (float) ($l->unit_cost ?? $l->batch->avg_cost);

            return [
                'date' => Locale::stamp($d->doc_at), 'month' => Locale::at($d->doc_at)->translatedFormat('F Y'), 'doc' => $d->doc_no, 'status' => ['confirmed' => 'Terkonfirmasi', 'draft' => 'Draf', 'approval' => 'Menunggu Persetujuan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan', 'cancel_pending' => 'Pembatalan Menunggu Persetujuan'][$d->status] ?? $d->status,
                'party' => $d->supplier?->name ?? $d->recipient ?? '', 'recipient' => $d->recipient ?? '', 'dept' => $d->department ?? '', 'ref' => $d->ref_no ?? '', 'reason' => $d->reason ?? '',
                'code' => $l->batch->code, 'item' => $l->batch->name, 'unit' => $l->batch->unit, 'qty' => $l->qty,
                'entered' => $l->entered_unit ? Locale::decimal($l->entered_qty, 3).' '.$l->entered_unit : '',
                'system' => $l->system_qty, 'counted' => $l->counted_qty,
                'cost' => $sup ? $cost : null, 'amount' => $sup ? Decimal::amount(abs($l->qty), $cost) : null, 'effect' => $sup ? Decimal::amount($l->qty, $cost) : null,
                'user' => $d->creator->name, 'approver' => $d->approver?->name ?? '',
            ];
        })->values()->all();

        return view($view, ['sup' => $sup, 'from' => $from, 'to' => $to, 'rows' => $rows] + $extra);
    }

    // Periode laporan dari query from / to (Y-m-d); bawaan awal bulan ini s.d. hari ini.
    private function period(Request $request): array
    {
        $parse = fn (?string $v, Carbon $default) => rescue(fn () => $v ? Carbon::parse($v) : $default, $default, false);
        $from = $parse($request->query('from'), now()->startOfMonth())->startOfDay();
        $to = $parse($request->query('to'), now())->endOfDay();

        return [$from, $to->lt($from) ? $from->copy()->endOfDay() : $to];
    }
}
