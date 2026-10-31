<?php

namespace App\Services;

use App\Enums\StockDocumentType;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockDocumentLine;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/* REMARK
 * Tujuan       : Satuan masuk per barang (m_item_unit): daftar kemasan yang sah saat barang diterima beserta faktornya ke satuan stok, dan aturan siapa boleh mengubah faktornya.
 *                Siapa pun boleh MEMBUAT faktor baru dan MEMPERBAIKI faktor yang belum pernah dipakai di dokumen terkonfirmasi; faktor yang sudah dipakai hanya boleh diubah pemegang hak tulis modul Satuan (UOM, Supervisor).
 *                Dokumen lama tidak pernah berubah: baris dokumen menyimpan qty yang sudah dikonversi dan yang diketik (entered_*).
 * Sumber hulu  : m_item_unit, m_unit_conversion (cadangan global), rm_stock_line (pemakaian).
 * Output       : options(product) untuk pilihan satuan di baris Penerimaan; usage(product); save(product, unit, factor, user).
 * Dipakai oleh : ItemLookups, StockDocumentController (baris form + jendela konversi), ProductController (bagian Satuan masuk).
 * Status       : Aktif.
 */
final class ProductUnits
{
    public const USED_MESSAGE = 'Faktor ini sudah dipakai di dokumen; hubungi Supervisor untuk mengubahnya.';

    /**
     * Pilihan satuan baris Penerimaan satu barang: satuan stok (faktor 1), satuan masuk sendiri (own: unit => faktor) dan, untuk satuan lain yang punya pasangan di master konversi global, faktor globalnya.
     * Satuan lain tanpa keduanya tetap boleh dipilih, tetapi pada penyimpanan minta faktor lewat jendela konversi.
     *
     * @return array{stock: string, own: array<string, string>, global: array<string, string>}
     */
    public static function options(Product $product): array
    {
        $own = $product->packUnits->mapWithKeys(fn (ProductUnit $u) => [$u->unit => $u->factor])->all();
        $global = [];
        foreach (UnitConversion::query()->get() as $c) {
            if ($c->to_unit === $product->unit) {
                $global[$c->from_unit] = Decimal::of($c->factor);
            } elseif ($c->from_unit === $product->unit) {
                $global[$c->to_unit] ??= Decimal::div('1', Decimal::of($c->factor));   // arah sebaliknya: 1 {stok} = f {to}, jadi 1 {to} = 1/f
            }
        }

        return ['stock' => $product->unit, 'own' => $own, 'global' => array_diff_key($global, $own, [$product->unit => 1])];
    }

    /** Jumlah dokumen Penerimaan terkonfirmasi (termasuk yang kemudian dibatalkan) yang memakai tiap satuan masuk barang ini. @return array<string, int> */
    public static function usage(Product $product): array
    {
        return StockDocumentLine::query()->join('rm_stock', 'rm_stock.id', '=', 'rm_stock_line.stock_document_id')
            ->join('items', 'items.id', '=', 'rm_stock_line.item_id')
            ->where('items.product_id', $product->id)->where('rm_stock.type', StockDocumentType::Receiving)->whereNotNull('rm_stock.confirmed_at')
            ->whereNotNull('rm_stock_line.entered_unit')
            ->groupBy('rm_stock_line.entered_unit')->selectRaw('rm_stock_line.entered_unit as unit, count(distinct rm_stock.id) as total')
            ->pluck('total', 'unit')->map(fn ($n) => (int) $n)->all();
    }

    /** Buat atau perbaiki faktor satuan masuk. Faktor yang sudah dipakai di dokumen terkonfirmasi: hanya pemegang hak tulis UOM (403 untuk yang lain). */
    public static function save(Product $product, string $unit, float|int|string $factor, User $user): ProductUnit
    {
        if ($unit === $product->unit || ! Unit::query()->where('code', $unit)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['unit' => $unit === $product->unit ? 'Satuan stok tidak perlu faktor (selalu 1).' : 'Satuan tidak ada atau nonaktif.']);
        }
        $factor = number_format(Decimal::round(Decimal::of($factor), 6), 6, '.', '');
        if (bccomp($factor, '0.000001', 6) < 0 || bccomp($factor, '999999999', 6) > 0) {
            throw ValidationException::withMessages(['factor' => 'Faktor harus lebih dari 0 dan paling banyak 999.999.999.']);
        }

        return DB::transaction(function () use ($product, $unit, $factor, $user) {
            $row = ProductUnit::query()->where('product_id', $product->id)->where('unit', $unit)->lockForUpdate()->first();
            if (! $row) {
                return ProductUnit::query()->createOrFirst(['product_id' => $product->id, 'unit' => $unit], ['factor' => $factor, 'created_by' => $user->id, 'updated_by' => $user->id]);
            }
            if (bccomp((string) $row->factor, $factor, 6) === 0) {
                return $row;
            }
            abort_if((self::usage($product)[$unit] ?? 0) > 0 && ! $user->canWrite('UOM'), 403, self::USED_MESSAGE);
            $row->update(['factor' => $factor, 'updated_by' => $user->id]);

            return $row;
        });
    }
}
