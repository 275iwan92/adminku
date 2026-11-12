<?php

namespace App\Support;

use App\Models\Batch;
use App\Models\Item;
use App\Models\PurchaseRequest;
use App\Models\UnitConversion;
use App\Services\ItemUnits;
use Devlib2\Locale;
use Devlib2\Lookup;
use Illuminate\Support\Arr;

/* REMARK
 * Tujuan       : Pilihan barang di baris dokumen (x-dl2::lines) dicari ke server; memilih mengisi kolom satuan, stok tersedia, selisih sistem, dan harga acuan di baris itu.
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
                'search' => function (string $q) use ($receiving) {
                    $rows = Search::apply(Item::query()->with('packUnits')->where('is_active', true), $q, ['name', 'location'])->orderBy('name')->limit(50)->get();
                    $conv = $receiving && $rows->isNotEmpty() ? UnitConversion::query()->get() : null;   // master konversi sekali per pencarian

                    return $rows->map(fn (Item $p) => self::productRow($p, $receiving, $conv));
                },
                'find' => fn ($id) => ($p = Item::query()->find($id)) ? self::productRow($p, $receiving) : null,
            ]);
        }

        foreach (['item_out' => ['unit' => 'unit', 'available' => 'stock'], 'item_adj' => ['unit' => 'unit', 'system_qty' => 'stock']] as $name => $fill) {
            $out = $name === 'item_out';
            Lookup::define($name, [
                'key' => 'id', 'label' => 'label', 'source' => 'items (is_active = 1)', 'fill' => $fill,
                'columns' => ['name' => 'Item', 'code' => 'Code', 'expiry' => 'Expiry', 'stock' => 'Stock'],
                'search' => fn (string $q) => Batch::query()->where('is_active', true)
                    ->when($out, fn ($w) => $w->where('stock_qty', '>', 0)->where(fn ($x) => $x->whereNull('expiry_date')->orWhere('expiry_date', '>=', today()->toDateString())))
                    ->tap(fn ($w) => Search::apply($w, $q, ['name', 'location']))
                    ->orderBy('name')->orderByRaw('expiry_date is null')->orderBy('expiry_date')->orderBy('id')->limit(50)->get()->map(self::itemRow(...)),
                'find' => fn ($id) => ($i = Batch::query()->find($id)) ? self::itemRow($i) : null,
            ]);
        }
        // Permintaan Pembelian: barang master aktif apa pun; memilih mengisi satuan dan estimasi harga satuan (harga beli terakhir, kosong bila belum pernah dibeli).
        // item_pp (baris PP: kolom est_price) dan item_po (baris PO: kolom price) sama; hanya kolom yang diisi harga terakhir yang berbeda.
        $pp = fn (Item $p) => ['id' => $p->id, 'label' => "{$p->code} · {$p->name}", 'name' => $p->name, 'code' => $p->code, 'unit' => $p->unit]
            + (($c = PurchaseRequest::lastCost($p->id)) === null ? ['price' => '', 'last_cost' => '—'] : ['price' => (float) $c, 'last_cost' => Locale::number((float) $c, 0)]);
        foreach (['item_pp' => 'est_price', 'item_po' => 'price'] as $name => $priceKey) {
            Lookup::define($name, [
                'key' => 'id', 'label' => 'label', 'source' => 'm_item (is_active = 1)', 'fill' => ['unit' => 'unit', $priceKey => 'price'],
                'columns' => ['name' => 'Item', 'code' => 'Code', 'unit' => 'Unit', 'last_cost' => 'Last cost'],
                'search' => fn (string $q) => Search::apply(Item::query()->where('is_active', true), $q, ['name', 'location'])->orderBy('name')->limit(50)->get()->map($pp),
                'find' => fn ($id) => ($p = Item::query()->find($id)) ? $pp($p) : null,
            ]);
        }
        // Stok Opname "Tambah barang": batch aktif apa pun, TANPA stok sistem (hitung buta tidak boleh bocor lewat pilihan ini).
        Lookup::define('item_count', [
            'key' => 'id', 'label' => 'label', 'source' => 'items (is_active = 1)', 'columns' => ['name' => 'Item', 'code' => 'Code', 'expiry' => 'Expiry'],
            'search' => fn (string $q) => Search::apply(Batch::query()->where('is_active', true), $q, ['name', 'location'])
                ->orderBy('name')->orderByRaw('expiry_date is null')->orderBy('expiry_date')->orderBy('id')->limit(50)->get()->map(fn (Batch $i) => Arr::except(self::itemRow($i), 'stock')),
            'find' => fn ($id) => ($i = Batch::query()->find($id)) ? Arr::except(self::itemRow($i), 'stock') : null,
        ]);
    }

    private static function productRow(Item $p, bool $withPacks = false, ?iterable $conversions = null): array
    {
        return [
            'id' => $p->id, 'label' => "{$p->code} · {$p->name}", 'name' => $p->name, 'code' => $p->code, 'unit' => $p->unit,
            'expiry' => $p->tracks_expiry ? 'Wajib diisi' : '—', 'price' => auth()->user()?->isSupervisor() ? $p->unit_price : null,
        ] + ($withPacks ? ['packs' => json_encode(ItemUnits::options($p, $conversions))] : []);
    }

    private static function itemRow(Batch $i): array
    {
        return [
            'id' => $i->id, 'label' => $i->label(), 'name' => $i->name, 'code' => $i->code, 'unit' => $i->unit,
            'expiry' => $i->expiry_date ? Locale::d($i->expiry_date) : '—', 'stock' => $i->stock_qty,
        ];
    }
}
