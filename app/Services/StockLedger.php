<?php

namespace App\Services;

use App\Enums\StockDocumentType;
use App\Models\Item;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockDocument;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/* REMARK
 * Tujuan       : Satu-satunya pintu yang mengubah saldo stok: memposting baris dokumen ke buku besar rm_stockcard dan memperbarui items.stock_qty / avg_cost (moving average).
 * Sumber hulu  : rm_stock + rm_stock_line (qty, unit_cost, selisih penyesuaian).
 * Output       : baris rm_stockcard (dengan saldo sesudah transaksi); daftar item yang menyentuh Reorder Point.
 * Dipakai oleh : StockDocuments (masuk, keluar, saldo awal, persetujuan penyesuaian, pembatalan); penjaga kunci Stok Opname (StockCount::lockingNow) ada di sini.
 * Status       : Aktif.
 */
final class StockLedger
{
    private const EPS = 0.0005;

    /**
     * @return array<int, Product> produk yang SESUDAH posting berada di/bawah Reorder Point
     */
    public function post(StockDocument $doc, User $user, ?\DateTimeInterface $movedAt = null): array
    {
        $movedAt ??= $doc->doc_at;
        $touched = [];

        DB::transaction(function () use ($doc, $user, $movedAt, &$touched) {
            $this->assertNotLocked();
            $this->lockItems($doc);
            foreach ($doc->lines()->orderBy('id')->get() as $line) {
                $item = Item::query()->lockForUpdate()->findOrFail($line->item_id);
                $signed = $doc->type->signed((float) $line->qty);
                if (abs($signed) < self::EPS) {
                    continue;  // hasil opname sama dengan sistem: tidak ada pergerakan
                }
                $balance = round($item->stock_qty + $signed, 3);

                if ($balance < -self::EPS) {
                    throw ValidationException::withMessages(['lines' => "Stok {$item->code} {$item->name} tidak cukup: tersedia ".rtrim(rtrim(number_format($item->stock_qty, 3, '.', ''), '0'), '.').', dibutuhkan '.rtrim(rtrim(number_format(abs($signed), 3, '.', ''), '0'), '.').'.']);
                }
                if ($doc->type === StockDocumentType::Opening && $item->movements()->exists()) {
                    throw ValidationException::withMessages(['lines' => "{$item->code} sudah punya pergerakan stok; pakai Penyesuaian Stok, bukan Saldo Awal."]);
                }

                // Penambahan memakai biaya baris (masuk / saldo awal) atau biaya rata-rata (penyesuaian +); pengurangan memakai biaya rata-rata.
                $cost = match (true) {
                    $signed > 0 && in_array($doc->type, [StockDocumentType::Receiving, StockDocumentType::Opening], true) => (float) $line->unit_cost,
                    $signed > 0 => $item->avg_cost > 0 ? $item->avg_cost : $item->unit_price,
                    default => $item->avg_cost,
                };
                $avg = $signed > 0 && $balance > 0 ? $this->movingAverage($item, $signed, $cost, $balance) : $item->avg_cost;

                $line->update(['unit_cost' => $cost]);
                // Saldo sengaja tidak mass-assignable di Item: hanya buku besar ini yang boleh mengubahnya.
                $item->forceFill(['stock_qty' => $balance, 'avg_cost' => $avg])->save();
                StockMovement::create([
                    'item_id' => $item->id,
                    'stock_document_id' => $doc->id,
                    'type' => $doc->type->value,
                    'moved_at' => $movedAt,
                    'qty' => $signed,
                    'unit_cost' => $cost,
                    'balance_qty' => $balance,
                    'balance_avg_cost' => $avg,
                    'user_id' => $user->id,
                ]);

                // Pesan ulang dihitung per produk (stok tersedia seluruh batch), bukan per batch.
                $product = $item->product;
                if ($product && ! isset($touched[$product->id]) && $product->isBelowReorderPoint()) {
                    $touched[$product->id] = $product;
                }
            }
        });

        return array_values($touched);
    }

    /**
     * Membatalkan posting dokumen: tiap baris buku besar dibalik dengan baris baru (baris asal tidak diubah / dihapus).
     * Pembalikan masuk menurunkan saldo (ditolak bila stok tidak cukup); pembalikan keluar mengembalikan saldo pada biaya saat itu.
     *
     * @return array<int, StockMovement> baris pembalik yang baru dibuat
     */
    public function reverse(StockDocument $doc, User $user): array
    {
        return DB::transaction(function () use ($doc, $user) {
            $made = [];
            $this->assertNotLocked();
            $this->lockItems($doc);
            $moves = $this->unreversed($doc)->get();
            foreach ($moves as $m) {
                $item = Item::query()->lockForUpdate()->findOrFail($m->item_id);
                $signed = -(float) $m->qty;
                $balance = round($item->stock_qty + $signed, 3);
                if ($balance < -self::EPS) {
                    throw $this->shortage($item, $signed);
                }
                $cost = (float) $m->unit_cost;
                $avg = match (true) {
                    $balance <= self::EPS => $item->avg_cost,
                    $signed > 0 => $this->movingAverage($item, $signed, $cost, $balance),
                    default => max(0, $this->movingAverage($item, $signed, $cost, $balance)),
                };
                $item->forceFill(['stock_qty' => $balance, 'avg_cost' => $avg])->save();
                $made[] = StockMovement::create([
                    'item_id' => $item->id, 'stock_document_id' => $doc->id, 'type' => $m->type, 'moved_at' => now(), 'qty' => $signed, 'unit_cost' => $cost,
                    'balance_qty' => $balance, 'balance_avg_cost' => $avg, 'user_id' => $user->id, 'reversal_of' => $m->id,
                ]);
            }

            return $made;
        });
    }

    /** Memeriksa tanpa mengubah apa pun: kalau pembatalan membuat stok negatif, lempar galat (koreksi harus lewat Stock Adjustment). */
    public function assertReversible(StockDocument $doc): void
    {
        $stock = [];
        foreach ($this->unreversed($doc)->get() as $m) {
            $item = Item::query()->findOrFail($m->item_id);
            $signed = -(float) $m->qty;
            $stock[$item->id] = round(($stock[$item->id] ?? $item->stock_qty) + $signed, 3);
            if ($stock[$item->id] < -self::EPS) {
                throw $this->shortage($item, $signed);
            }
        }
    }

    // Rata-rata bergerak = (nilai lama + nilai perubahan) / saldo baru, dihitung presisi dan dibulatkan sekali ke 4 desimal (skala kolom avg_cost). $signed negatif = nilai dikurangi.
    private function movingAverage(Item $item, float $signed, float $cost, float $balance): float
    {
        $value = Decimal::add(Decimal::mul(Decimal::of($item->stock_qty), Decimal::of($item->avg_cost)), Decimal::mul(Decimal::of($signed), Decimal::of($cost)));

        return Decimal::round(Decimal::div($value, Decimal::of($balance)), 4);
    }

    // Movement asal dokumen yang belum punya pembalik (pembatalan kedua tidak membalik ulang).
    private function unreversed(StockDocument $doc)
    {
        return StockMovement::query()->where('stock_document_id', $doc->id)->whereNull('reversal_of')
            ->whereNotExists(fn ($q) => $q->from('rm_stockcard as r')->whereColumn('r.reversal_of', 'rm_stockcard.id'))->orderByDesc('id');
    }

    // Satu-satunya penjaga kunci Stok Opname: selama opname berjalan dengan kunci aktif, tidak ada dokumen yang boleh memposting atau membalik stok (draf tetap boleh disimpan).
    // Persetujuan opname sendiri melepas kunci (status Selesai) di transaksi yang sama SEBELUM memposting Penyesuaiannya.
    private function assertNotLocked(): void
    {
        if ($count = StockCount::lockingNow()) {
            throw ValidationException::withMessages(['lines' => "Stok Opname {$count->doc_no} sedang berjalan, transaksi dikunci. Hubungi Supervisor."]);
        }
    }

    // Semua item dokumen dikunci sekaligus urut id: dokumen yang bersamaan memegang item yang sama dalam urutan sama, jadi tidak saling menunggu melingkar.
    private function lockItems(StockDocument $doc): void
    {
        Item::query()->whereIn('id', $doc->lines()->pluck('item_id'))->orderBy('id')->lockForUpdate()->get();
    }

    private function shortage(Item $item, float $signed): ValidationException
    {
        $fmt = fn (float $v) => rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');

        return ValidationException::withMessages(['lines' => "Pembatalan tidak bisa dilakukan: stok {$item->code} {$item->name} tinggal {$fmt($item->stock_qty)}, tidak cukup untuk menarik kembali {$fmt(abs($signed))}. Koreksi stok lewat Stock Adjustment."]);
    }
}
