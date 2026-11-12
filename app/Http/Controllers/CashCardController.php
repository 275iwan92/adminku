<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use App\Services\CashLedger;
use Devlib2\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/* REMARK
 * Tujuan       : Kartu Saldo per kas (KSB): pilih satu kas (Tunai / rekening bank), daftar mutasi buku besar kas bergulir dengan saldo berjalan, saldo awal dan akhir periode. Cetak / ekspor lewat x-dl2::report.
 * Sumber hulu  : k_cash_ledger (CashLedger), m_cash_account.
 * Output       : view cash-card.index.
 * Dipakai oleh : routes/web.php (cash-card.index).
 * Status       : Aktif.
 */
class CashCardController extends Controller
{
    public function index(Request $request)
    {
        $parse = fn (?string $v, Carbon $default) => rescue(fn () => $v ? Carbon::parse($v) : $default, $default, false);
        $from = $parse($request->query('from'), now()->startOfMonth())->startOfDay();
        $to = $parse($request->query('to'), now())->endOfDay();
        $to = $to->lt($from) ? $from->copy()->endOfDay() : $to;

        $accounts = CashAccount::query()->orderByRaw("type = 'cash' desc")->orderBy('name')->get();
        $account = $accounts->firstWhere('id', (int) $request->query('cash_account_id')) ?? $accounts->first();

        $rows = [];
        $open = $in = $out = 0.0;
        if ($account) {
            [$open, $moves] = app(CashLedger::class)->period($account->id, $from->toDateString(), $to->toDateString());
            $balance = $open;
            $rows[] = ['date' => Locale::d($from), 'type' => '—', 'doc' => '—', 'uraian' => 'Saldo awal', 'masuk' => null, 'keluar' => null, 'saldo' => $balance];
            foreach ($moves as $m) {
                $amount = (float) $m->amount;
                $balance += $amount;
                $in += max($amount, 0);
                $out += max(-$amount, 0);
                $rows[] = ['date' => Locale::d($m->moved_at), 'type' => $m->typeLabel().($m->reversal_of ? ' (pembatalan)' : ''), 'doc' => $m->doc_no ?? '—', 'uraian' => $m->note ?? '', 'masuk' => $amount > 0 ? $amount : null, 'keluar' => $amount < 0 ? -$amount : null, 'saldo' => $balance];
            }
            $rows[] = ['date' => Locale::d($to), 'type' => '—', 'doc' => '—', 'uraian' => 'Saldo akhir', 'masuk' => null, 'keluar' => null, 'saldo' => $balance];
        }

        return view('cash-card.index', [
            'from' => $from, 'to' => $to, 'rows' => $rows, 'account' => $account, 'accounts' => $accounts->pluck('name', 'id')->all(),
            'open' => $open, 'in' => $in, 'out' => $out, 'close' => $open + $in - $out,
        ]);
    }
}
