<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

// is_system = supplier bawaan (Kontan): tak bisa dinonaktifkan, diganti nama / kodenya, atau dihapus; selalu pertama di dropdown. Hanya diisi migrasi / seeder (tidak mass-assignable).
// phone = telepon kantor (PSTN); contact_mobile = HP kontak; address = nama jalan; area_level + area_id = wilayah (lihat App\Support\GeoArea).
#[Fillable(['code', 'name', 'phone', 'contact_name', 'contact_mobile', 'email', 'payment_term_days', 'note', 'address', 'area_level', 'area_id', 'postal_code', 'is_active'])]
class Supplier extends Model
{
    protected $table = 'm_supplier';

    public const SYSTEM_MESSAGE = 'Kontan adalah pemasok sistem: tidak bisa dinonaktifkan, diganti nama, atau dihapus.';

    protected static function booted(): void
    {
        static::deleting(fn (Supplier $s) => $s->is_system ? throw new LogicException(self::SYSTEM_MESSAGE) : null);
    }

    // Kode berikutnya SUP-001, SUP-002, ... (tambah cepat dari dropdown; kode lain yang tidak berpola diabaikan).
    public static function nextCode(): string
    {
        $max = static::query()->where('code', 'like', 'SUP-%')->pluck('code')->map(fn ($c) => ctype_digit(substr($c, 4)) ? (int) substr($c, 4) : 0)->max() ?? 0;

        return sprintf('SUP-%03d', $max + 1);
    }

    // Buat supplier dengan kode otomatis; ulang bila kode tabrakan dengan pembuat lain (kolom unik).
    public static function createQuick(array $data): static
    {
        for ($try = 1; ; $try++) {
            try {
                return static::create($data + ['code' => static::nextCode()]);
            } catch (UniqueConstraintViolationException $e) {
                if ($try >= 5) {
                    throw $e;
                }
            }
        }
    }

    // Urutan semua dropdown supplier: pemasok sistem (Kontan) dulu, lalu nama.
    public function scopeForSelect(Builder $query): Builder
    {
        return $query->orderByDesc('is_system')->orderBy('name');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_system' => 'boolean', 'area_id' => 'integer', 'payment_term_days' => 'integer'];
    }

    public function termLabel(): string
    {
        return $this->payment_term_days === null ? '-' : ($this->payment_term_days === 0 ? 'Tunai' : $this->payment_term_days.' hari');
    }

    // Riwayat beli per barang dari Penerimaan terkonfirmasi (tidak dibatalkan): terakhir beli, harga terakhir, berapa kali (jumlah dokumen). Satuan = satuan stok barang.
    public function purchaseHistory(): Collection
    {
        return DB::table('rm_stockcard as c')
            ->join('rm_stock as d', 'd.id', '=', 'c.stock_document_id')->join('i_batch as b', 'b.id', '=', 'c.batch_id')->join('m_item as i', 'i.id', '=', 'b.item_id')
            ->where('d.supplier_id', $this->id)->where('c.type', 'IN')->whereNull('c.reversal_of')->whereIn('d.status', ['confirmed', 'cancel_pending'])
            ->orderByDesc('c.moved_at')->orderByDesc('c.id')
            ->get(['i.id as item_id', 'i.code', 'i.name', 'i.unit', 'c.stock_document_id', 'c.moved_at', 'c.unit_cost'])
            ->groupBy('item_id')->map(fn ($g) => (object) ['code' => $g[0]->code, 'name' => $g[0]->name, 'unit' => $g[0]->unit, 'last_at' => $g[0]->moved_at, 'last_price' => $g[0]->unit_cost, 'times' => $g->pluck('stock_document_id')->unique()->count()])
            ->sortByDesc('last_at')->values();
    }

    // Kunci wilayah "LEVEL:id" untuk GeoArea / kotak cari.
    public function areaKey(): ?string
    {
        return $this->area_level && $this->area_id ? $this->area_level.':'.$this->area_id : null;
    }
}
