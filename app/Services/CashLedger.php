<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\CashAdjustment;
use App\Models\CashIn;
use App\Models\CashMovement;
use App\Models\CashOut;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/* REMARK
 * Tujuan       : Satu-satunya pintu yang mengubah saldo kas: menulis baris k_cash_ledger (saldo = jumlah baris). Baris tidak pernah diubah / dihapus; pembatalan = baris pembalik (reversal_of).
 *                Tiap penulisan mengunci baris kas dulu, lalu menolak bila saldo jadi negatif (Uang Keluar, Penyesuaian kurang, pembatalan Uang Masuk).
 * Sumber hulu  : CashIn / CashOut / CashAdjustment yang disetujui, saldo awal m_cash_account.
 * Output       : baris k_cash_ledger.
 * Dipakai oleh : CashInController, CashOutController, CashAdjustmentController, CashAccount (saldo awal), migrasi data lama.
 * Status       : Aktif.
 */
final class CashLedger
{
    private const EPS = 0.005;

    public function balance(int $accountId, ?string $upTo = null): float
    {
        return round((float) CashMovement::query()->where('cash_account_id', $accountId)->when($upTo, fn ($q) => $q->whereDate('moved_at', '<=', $upTo))->sum('amount'), 2);
    }

    /**
     * Mutasi satu kas dalam periode: [saldo awal, baris]. Baris saldo awal (type opening) dilipat ke saldo awal, bukan didaftar sebagai uang masuk.
     *
     * @return array{0: float, 1: \Illuminate\Support\Collection<int, CashMovement>}
     */
    public function period(int $accountId, string $from, string $to): array
    {
        $q = CashMovement::query()->where('cash_account_id', $accountId);
        $open = (float) (clone $q)->where(fn ($w) => $w->whereDate('moved_at', '<', $from)->orWhere(fn ($o) => $o->where('type', 'opening')->whereDate('moved_at', '<=', $to)))->sum('amount');
        $rows = (clone $q)->whereDate('moved_at', '>=', $from)->whereDate('moved_at', '<=', $to)->where('type', '!=', 'opening')->orderBy('moved_at')->orderBy('id')->get();

        return [$open, $rows];
    }

    public function postIn(CashIn $doc, ?User $user): void
    {
        $this->write($doc->cash_account_id, 'in', 'cash_in', $doc->id, $doc->doc_no, (float) $doc->amount, $doc->doc_date, $user, $doc->source_name);
    }

    public function postOut(CashOut $doc, ?User $user): void
    {
        $this->write($doc->cash_account_id, 'out', 'cash_out', $doc->id, $doc->doc_no, -(float) $doc->amount, $doc->doc_date, $user, $doc->purpose);
    }

    public function postAdjustment(CashAdjustment $doc, ?User $user): void
    {
        $this->write($doc->cash_account_id, 'adjust', 'cash_adjust', $doc->id, $doc->doc_no, $doc->signedAmount(), $doc->doc_date, $user, $doc->cause);
    }

    /** Menyamakan baris saldo awal dengan m_cash_account.opening_balance: selisihnya ditulis sebagai baris baru (baris lama tidak diubah). */
    public function syncOpening(CashAccount $account, ?User $user = null): void
    {
        $rows = CashMovement::query()->where('doc_type', 'opening')->where('doc_id', $account->id);
        $first = ! (clone $rows)->exists();
        $delta = round((float) $account->opening_balance - (float) $rows->sum('amount'), 2);
        if ($first || abs($delta) >= self::EPS) {
            // Baris pertama bertanggal paling awal (pembuatan kas atau dokumen tertua) supaya selalu mendahului mutasi; koreksi berikutnya bertanggal hari ini.
            $at = $first ? collect([$account->created_at?->toDateString(), CashIn::query()->where('cash_account_id', $account->id)->min('doc_date'), CashOut::query()->where('cash_account_id', $account->id)->min('doc_date')])->filter()->min() : now();
            $this->write($account->id, 'opening', 'opening', $account->id, null, $delta, $at ?? now(), $user ?? auth()->user(), 'Saldo awal');
        }
    }

    /**
     * Membalik semua baris dokumen yang belum dibalik (pembatalan dokumen yang sudah Disetujui). Pembalikan yang membuat saldo negatif ditolak.
     *
     * @return int jumlah baris pembalik
     */
    public function reverse(string $docType, int $docId, ?User $user): int
    {
        return DB::transaction(function () use ($docType, $docId, $user) {
            $rows = CashMovement::query()->where('doc_type', $docType)->where('doc_id', $docId)->whereNull('reversal_of')
                ->whereNotExists(fn ($q) => $q->from('k_cash_ledger as r')->whereColumn('r.reversal_of', 'k_cash_ledger.id'))->orderBy('id')->get();
            foreach ($rows as $r) {
                $this->write($r->cash_account_id, $r->type, $r->doc_type, $r->doc_id, $r->doc_no, -(float) $r->amount, now(), $user, 'Pembatalan '.($r->doc_no ?? ''), $r->id);
            }

            return $rows->count();
        });
    }

    /** Data lama: saldo awal tiap kas + satu baris untuk tiap Uang Masuk / Uang Keluar Disetujui. Aman diulang (tidak menggandakan). */
    public function backfill(): int
    {
        $before = CashMovement::query()->count();
        DB::transaction(function () {
            foreach (CashAccount::query()->orderBy('id')->get() as $a) {
                $this->syncOpening($a);
            }
            foreach ([[CashIn::class, 'cash_in'], [CashOut::class, 'cash_out']] as [$model, $type]) {
                foreach ($model::query()->where('status', 'approved')->orderBy('doc_date')->orderBy('id')->get() as $doc) {
                    $in = $type === 'cash_in';
                    // Tanpa penjaga saldo: ini mencatat sejarah yang sudah disetujui, bukan transaksi baru.
                    $this->write($doc->cash_account_id, $in ? 'in' : 'out', $type, $doc->id, $doc->doc_no, $in ? (float) $doc->amount : -(float) $doc->amount, $doc->doc_date, null, $in ? $doc->source_name : $doc->purpose, null, false);
                }
            }
        });

        return CashMovement::query()->count() - $before;
    }

    private function write(int $accountId, string $type, string $docType, int $docId, ?string $docNo, float $signed, $movedAt, ?User $user, ?string $note, ?int $reversalOf = null, bool $guard = true): void
    {
        DB::transaction(function () use ($accountId, $type, $docType, $docId, $docNo, $signed, $movedAt, $user, $note, $reversalOf, $guard) {
            $account = CashAccount::query()->lockForUpdate()->findOrFail($accountId);
            if ($reversalOf === null && $docType !== 'opening' && CashMovement::query()->where('doc_type', $docType)->where('doc_id', $docId)->whereNull('reversal_of')->exists()) {
                return;   // dokumen yang sama tidak diposting dua kali
            }
            $balance = $this->balance($accountId);
            if ($guard && $signed < 0 && round($balance + $signed, 2) < -self::EPS) {
                throw ValidationException::withMessages(['amount' => "Saldo {$account->name} tidak cukup: tersedia Rp ".number_format($balance, 0, ',', '.').', dibutuhkan Rp '.number_format(abs($signed), 0, ',', '.').'.']);
            }
            CashMovement::create([
                'cash_account_id' => $accountId, 'moved_at' => $movedAt, 'type' => $type, 'doc_type' => $docType, 'doc_id' => $docId, 'doc_no' => $docNo,
                'note' => $note !== null ? mb_substr($note, 0, 255) : null, 'amount' => $signed, 'user_id' => $user?->id, 'reversal_of' => $reversalOf,
            ]);
        });
    }
}
