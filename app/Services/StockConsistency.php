<?php

namespace App\Services;

use App\Enums\StockDocumentType;
use App\Models\Batch;
use App\Models\StockDocument;
use App\Models\StockDocumentLine;
use App\Models\StockMovement;
use App\Support\Decimal;

/* REMARK
 * Tujuan       : Pemeriksaan konsistensi stok TANPA mengubah data: (saldo) i_batch.stock_qty = saldo terakhir buku besar rm_stockcard, (jumlah) = jumlah seluruh qty buku besar, (rantai) tiap baris = saldo sebelumnya + qty,
 *                (biaya) biaya rata-rata bergerak tiap baris dan i_batch.avg_cost cocok dengan hitung ulang presisi, (nilai) nilai stok = qty x biaya rata-rata, (negatif) tidak ada saldo minus,
 *                (dokumen) pergerakan tiap dokumen sama dengan barisnya, dokumen draf/ditolak tidak punya pergerakan, dokumen batal netto nol.
 * Output       : array nama pemeriksaan => daftar temuan (kosong = konsisten) dan ringkasan angka.
 * Dipakai oleh : php artisan sstock:check-stock, tes StockConsistencyTest.
 * Status       : Aktif.
 */
final class StockConsistency
{
    private const AVG_TOLERANCE = 0.00005;   // biaya rata-rata disimpan 4 desimal

    /** @return array{problems: array<string, list<string>>, summary: array<string, int|float>} */
    public static function run(): array
    {
        $problems = array_fill_keys(['saldo', 'jumlah', 'rantai', 'biaya', 'nilai', 'negatif', 'dokumen'], []);
        $batches = Batch::query()->get(['id', 'code', 'stock_qty', 'avg_cost'])->keyBy('id');
        $last = [];   // batch_id => [saldo, biaya rata-rata] sesudah baris terakhir
        $sum = [];
        $rows = 0;

        foreach (StockMovement::query()->orderBy('batch_id')->orderBy('id')->cursor() as $m) {
            $rows++;
            $code = $batches[$m->batch_id]->code ?? "batch #{$m->batch_id}";
            [$prevQty, $prevAvg] = $last[$m->batch_id] ?? [0.0, 0.0];
            $sum[$m->batch_id] = round(($sum[$m->batch_id] ?? 0.0) + $m->qty, Decimal::QTY_SCALE);

            if (! isset($batches[$m->batch_id])) {
                $problems['saldo'][] = "Buku besar #{$m->id} menunjuk batch yang tidak ada ({$m->batch_id}).";
            }
            if (abs(round($prevQty + $m->qty, Decimal::QTY_SCALE) - $m->balance_qty) > Decimal::QTY_EPS) {
                $problems['rantai'][] = "{$code} baris #{$m->id}: saldo sebelumnya {$prevQty} + qty {$m->qty} seharusnya ".round($prevQty + $m->qty, Decimal::QTY_SCALE).", tercatat {$m->balance_qty}.";
            }
            $expected = self::expectedAverage($m, $prevQty, $prevAvg);
            if (abs($expected - $m->balance_avg_cost) > self::AVG_TOLERANCE) {
                $problems['biaya'][] = "{$code} baris #{$m->id}: biaya rata-rata seharusnya {$expected}, tercatat {$m->balance_avg_cost}.";
            }
            $last[$m->batch_id] = [$m->balance_qty, $m->balance_avg_cost];
        }

        $pairs = [];
        foreach ($batches as $id => $b) {
            [$qty, $avg] = $last[$id] ?? [0.0, 0.0];
            if (abs($b->stock_qty - $qty) > Decimal::QTY_EPS) {
                $problems['saldo'][] = "{$b->code}: i_batch.stock_qty {$b->stock_qty} tetapi saldo terakhir buku besar {$qty}.";
            }
            if (abs($b->stock_qty - ($sum[$id] ?? 0.0)) > Decimal::QTY_EPS) {
                $problems['jumlah'][] = "{$b->code}: i_batch.stock_qty {$b->stock_qty} tetapi jumlah qty buku besar ".($sum[$id] ?? 0.0).'.';
            }
            if (isset($last[$id]) && abs($b->avg_cost - $avg) > self::AVG_TOLERANCE) {
                $problems['biaya'][] = "{$b->code}: i_batch.avg_cost {$b->avg_cost} tetapi biaya rata-rata terakhir buku besar {$avg}.";
            }
            if (Decimal::amount($b->stock_qty, $b->avg_cost) != Decimal::amount($qty, $avg) && isset($last[$id])) {
                $problems['nilai'][] = "{$b->code}: nilai batch ".Decimal::amount($b->stock_qty, $b->avg_cost).' tetapi nilai menurut buku besar '.Decimal::amount($qty, $avg).'.';
            }
            if ($b->stock_qty < -Decimal::QTY_EPS) {
                $problems['negatif'][] = "{$b->code}: stok minus {$b->stock_qty}.";
            }
            $pairs[] = [$b->stock_qty, $b->avg_cost];
        }

        $total = Decimal::total($pairs, 2);
        $sql = round((float) (Batch::query()->selectRaw('SUM(stock_qty * avg_cost) v')->value('v') ?? 0), 2);
        if (abs($total - $sql) > 0.005) {
            $problems['nilai'][] = "Total nilai stok: hitung presisi {$total} tetapi SUM SQL {$sql}.";
        }

        $problems['dokumen'] = self::documentProblems();

        return ['problems' => $problems, 'summary' => ['batch' => $batches->count(), 'pergerakan' => $rows, 'nilai_stok' => $total]];
    }

    // Biaya rata-rata yang seharusnya sesudah baris itu, dengan aturan yang sama dengan StockLedger (post: hanya penambahan menghitung ulang; reverse: pembalik).
    private static function expectedAverage(StockMovement $m, float $prevQty, float $prevAvg): float
    {
        $avg = fn () => Decimal::round(Decimal::div(Decimal::add(Decimal::mul(Decimal::of($prevQty), Decimal::of($prevAvg)), Decimal::mul(Decimal::of($m->qty), Decimal::of($m->unit_cost))), Decimal::of($m->balance_qty)), 4);

        if ($m->reversal_of !== null) {
            return match (true) {
                $m->balance_qty <= Decimal::QTY_EPS => $prevAvg,
                $m->qty > 0 => $avg(),
                default => max(0.0, $avg()),
            };
        }

        return $m->qty > 0 && $m->balance_qty > 0 ? $avg() : $prevAvg;
    }

    /** @return list<string> */
    private static function documentProblems(): array
    {
        $out = [];
        $moves = [];   // doc => batch => [asal, netto]
        foreach (StockMovement::query()->selectRaw('stock_document_id as d, batch_id as b, sum(case when reversal_of is null then qty else 0 end) as orig, sum(qty) as net')->groupBy('stock_document_id', 'batch_id')->get() as $r) {
            $moves[$r->d][$r->b] = [round((float) $r->orig, Decimal::QTY_SCALE), round((float) $r->net, Decimal::QTY_SCALE)];
        }
        $lines = [];
        foreach (StockDocumentLine::query()->selectRaw('stock_document_id as d, batch_id as b, sum(qty) as q')->groupBy('stock_document_id', 'batch_id')->get() as $r) {
            $lines[$r->d][$r->b] = (float) $r->q;
        }

        foreach (StockDocument::query()->get(['id', 'doc_no', 'type', 'status', 'reversal_of_document_id']) as $d) {
            if ($d->reversal_of_document_id) {
                continue;   // dokumen pembalik otomatis: catatan, pergerakannya baris pembalik di dokumen asal
            }
            $got = $moves[$d->id] ?? [];
            if (in_array($d->status, ['confirmed', 'approved', 'cancel_pending'], true)) {
                $want = [];
                foreach ($lines[$d->id] ?? [] as $batch => $q) {
                    $signed = round($d->type === StockDocumentType::Issuance ? -$q : $q, Decimal::QTY_SCALE);
                    if (abs($signed) >= Decimal::QTY_EPS) {
                        $want[$batch] = $signed;
                    }
                }
                foreach ($want + array_map(fn ($g) => $g[0], $got) as $batch => $_) {
                    if (abs(($want[$batch] ?? 0.0) - ($got[$batch][0] ?? 0.0)) > Decimal::QTY_EPS) {
                        $out[] = "{$d->doc_no}: batch #{$batch} seharusnya ".($want[$batch] ?? 0).' menurut baris dokumen, buku besar '.($got[$batch][0] ?? 0).'.';
                    }
                }
            } elseif ($d->status === 'cancelled') {
                foreach ($got as $batch => [$orig, $net]) {
                    if (abs($net) > Decimal::QTY_EPS) {
                        $out[] = "{$d->doc_no} dibatalkan tetapi batch #{$batch} netto {$net}, bukan 0.";
                    }
                }
            } elseif ($got) {
                $out[] = "{$d->doc_no} berstatus {$d->status} tetapi punya pergerakan stok.";
            }
        }

        return $out;
    }
}
