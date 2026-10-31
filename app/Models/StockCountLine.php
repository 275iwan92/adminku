<?php

namespace App\Models;

use App\Support\Decimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['stock_count_id', 'item_id', 'system_qty', 'unit_cost', 'counted_qty', 'recount_qty', 'counted_by', 'entered_by', 'entered_at', 'rev', 'skipped', 'reason', 'note', 'added_manually'])]
class StockCountLine extends Model
{
    protected $table = 'i_count_line';

    protected function casts(): array
    {
        return ['system_qty' => 'float', 'unit_cost' => 'float', 'counted_qty' => 'float', 'recount_qty' => 'float', 'skipped' => 'boolean', 'added_manually' => 'boolean', 'entered_at' => 'datetime', 'rev' => 'integer'];
    }

    public function count(): BelongsTo
    {
        return $this->belongsTo(StockCount::class, 'stock_count_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    // Hasil akhir: hitung ulang bila ada, selain itu hasil hitung; null = tidak ada hasil (belum diisi atau Tidak dihitung).
    public function finalQty(): ?float
    {
        return $this->recount_qty ?? ($this->skipped ? null : $this->counted_qty);
    }

    // Kosong = belum dihitung (bukan nol); baris selesai bila ada hasil atau ditandai Tidak dihitung.
    public function isFilled(): bool
    {
        return $this->skipped || $this->counted_qty !== null || $this->recount_qty !== null;
    }

    // Tidak dihitung tanpa catatan (dan tanpa hitung ulang) belum boleh dianggap selesai.
    public function isComplete(): bool
    {
        return $this->isFilled() && ! ($this->skipped && $this->recount_qty === null && blank($this->note));
    }

    // Selisih jumlah = fisik - sistem; baris tanpa hasil tidak menyumbang selisih.
    public function diff(): float
    {
        $final = $this->finalQty();

        return $final === null ? 0.0 : round($final - $this->system_qty, 3);
    }

    // Persen selisih terhadap stok sistem (bertanda); null bila stok sistem nol (tidak ada dasar persen).
    public function pct(): ?float
    {
        if ($this->system_qty <= 0) {
            return null;
        }
        $pct = Decimal::round(Decimal::div(Decimal::mul(Decimal::of($this->diff()), '100'), Decimal::of($this->system_qty)), 2);

        return $pct == 0 ? 0.0 : $pct;
    }

    // Nilai selisih = selisih x biaya rata-rata saat cut-off.
    public function value(): float
    {
        return Decimal::amount($this->diff(), $this->unit_cost);
    }

    // |selisih| > ambang persen. Dibanding tanpa pembulatan (|selisih| x 100 > ambang x sistem); stok sistem nol dan ada selisih selalu melewati ambang.
    public function overThreshold(float $thresholdPct): bool
    {
        $diff = abs($this->diff());
        if ($diff == 0.0) {
            return false;
        }

        return $this->system_qty <= 0 || bccomp(Decimal::mul(Decimal::of($diff), '100'), Decimal::mul(Decimal::of($thresholdPct), Decimal::of($this->system_qty)), 20) > 0;
    }
}
