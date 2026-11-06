<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Location;
use App\Models\Item;
use Illuminate\Database\Seeder;

/* REMARK
 * Tujuan       : Sampel barang ATK, sparepart maintenance (mur / baut berbagai ukuran) dan tools (+ barang berkedaluwarsa dengan beberapa batch), lengkap dengan lokasi rak (master Lokasi), harga, Reorder Point / Qty, Lead Time.
 * Output       : tabel items (stok awal diisi lewat SampleTransactionSeeder, bukan di sini).
 * Dipakai oleh : DatabaseSeeder, SampleTransactionSeeder (daftar ITEMS).
 * Status       : Aktif (data contoh).
 */
class ItemSeeder extends Seeder
{
    // [kode, nama, kategori, satuan, lokasi, harga, ROP, ROQ, lead time (hari), stok awal]
    public const ITEMS = [
        ['ATK-0001', 'Kertas HVS A4 80gr', 'ATK', 'box', 'A1-01', 285000, 10, 30, 3, 40],
        ['ATK-0002', 'Pulpen Hitam 0.5', 'ATK', 'box', 'A1-02', 42000, 5, 20, 3, 25],
        ['ATK-0003', 'Spidol Whiteboard', 'ATK', 'box', 'A1-03', 68000, 4, 12, 3, 12],
        ['ATK-0004', 'Map Folder Plastik', 'ATK', 'pack', 'A1-04', 24000, 10, 30, 4, 50],
        ['ATK-0005', 'Lakban Bening 2"', 'ATK', 'roll', 'A2-01', 9500, 24, 72, 4, 120],
        ['ATK-0006', 'Staples No.10', 'ATK', 'box', 'A2-02', 6000, 20, 60, 4, 80],
        ['ATK-0007', 'Tinta Printer Hitam', 'ATK', 'bottle', 'A2-03', 78000, 6, 12, 7, 10],
        ['SPR-0001', 'Baut M6 x 20 mm', 'SPR', 'pcs', 'B1-01', 450, 500, 2000, 5, 3000],
        ['SPR-0002', 'Baut M8 x 30 mm', 'SPR', 'pcs', 'B1-02', 850, 400, 1500, 5, 1200],
        ['SPR-0003', 'Baut M10 x 40 mm', 'SPR', 'pcs', 'B1-03', 1400, 300, 1000, 5, 800],
        ['SPR-0004', 'Mur M6', 'SPR', 'pcs', 'B2-01', 220, 500, 2000, 5, 3500],
        ['SPR-0005', 'Mur M8', 'SPR', 'pcs', 'B2-02', 380, 400, 1500, 5, 1800],
        ['SPR-0006', 'Mur M10', 'SPR', 'pcs', 'B2-03', 650, 300, 1000, 5, 900],
        ['SPR-0007', 'Ring Plat M8', 'SPR', 'pcs', 'B3-01', 120, 500, 2000, 5, 2500],
        ['SPR-0008', 'Bearing 6204', 'SPR', 'pcs', 'B4-01', 38000, 6, 20, 10, 18],
        ['SPR-0009', 'V-Belt B-52', 'SPR', 'pcs', 'B4-02', 65000, 4, 10, 10, 8],
        ['SPR-0010', 'Oli Hidrolik 20L', 'SPR', 'can', 'B5-01', 780000, 2, 4, 14, 5],
        ['SPR-0011', 'Grease Gemuk 1kg', 'SPR', 'can', 'B5-02', 85000, 4, 12, 7, 14],
        ['TLS-0001', 'Obeng Plus/Minus Set', 'TLS', 'set', 'C1-01', 95000, 2, 5, 7, 6],
        ['TLS-0002', 'Kunci Pas 8-24 mm Set', 'TLS', 'set', 'C1-02', 320000, 1, 3, 10, 4],
        ['TLS-0003', 'Tang Kombinasi 8"', 'TLS', 'pcs', 'C2-01', 78000, 2, 6, 7, 6],
        ['TLS-0004', 'Meteran 5 m', 'TLS', 'pcs', 'C2-02', 35000, 3, 8, 7, 7],
        ['TLS-0005', 'Mata Bor HSS 6 mm', 'TLS', 'pcs', 'C3-01', 18000, 10, 30, 7, 24],
        ['TLS-0006', 'Batu Gerinda 4"', 'TLS', 'pcs', 'C3-02', 12000, 15, 50, 7, 40],
    ];

    // Barang berkedaluwarsa contoh: [nama, kategori, satuan, lokasi, harga, ROP, ROQ, lead time, stok awal, kedaluwarsa (hari dari sekarang)]. Satu nama = satu produk, tiap tanggal satu batch.
    // Tanggal relatif supaya peringatan beranda dan Laporan Kedaluwarsa selalu punya contoh (sudah lewat, hampir, masih lama), termasuk dua batch untuk satu produk.
    public const EXPIRING = [
        ['Cairan Pembersih Mesin 5L', 'SPR', 'can', 'B5-03', 120000, 2, 6, 7, 6, -15],
        ['Lem Kayu Fox 100 ml', 'ATK', 'pcs', 'A2-04', 12000, 6, 24, 3, 30, 12],
        ['Lem Kayu Fox 100 ml', 'ATK', 'pcs', 'A2-04', 12000, 6, 24, 3, 24, 200],
        ['Cat Semprot Pelindung', 'SPR', 'can', 'B5-03', 45000, 4, 12, 7, 20, 150],
    ];

    // Jenis demo dari kode rak: A1-A2 lemari, B1-B3 gudang tertutup, B4-B5 gudang terbuka, C area luas.
    private static function locationType(string $code): ?string
    {
        return match (true) {
            str_starts_with($code, 'A') => 'lemari',
            (int) substr($code, 1, 1) >= 4 && $code[0] === 'B' => 'gudang_terbuka',
            $code[0] === 'B' => 'gudang_tertutup',
            $code[0] === 'C' => 'area_luas',
            default => null,
        };
    }

    public function run(): void
    {
        $categories = Category::pluck('id', 'code');

        foreach (self::ITEMS as [$code, $name, $cat, $unit, $location, $price, $rop, $roq, $lead]) {
            Location::firstOrCreate(['code' => $location], ['name' => 'Rak '.$location, 'type' => self::locationType($location)]);
            Item::where('code', $code)->exists() || Item::register([
                'name' => $name, 'category_id' => $categories[$cat], 'unit' => $unit, 'location' => $location,
                'unit_price' => $price, 'reorder_point' => $rop, 'reorder_qty' => $roq, 'lead_time_days' => $lead,
            ], $code);
        }

        foreach (self::EXPIRING as [$name, $cat, $unit, $location, $price, $rop, $roq, $lead, , $days]) {
            Location::firstOrCreate(['code' => $location], ['name' => 'Rak '.$location, 'type' => self::locationType($location)]);
            $product = Item::where('name', $name)->first() ?? Item::register([
                'name' => $name, 'category_id' => $categories[$cat], 'unit' => $unit, 'location' => $location, 'tracks_expiry' => true,
                'unit_price' => $price, 'reorder_point' => $rop, 'reorder_qty' => $roq, 'lead_time_days' => $lead,
            ]);
            $product->batchFor(today()->addDays($days));
        }
    }
}
