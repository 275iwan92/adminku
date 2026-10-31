<?php

namespace App\Support;

use App\Models\Item;
use App\Models\Product;
use App\Services\ProductUnits;
use Devlib\Locale;
use Devlib\Lookup;
use Illuminate\Support\Arr;

/* REMARK
 * Tujuan       : Pilihan barang di baris dokumen (x-dl::lines) dicari ke server; memilih mengisi kolom satuan, stok tersedia, selisih sistem, dan harga acuan di baris itu.
 *                Penerimaan dan Saldo Awal memilih PRODUK (batch menurut tanggal kedaluwarsa dibuat otomatis). Pengeluaran dan Penyesuaian memilih BATCH (barang):
 *                daftar Pengeluaran hanya batch ber-stok yang belum kedaluwarsa, dengan kedaluwarsa paling cepat di atas (FEFO).
 * Sumber hulu  : m_item, items (hanya yang aktif). Harga acuan hanya untuk Supervisor.
 * Output       : Lookup product_in (Receiving), product_opn (Opening Balance), item_out (Issuance), item_adj (Stock Adjustment), item_count (Stok Opname: tambah barang, tanpa stok).
 * Dipakai oleh : AppServiceProvider::boot; rute /_devlib-lookup/{name} milik DevLib.
 * Status       : Aktif.
 */
final class ItemLookups
{
    public static function register(): void
    {
        foreach (['product_in', 'product_opn'] as $name) {
            // Penerimaan: pilihan satuan baris (packs) ikut diisi menurut barangnya; Saldo Awal selalu satuan stok.
            $receiving = $name === 'product_in';
            Lookup::define($name, [
                'key' => 'id', 'label' => 'label', 'source' => 'm_item (is_active = 1)', 'fill' => ['unit' => 'unit', 'unit_cost' => 'price'] + ($receiving ? ['stock_unit' => 'unit', 'packs' => 'packs'] : []),
                'columns' => ['name' => 'Item', 'code' => 'Code', 'unit' => 'Unit', 'expiry' => 'Expiry'],
                'search' => fn (string $q) => Product::query()->with('packUnits')->where('is_active', true)
                    ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"))
                    ->orderBy('name')->limit(50)->get()->map(fn (Product $p) => self::productRow($p, $receiving)),
                'find' => fn ($id) => ($p = Product::query()->find($id)) ? self::productRow($p, $receiving) : null,
            ]);
        }

        foreach (['item_out' => ['unit' => 'unit', 'available' => 'stock'], 'item_adj' => ['unit' => 'unit', 'system_qty' => 'stock']] as $name => $fill) {
            $out = $name === 'item_out';
            Lookup::define($name, [
                'key' => 'id', 'label' => 'label', 'source' => 'items (is_active = 1)', 'fill' => $fill,
                'columns' => ['name' => 'Item', 'code' => 'Code', 'expiry' => 'Expiry', 'stock' => 'Stock'],
                'search' => fn (string $q) => Item::query()->where('is_active', true)
                    ->when($out, fn ($w) => $w->where('stock_qty', '>', 0)->where(fn ($x) => $x->whereNull('expiry_date')->orWhere('expiry_date', '>=', today()->toDateString())))
                    ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"))
                    ->orderBy('name')->orderByRaw('expiry_date is null')->orderBy('expiry_date')->orderBy('id')->limit(50)->get()->map(self::itemRow(...)),
                'find' => fn ($id) => ($i = Item::query()->find($id)) ? self::itemRow($i) : null,
            ]);
        }
        // Stok Opname "Tambah barang": batch aktif apa pun, TANPA stok sistem (hitung buta tidak boleh bocor lewat pilihan ini).
        Lookup::define('item_count', [
            'key' => 'id', 'label' => 'label', 'source' => 'items (is_active = 1)', 'columns' => ['name' => 'Item', 'code' => 'Code', 'expiry' => 'Expiry'],
            'search' => fn (string $q) => Item::query()->where('is_active', true)->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"))
                ->orderBy('name')->orderByRaw('expiry_date is null')->orderBy('expiry_date')->orderBy('id')->limit(50)->get()->map(fn (Item $i) => Arr::except(self::itemRow($i), 'stock')),
            'find' => fn ($id) => ($i = Item::query()->find($id)) ? Arr::except(self::itemRow($i), 'stock') : null,
        ]);
    }

    private static function productRow(Product $p, bool $withPacks = false): array
    {
        return [
            'id' => $p->id, 'label' => "{$p->code} · {$p->name}", 'name' => $p->name, 'code' => $p->code, 'unit' => $p->unit,
            'expiry' => $p->tracks_expiry ? 'Wajib diisi' : '—', 'price' => auth()->user()?->isSupervisor() ? $p->unit_price : null,
        ] + ($withPacks ? ['packs' => json_encode(ProductUnits::options($p))] : []);
    }

    private static function itemRow(Item $i): array
    {
        return [
            'id' => $i->id, 'label' => $i->label(), 'name' => $i->name, 'code' => $i->code, 'unit' => $i->unit,
            'expiry' => $i->expiry_date ? Locale::d($i->expiry_date) : '—', 'stock' => $i->stock_qty,
        ];
    }
}
