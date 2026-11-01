<?php

namespace App\Services;

use App\Enums\StockDocumentType;
use App\Models\Department;
use App\Models\Batch;
use App\Models\Item;
use App\Models\Recipient;
use App\Models\StockCount;
use App\Models\StockDocument;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Decimal;
use App\Support\UnitConverter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/* REMARK
 * Tujuan       : Siklus hidup dokumen stok: draf (stok belum berubah) > Confirm (diposting ke stok, terkunci) > Cancel (pengajuan + persetujuan Supervisor, stok dibalik) ; koreksi lewat penyesuaian.
 *                Penyesuaian: draft -> approval -> approved (diposting) | rejected, hanya supervisor.
 * Sumber hulu  : data form yang sudah divalidasi (header + lines), StockLedger.
 * Output       : StockDocument beserta baris; untuk keluar: item yang kini di/bawah Reorder Point.
 * Dipakai oleh : StockDocumentController.
 * Status       : Aktif.
 */
final class StockDocuments
{
    public function __construct(private readonly StockLedger $ledger, private readonly ReorderOrders $reorders) {}

    /**
     * Dokumen baru SELALU berstatus draf dan belum menyentuh stok; stok baru diproses saat Confirm (penyesuaian: saat Approve).
     *
     * @param  array<string, mixed>  $header  supplier_id, ref_no, recipient, department, reason, note, doc_at (hanya saldo awal)
     * @param  array<int, array<string, mixed>>  $lines  batch_id, qty, unit_cost, counted_qty, note
     */
    public function create(StockDocumentType $type, array $header, array $lines, User $user): StockDocument
    {
        $header = $this->withNames($type, $header);

        return DB::transaction(function () use ($type, $header, $lines, $user) {
            $doc = $this->createNumbered($type, [
                ...$header,
                'type' => $type,
                'doc_at' => $type === StockDocumentType::Opening ? $header['doc_at'] : now(),
                'status' => 'draft',
                'created_by' => $user->id,
            ]);
            $this->writeLines($doc, $lines);

            return $doc;
        });
    }

    /**
     * Confirm = diproses ke stok (saldo awal, masuk, keluar). Tanggal / jam dokumen = saat Confirm (saldo awal: tanggal yang dipilih).
     *
     * @return array<int, Item> produk yang kini di/bawah Reorder Point
     */
    public function confirm(StockDocument $doc, User $user): array
    {
        abort_if($doc->type === StockDocumentType::Adjustment, 422, 'Penyesuaian diajukan lalu disetujui Supervisor.');

        return DB::transaction(function () use ($doc, $user) {
            $this->lockAndAssert($doc, ['draft']);
            abort_if($doc->lines()->doesntExist(), 422, 'Tambahkan minimal satu baris sebelum Confirm.');
            if ($doc->type !== StockDocumentType::Opening) {
                $doc->update(['doc_at' => now()]);
            }
            $below = $this->ledger->post($doc->refresh(), $user);
            $doc->update(['status' => 'confirmed', 'confirmed_by' => $user->id, 'confirmed_at' => now()]);
            $this->reorders->reconcileDocument($doc);   // penerimaan menambah jumlah diterima pesanan; menutup hanya bila jumlah dipesan terpenuhi

            return $below;
        });
    }

    /**
     * Cancel: pengguna MENGAJUKAN pembatalan (alasan wajib) yang menunggu persetujuan Supervisor; Supervisor yang menekan Cancel membatalkan langsung.
     * Ditolak lebih dulu bila stok tidak cukup untuk menarik dokumen kembali (koreksi lewat Stock Adjustment). Draf dihapus, bukan dibatalkan.
     * Penyesuaian yang baru diajukan (belum disetujui) hanya ditarik; pengajuan pembatalan yang menunggu bisa ditarik kembali.
     *
     * @return string requested | cancelled | withdrawn
     */
    public function cancel(StockDocument $doc, User $user, string $reason): string
    {
        $reason = mb_substr(trim($reason), 0, 255);

        return DB::transaction(function () use ($doc, $user, $reason) {
            $this->lockAndAssert($doc, ['confirmed', 'approved', 'approval', 'cancel_pending']);
            abort_if($doc->reversal_of_document_id, 422, 'Dokumen pembalik otomatis tidak bisa dibatalkan.');

            if ($doc->status === 'cancel_pending') {
                $doc->update($this->backToProcessed($doc) + ['cancel_reason' => null, 'cancel_requested_by' => null, 'cancel_requested_at' => null]);

                return 'withdrawn';
            }
            abort_if(mb_strlen($reason) < 3, 422, 'Isi alasan pembatalan (minimal 3 huruf).');

            if ($doc->status === 'approval') {
                $this->finishCancel($doc, $user, $reason);

                return 'cancelled';
            }
            $this->ledger->assertReversible($doc);
            if ($user->isSupervisor()) {
                $this->finishCancel($doc, $user, $reason);

                return 'cancelled';
            }
            $doc->update(['status' => 'cancel_pending', 'cancel_reason' => $reason, 'cancel_requested_by' => $user->id, 'cancel_requested_at' => now()]);

            return 'requested';
        });
    }

    /** Hasil hitungan "apakah stok masih cukup untuk menarik dokumen ini kembali": pesan masalahnya, atau null bila bisa dibatalkan. */
    public function cancelProblem(StockDocument $doc): ?string
    {
        if (! in_array($doc->status, ['confirmed', 'approved'], true)) {
            return null;
        }
        try {
            $this->ledger->assertReversible($doc);
        } catch (ValidationException $e) {
            return $e->errors()['lines'][0] ?? 'Pembatalan tidak bisa dilakukan.';
        }

        return null;
    }

    // Supervisor menyetujui pengajuan pembatalan: stok dibalik (diperiksa ulang), dokumen dibatalkan.
    public function approveCancel(StockDocument $doc, User $approver): void
    {
        DB::transaction(function () use ($doc, $approver) {
            $this->lockAndAssert($doc, ['cancel_pending']);
            $this->finishCancel($doc, $approver, (string) $doc->cancel_reason);
        });
    }

    // Supervisor menolak pengajuan: dokumen kembali berstatus sebelumnya, stok tidak berubah.
    public function rejectCancel(StockDocument $doc): void
    {
        DB::transaction(function () use ($doc) {
            $this->lockAndAssert($doc, ['cancel_pending']);
            $doc->update($this->backToProcessed($doc) + ['cancel_reason' => null, 'cancel_requested_by' => null, 'cancel_requested_at' => null]);
        });
    }

    private function backToProcessed(StockDocument $doc): array
    {
        return ['status' => $doc->type === StockDocumentType::Adjustment ? 'approved' : 'confirmed'];
    }

    private function finishCancel(StockDocument $doc, User $by, string $reason): void
    {
        DB::transaction(function () use ($doc, $by, $reason) {
            if (in_array($doc->status, ['confirmed', 'approved', 'cancel_pending'], true)) {
                $this->recordReversal($doc, $by, $reason, $this->ledger->reverse($doc, $by));
            }
            $doc->update(['status' => 'cancelled', 'cancelled_by' => $by->id, 'cancelled_at' => now(), 'cancel_reason' => $reason]);
            $this->reorders->reconcileDocument($doc);   // penerimaan yang dibatalkan tidak dihitung lagi: pesanan yang jumlahnya jadi kurang terbuka kembali
        });
    }

    /**
     * Pembatalan dokumen yang sudah diproses: dokumen ADJUSTMENT pembalik dibuat otomatis (langsung disetujui, menunjuk dokumen asal) berisi selisih stok akibat pembalikan.
     * Pergerakan stoknya tetap baris reversal_of di dokumen asal (Kartu Stok tidak berubah); dokumen ini catatan yang tersimpan dan bisa dicetak.
     *
     * @param  array<int, StockMovement>  $moves
     */
    private function recordReversal(StockDocument $original, User $by, string $reason, array $moves): void
    {
        if (! $moves) {
            return;
        }
        $adj = $this->createNumbered(StockDocumentType::Adjustment, [
            'type' => StockDocumentType::Adjustment, 'doc_at' => now(), 'status' => 'approved',
            'reason' => 'Pembalik otomatis pembatalan '.$original->doc_no, 'note' => $reason, 'created_by' => $by->id, 'confirmed_by' => $by->id, 'confirmed_at' => now(),
            'approved_by' => $by->id, 'approved_at' => now(), 'reversal_of_document_id' => $original->id,
        ]);
        foreach ($moves as $m) {
            $adj->lines()->create(['batch_id' => $m->batch_id, 'qty' => $m->qty, 'unit_cost' => $m->unit_cost, 'system_qty' => round($m->balance_qty - $m->qty, 3), 'counted_qty' => $m->balance_qty]);
        }
    }

    /**
     * Penyesuaian OTOMATIS hasil Stok Opname: satu dokumen ADJUSTMENT langsung berstatus approved (reason "Stok Opname SO-..."), diposting lewat buku besar yang sama
     * (kunci baris item dan pemeriksaan saldo ikut). Dipanggil dalam transaksi persetujuan opname; kegagalan posting membatalkan seluruh persetujuan.
     *
     * @param  array<int, array{batch_id: int, qty: float, system_qty: float, counted_qty: float, unit_cost: float}>  $rows  hanya baris selisih
     */
    public function recordCountAdjustment(StockCount $count, array $rows, User $by): StockDocument
    {
        return DB::transaction(function () use ($count, $rows, $by) {
            $adj = $this->createNumbered(StockDocumentType::Adjustment, [
                'type' => StockDocumentType::Adjustment, 'doc_at' => now(), 'status' => 'approved', 'reason' => 'Stok Opname '.$count->doc_no, 'note' => $count->note,
                'created_by' => $by->id, 'confirmed_by' => $by->id, 'confirmed_at' => now(), 'approved_by' => $by->id, 'approved_at' => now(),
            ]);
            foreach ($rows as $row) {
                $adj->lines()->create($row);
            }
            $this->ledger->post($adj, $by, now());

            return $adj;
        });
    }

    // Semua jenis dokumen: departemen kosong -> departemen bawaan penerima. Nama master disalin ke kolom teks dokumen (tetap terbaca bila master diubah).
    private function withNames(StockDocumentType $type, array $header): array
    {
        $recipient = isset($header['recipient_id']) ? Recipient::find($header['recipient_id']) : null;
        $header['department_id'] = $header['department_id'] ?? $recipient?->department_id;
        $header['recipient'] = $recipient?->name ?? $header['recipient'] ?? null;
        $header['department'] = isset($header['department_id']) ? Department::find($header['department_id'])?->name : ($header['department'] ?? null);

        return $header;
    }

    // Hanya draf yang boleh diubah (semua jenis).
    public function update(StockDocument $doc, array $header, array $lines): void
    {
        $header = $this->withNames($doc->type, $header);
        DB::transaction(function () use ($doc, $header, $lines) {
            $this->lockAndAssert($doc, ['draft']);
            $old = $doc->lines()->pluck('batch_id')->all();
            $doc->update($header);
            $doc->lines()->delete();
            $this->writeLines($doc, $lines);
            $this->purgeUnusedBatches($old);
        });
    }

    public function submit(StockDocument $doc): void
    {
        DB::transaction(function () use ($doc) {
            $this->lockAndAssert($doc, ['draft'], StockDocumentType::Adjustment);
            abort_if($doc->lines()->doesntExist(), 422, 'Tambahkan minimal satu baris sebelum konfirmasi.');
            $doc->update(['status' => 'approval']);
        });
    }

    public function approve(StockDocument $doc, User $approver): void
    {
        DB::transaction(function () use ($doc, $approver) {
            $this->lockAndAssert($doc, ['approval'], StockDocumentType::Adjustment);
            // Batch dikunci DULU, baru dibandingkan: stok tidak bisa berubah antara pemeriksaan dan posting.
            $locked = Batch::query()->whereIn('id', $doc->lines()->pluck('batch_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($doc->lines()->get() as $line) {
                $line->setRelation('batch', $locked[$line->batch_id]);
                if (abs($line->batch->stock_qty - (float) $line->system_qty) > Decimal::QTY_EPS) {
                    throw ValidationException::withMessages(['lines' => "Stok {$line->batch->code} berubah sejak penghitungan (sistem {$line->system_qty}, sekarang {$line->batch->stock_qty}). Tolak dokumen ini lalu hitung ulang."]);
                }
            }
            $this->ledger->post($doc, $approver, now());
            $doc->update(['status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now()]);
        });
    }

    public function reject(StockDocument $doc, User $approver): void
    {
        DB::transaction(function () use ($doc, $approver) {
            $this->lockAndAssert($doc, ['approval'], StockDocumentType::Adjustment);
            $doc->update(['status' => 'rejected', 'approved_by' => $approver->id, 'approved_at' => now()]);
        });
    }

    public function delete(StockDocument $doc): void
    {
        DB::transaction(function () use ($doc) {
            $this->lockAndAssert($doc, ['draft']);
            $old = $doc->lines()->pluck('batch_id')->all();
            $doc->delete();
            $this->purgeUnusedBatches($old);
        });
    }

    // Batch berkedaluwarsa yang dibuat oleh draf lalu tidak jadi dipakai (tanpa stok, baris, atau pergerakan) dibuang supaya daftar batch tidak berisi sisa.
    private function purgeUnusedBatches(array $itemIds): void
    {
        Batch::query()->whereIn('id', $itemIds)->whereNotNull('expiry_date')->where('stock_qty', 0)
            ->whereDoesntHave('lines')->whereDoesntHave('movements')->delete();
    }

    /**
     * Peringatan urutan keluar (FEFO): baris Pengeluaran memakai batch yang kedaluwarsanya lebih jauh padahal batch yang lebih cepat kedaluwarsa masih punya stok. Hanya peringatan, tidak memblokir.
     *
     * @return array<int, string>
     */
    public function fefoWarnings(StockDocument $doc): array
    {
        if ($doc->type !== StockDocumentType::Issuance) {
            return [];
        }
        $out = [];
        $lines = $doc->lines()->with('batch')->get();
        $taken = $lines->groupBy('batch_id')->map(fn ($l) => (float) $l->sum('qty'));  // yang diambil dokumen ini sendiri (termasuk sambungan otomatis antar batch)
        // Batch saudara (barang sama, ada stok, berkedaluwarsa) dimuat sekali untuk semua baris, bukan satu query per baris.
        $siblings = Batch::query()->whereIn('item_id', $lines->pluck('batch.item_id')->filter()->unique())->where('stock_qty', '>', 0)->whereNotNull('expiry_date')->orderBy('expiry_date')->get()->groupBy('item_id');
        foreach ($lines as $line) {
            $item = $line->batch;
            if (! $item->expiry_date) {
                continue;
            }
            $earlier = ($siblings[$item->item_id] ?? collect())->filter(fn (Batch $e) => $e->id !== $item->id && $e->expiry_date < $item->expiry_date)
                ->first(fn (Batch $e) => $e->stock_qty - ($taken[$e->id] ?? 0) > Decimal::QTY_EPS);
            if ($earlier) {
                $left = $earlier->stock_qty - ($taken[$earlier->id] ?? 0);
                $out[] = "{$item->name}: batch {$item->code} (kedaluwarsa ".$item->expiry_date->format('d-m-Y').") dipilih, padahal batch {$earlier->code} (kedaluwarsa ".$earlier->expiry_date->format('d-m-Y').') masih ada stok '.rtrim(rtrim(number_format($left, 3, ',', '.'), '0'), ',').'. Keluarkan yang kedaluwarsa lebih dulu.';
            }
        }

        return array_values(array_unique($out));
    }

    // Dipanggil di awal transaksi: baca ulang baris dokumen dengan kunci (permintaan lain menunggu) lalu periksa status pada data terbaru, bukan model yang dimuat sebelum transaksi.
    private function lockAndAssert(StockDocument $doc, array $allowed, ?StockDocumentType $only = null): void
    {
        $doc->setRawAttributes(StockDocument::query()->lockForUpdate()->findOrFail($doc->id)->getAttributes(), true);
        $this->assertStatus($doc, $allowed, $only);
    }

    private function assertStatus(StockDocument $doc, array $allowed, ?StockDocumentType $only = null): void
    {
        abort_unless(($only === null || $doc->type === $only) && in_array($doc->status, $allowed, true), 422, 'Aksi ini tidak diizinkan pada status sekarang.');
    }

    // Baris menyebut batch langsung (batch_id) atau produk + tanggal kedaluwarsa (item_id, expiry_date: batch dibuat bila belum ada).
    private function resolveItem(array $row): Batch
    {
        if (! empty($row['batch_id'])) {
            return Batch::query()->findOrFail($row['batch_id']);
        }
        $product = Item::query()->findOrFail($row['item_id']);
        if ($product->tracks_expiry && empty($row['expiry_date'])) {
            throw ValidationException::withMessages(['lines' => "Isi tanggal kedaluwarsa untuk {$product->name}."]);
        }

        return $product->batchFor($row['expiry_date'] ?? null);
    }

    /**
     * Pengeluaran: jumlah melebihi stok batch yang dipilih disambung ke batch lain produk yang sama menurut FEFO (kedaluwarsa paling cepat dulu, lalu yang paling lama masuk).
     * Bila seluruh stok layak barang itu pun tidak cukup, baris TIDAK dipecah: tetap satu baris sesuai ketikan pengguna di batch pilihan (draf tidak ditulis ulang),
     * dan Confirm menolaknya dengan pesan "tersedia X, dibutuhkan Y".
     *
     * @return array<int, array{0: Batch, 1: float}>
     */
    private function allocate(Batch $first, float $qty): array
    {
        $take = min($qty, max($first->stock_qty, 0));
        $left = round($qty - $take, Decimal::QTY_SCALE);
        if ($left <= 0 || ! $first->item_id) {
            return [[$first, $left > 0 ? $qty : $take]];
        }
        $others = Batch::query()->where('item_id', $first->item_id)->where('id', '!=', $first->id)->where('stock_qty', '>', 0)->usable()
            ->orderByRaw('expiry_date is null')->orderBy('expiry_date')->orderBy('id')->get();
        if ($others->sum('stock_qty') + Decimal::QTY_EPS < $left) {
            return [[$first, $qty]];
        }
        $parts = $take > 0 ? [[$first, $take]] : [];
        foreach ($others as $o) {
            $t = min($left, $o->stock_qty);
            $parts[] = [$o, $t];
            $left = round($left - $t, Decimal::QTY_SCALE);
            if ($left <= 0) {
                break;
            }
        }

        return $parts;
    }

    /**
     * Hanya Penerimaan yang boleh memakai satuan selain satuan stok barang (Pengeluaran dan Penyesuaian selalu dalam satuan stok). Faktornya: satuan masuk milik barang, lalu konversi global;
     * bila tidak ada keduanya, semua pasangan yang belum ada dikumpulkan (bukan satu per satu) dan dikirim ke form lewat flash `missing_conversions`
     * supaya layar menampilkan jendela isi faktor (jendela itu membuat satuan masuk milik BARANG, bukan konversi global).
     */
    private function assertConversions(StockDocumentType $type, array $lines): void
    {
        if ($type !== StockDocumentType::Receiving) {
            return;
        }
        $missing = [];
        foreach ($lines as $row) {
            $from = $row['unit'] ?? null;
            $stock = ! empty($row['batch_id']) ? Batch::query()->find($row['batch_id']) : (! empty($row['item_id']) ? Item::query()->find($row['item_id']) : null);
            $productId = $stock instanceof Batch ? $stock->item_id : $stock?->id;
            if ($from && $stock && $from !== $stock->unit && UnitConverter::factor($from, $stock->unit, $productId) === null) {
                $missing["{$productId}>{$from}"] = ['item_id' => $productId, 'from' => $from, 'to' => $stock->unit, 'items' => $productId ? Item::query()->whereKey($productId)->value('code') : $stock->code];
            }
        }
        if ($missing) {
            $pairs = array_values($missing);
            session()->flash('missing_conversions', $pairs);

            throw ValidationException::withMessages(['lines' => 'Belum ada faktor satuan: '.collect($pairs)->map(fn ($p) => "{$p['from']} ke {$p['to']} ({$p['items']})")->implode('; ').'. Isi faktornya di jendela yang muncul.']);
        }
    }

    private function writeLines(StockDocument $doc, array $lines): void
    {
        $this->assertConversions($doc->type, $lines);
        foreach ($lines as $row) {
            $item = $this->resolveItem($row);
            $isAdjustment = $doc->type === StockDocumentType::Adjustment;
            $qty = $isAdjustment ? round((float) $row['counted_qty'] - $item->stock_qty, Decimal::QTY_SCALE) : $row['qty'];
            if ($isAdjustment && abs($qty) < Decimal::QTY_EPS) {
                throw ValidationException::withMessages(['lines' => "Jumlah hitung {$item->code} sama dengan stok sistem (".Decimal::fmtQty($item->stock_qty).'): tidak ada yang disesuaikan. Ubah jumlah hitungnya atau hapus barang itu dari baris.']);
            }
            $cost = $row['unit_cost'] ?? null;
            $entered = [];

            // Penerimaan dalam satuan lain: simpan yang diketik, proses ke stok dalam satuan stok item. Jenis lain selalu satuan stok (isian unit diabaikan).
            if ($doc->type === StockDocumentType::Receiving && ($row['unit'] ?? $item->unit) !== $item->unit) {
                [$qty, $cost, $entered] = $this->toStockUnit($item, $row);
            }

            $parts = $doc->type === StockDocumentType::Issuance ? $this->allocate($item, (float) $qty) : [[$item, $qty]];
            foreach ($parts as [$target, $partQty]) {
                $doc->lines()->create([
                    'batch_id' => $target->id,
                    'qty' => $partQty,
                    'unit_cost' => $cost,
                    'system_qty' => $isAdjustment ? $target->stock_qty : null,
                    'counted_qty' => $isAdjustment ? $row['counted_qty'] : null,
                    'note' => $row['note'] ?? null,
                ] + $entered);
            }
        }
    }

    /** @return array{0: float, 1: ?float, 2: array<string, mixed>} qty dan biaya per satuan stok + data yang diketik */
    private function toStockUnit(Batch $item, array $row): array
    {
        if (UnitConverter::factor($row['unit'], $item->unit, $item->item_id) === null) {
            throw ValidationException::withMessages(['lines' => "Belum ada faktor dari {$row['unit']} ke {$item->unit} untuk {$item->code}. Isi di bagian Satuan masuk pada barangnya."]);
        }
        $qty = Decimal::round(UnitConverter::exact(Decimal::of($row['qty']), $row['unit'], $item->unit, false, $item->item_id), 3);
        if ($qty <= 0) {
            throw ValidationException::withMessages(['lines' => "Jumlah {$item->code} terlalu kecil untuk dikonversi ke {$item->unit}."]);
        }

        $cost = $row['unit_cost'] ?? null;

        return [$qty, $cost === null ? null : Decimal::round(UnitConverter::exact(Decimal::of($cost), $row['unit'], $item->unit, true, $item->item_id), 4), ['entered_qty' => $row['qty'], 'entered_unit' => $row['unit'], 'entered_unit_cost' => $cost]];
    }

    // Dua penyimpan bersamaan bisa membaca nomor terakhir yang sama: kolom unik menolak yang kalah, lalu nomor berikutnya diambil lagi.
    private function createNumbered(StockDocumentType $type, array $attributes): StockDocument
    {
        for ($try = 1; ; $try++) {
            $number = $this->nextNumber($type, $try > 1);
            try {
                return DB::transaction(fn () => StockDocument::create($attributes + ['doc_no' => $number]));
            } catch (UniqueConstraintViolationException $e) {
                if ($try >= 5) {
                    throw $e;
                }
            }
        }
    }

    // PREFIX-YYMM-NNNN, urut per jenis per bulan; kolom doc_no unik menjaga dari nomor ganda.
    // $fresh (ulangan sesudah tabrakan): baca terkunci supaya yang terbaca nomor yang sudah di-commit penyimpan lain; bacaan biasa di MySQL (REPEATABLE READ) memakai snapshot lama sehingga nomor yang sama terulang.
    private function nextNumber(StockDocumentType $type, bool $fresh = false): string
    {
        $stem = $type->prefix().'-'.now()->format('ym').'-';
        $last = StockDocument::query()->where('doc_no', 'like', $stem.'%')->orderByDesc('doc_no')->when($fresh, fn ($q) => $q->lockForUpdate())->value('doc_no');

        return $stem.str_pad((string) ($last ? (int) substr($last, -4) + 1 : 1), 4, '0', STR_PAD_LEFT);
    }
}
