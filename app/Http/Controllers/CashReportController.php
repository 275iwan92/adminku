<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Support\Text;
use Devlib\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/* REMARK
 * Tujuan       : Laporan modul Kas: Arus Kas (buku kas per kas/rekening, saldo awal-berjalan-akhir), Penerimaan (Uang Masuk per sumber dana, terpakai/sisa), Pengeluaran (Uang Keluar per sumber dana lewat Uang Masuk tertaut).
 *                Semua tampil sebagai x-dl::report (kop, kriteria, ringkasan, kelompok, cetak), pola sama ReportController (laporan Stok).
 * Sumber hulu  : m_cash_account, k_cash_in, k_cash_out.
 * Output       : view reports.cash-* dengan filter GET (periode, kas, sumber dana, status) + x-dl::report.
 * Dipakai oleh : routes/web.php (reports.cash-*).
 * Status       : Aktif.
 */
class CashReportController extends Controller
{
    // Buku kas: saldo awal periode + baris Uang Masuk/Uang Keluar Disetujui kronologis + saldo akhir, per kas/rekening (atau satu kas bila difilter).
    public function cashFlow(Request $request)
    {
        [$from, $to] = $this->period($request);
        $accountId = $request->filled('cash_account_id') ? (int) $request->query('cash_account_id') : null;
        $allAccounts = CashAccount::query()->orderBy('name')->get();
        $accounts = $accountId ? $allAccounts->where('id', $accountId) : $allAccounts;

        $rows = [];
        $totalOpen = $totalIn = $totalOut = 0.0;
        foreach ($accounts as $account) {
            $priorIn = (float) CashIn::query()->where('cash_account_id', $account->id)->where('status', 'approved')->where('doc_date', '<', $from->toDateString())->sum('amount');
            $priorOut = (float) CashOut::query()->whereHas('cashIn', fn ($q) => $q->where('cash_account_id', $account->id))->where('status', 'approved')->where('doc_date', '<', $from->toDateString())->sum('amount');
            $balance = (float) $account->opening_balance + $priorIn - $priorOut;
            $totalOpen += $balance;
            $rows[] = ['cash_account' => $account->name, 'all' => 'Semua Kas', 'date' => Locale::d($from), 'doc' => '—', 'uraian' => 'Saldo awal', 'masuk' => null, 'keluar' => null, 'saldo' => $balance];

            $ins = CashIn::query()->where('cash_account_id', $account->id)->where('status', 'approved')->whereBetween('doc_date', [$from->toDateString(), $to->toDateString()])->get()
                ->map(fn (CashIn $c) => ['at' => $c->doc_date, 'doc' => $c->doc_no, 'uraian' => $c->source_name, 'masuk' => (float) $c->amount, 'keluar' => null]);
            $outs = CashOut::query()->whereHas('cashIn', fn ($q) => $q->where('cash_account_id', $account->id))->where('status', 'approved')->whereBetween('doc_date', [$from->toDateString(), $to->toDateString()])->get()
                ->map(fn (CashOut $c) => ['at' => $c->doc_date, 'doc' => $c->doc_no, 'uraian' => $c->purpose, 'masuk' => null, 'keluar' => (float) $c->amount]);
            foreach ($ins->concat($outs)->sortBy('at')->values() as $e) {
                $balance += ($e['masuk'] ?? 0) - ($e['keluar'] ?? 0);
                $totalIn += $e['masuk'] ?? 0;
                $totalOut += $e['keluar'] ?? 0;
                $rows[] = ['cash_account' => $account->name, 'all' => 'Semua Kas', 'date' => Locale::d($e['at']), 'doc' => $e['doc'], 'uraian' => $e['uraian'], 'masuk' => $e['masuk'], 'keluar' => $e['keluar'], 'saldo' => $balance];
            }
            $rows[] = ['cash_account' => $account->name, 'all' => 'Semua Kas', 'date' => Locale::d($to), 'doc' => '—', 'uraian' => 'Saldo akhir', 'masuk' => null, 'keluar' => null, 'saldo' => $balance];
        }

        return view('reports.cash-flow', [
            'from' => $from, 'to' => $to, 'rows' => $rows, 'accountId' => $accountId, 'accounts' => $allAccounts->pluck('name', 'id')->all(),
            'totalOpen' => $totalOpen, 'totalIn' => $totalIn, 'totalOut' => $totalOut, 'totalClose' => $totalOpen + $totalIn - $totalOut,
        ]);
    }

    // Uang Masuk per sumber dana: jumlah diterima, terpakai (Uang Keluar disetujui tertaut), sisa.
    public function receipts(Request $request)
    {
        [$from, $to] = $this->period($request);
        $status = $this->statusFilter($request);
        $source = Text::of($request->query('source'));

        $rows = CashIn::query()->with('cashAccount')->whereBetween('doc_date', [$from->toDateString(), $to->toDateString()])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($source !== '', fn ($q) => $q->where('source_name', 'like', '%'.$source.'%'))
            ->orderBy('doc_date')->get()->map(fn (CashIn $c) => [
                'date' => Locale::d($c->doc_date), 'doc' => $c->doc_no ?? '(draf)', 'source_name' => $c->source_name, 'account' => $c->cashAccount->name,
                'amount' => (float) $c->amount, 'used' => $c->usedAmount(), 'remaining' => $c->remainingAmount(), 'status' => $c->statusLabel(),
            ])->all();

        return view('reports.cash-receipts', [
            'from' => $from, 'to' => $to, 'rows' => $rows, 'status' => $status, 'source' => $source,
            'sources' => CashIn::query()->distinct()->orderBy('source_name')->pluck('source_name', 'source_name')->all(),
        ]);
    }

    // Uang Keluar per sumber dana (lewat Uang Masuk yang ditaut): "untuk apa" per sumber dana, dikelompokkan oleh nama sumber dana.
    public function disbursements(Request $request)
    {
        [$from, $to] = $this->period($request);
        $accountId = $request->filled('cash_account_id') ? (int) $request->query('cash_account_id') : null;
        $status = $this->statusFilter($request);
        $source = Text::of($request->query('source'));

        $rows = CashOut::query()->with(['cashIn.cashAccount'])->whereBetween('doc_date', [$from->toDateString(), $to->toDateString()])
            ->when($accountId, fn ($q) => $q->whereHas('cashIn', fn ($w) => $w->where('cash_account_id', $accountId)))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($source !== '', fn ($q) => $q->whereHas('cashIn', fn ($w) => $w->where('source_name', 'like', '%'.$source.'%')))
            ->orderBy('doc_date')->get()->map(fn (CashOut $c) => [
                'date' => Locale::d($c->doc_date), 'doc' => $c->doc_no ?? '(draf)', 'cash_in' => $c->cashIn->number(), 'source_name' => $c->cashIn->source_name,
                'account' => $c->cashIn->cashAccount->name, 'purpose' => $c->purpose, 'amount' => (float) $c->amount, 'status' => $c->statusLabel(),
            ])->all();

        return view('reports.cash-disbursements', [
            'from' => $from, 'to' => $to, 'rows' => $rows, 'accountId' => $accountId, 'status' => $status, 'source' => $source,
            'accounts' => CashAccount::query()->orderBy('name')->pluck('name', 'id')->all(),
            'sources' => CashIn::query()->distinct()->orderBy('source_name')->pluck('source_name', 'source_name')->all(),
        ]);
    }

    // Filter Status: bawaan 'approved' hanya bila query ?status= sama sekali tidak dikirim. Middleware ConvertEmptyStringsToNull mengubah ?status= (pilihan "Semua") jadi null,
    // dan Request::query() memperlakukan null sebagai "tidak ada" lalu jatuh ke bawaan — has() memeriksa keberadaan kuncinya saja, jadi "Semua" tidak pernah salah kembali ke Disetujui.
    private function statusFilter(Request $request): string
    {
        return $request->has('status') ? (string) $request->query('status') : 'approved';
    }

    // Periode laporan dari query from / to (Y-m-d); bawaan awal bulan ini s.d. hari ini. Sama seperti ReportController::period.
    private function period(Request $request): array
    {
        $parse = fn (?string $v, Carbon $default) => rescue(fn () => $v ? Carbon::parse($v) : $default, $default, false);
        $from = $parse($request->query('from'), now()->startOfMonth())->startOfDay();
        $to = $parse($request->query('to'), now())->endOfDay();

        return [$from, $to->lt($from) ? $from->copy()->endOfDay() : $to];
    }
}
