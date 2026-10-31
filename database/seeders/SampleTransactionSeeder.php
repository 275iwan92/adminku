<?php

namespace Database\Seeders;

use App\Enums\StockDocumentType;
use App\Models\Batch;
use App\Models\Item;
use App\Models\Recipient;
use App\Models\StockDocument;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockDocuments;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/* REMARK
 * Tujuan       : Riwayat contoh lewat jalur resmi (StockDocuments -> StockLedger): saldo awal bulan lalu, penerimaan, pengeluaran (ada yang membuat stok di bawah Reorder Point), satu penyesuaian disetujui dan satu menunggu persetujuan.
 * Sumber hulu  : ItemSeeder::ITEMS (stok awal), users, m_supplier.
 * Output       : rm_stock, rm_stock_line, rm_stockcard, i_batch.stock_qty / avg_cost.
 * Dipakai oleh : DatabaseSeeder. Dilewati bila sudah ada dokumen stok.
 * Status       : Aktif (data contoh).
 */
class SampleTransactionSeeder extends Seeder
{
    public function run(StockDocuments $docs): void
    {
        if (StockDocument::query()->exists()) {
            return;
        }

        $sup = User::where('username', 'supervisor')->firstOrFail();
        $entry = User::where('username', 'entry')->firstOrFail();
        $item = fn (string $code) => Batch::where('code', $code)->firstOrFail()->id;
        $recipient = fn (string $name) => Recipient::where('name', $name)->firstOrFail()->id;
        $supplier = fn (string $code) => Supplier::where('code', $code)->firstOrFail()->id;
        $monthStart = now()->subMonth()->startOfMonth();
        // Dokumen contoh dibuat lalu langsung di-Confirm supaya diposting ke stok seperti pemakaian nyata.
        $post = function (StockDocumentType $type, array $header, array $lines, User $user) use ($docs) {
            $doc = $docs->create($type, $header, $lines, $user);
            $docs->confirm($doc, $user);

            return $doc;
        };

        // Saldo awal: seluruh item pada awal periode (bulan lalu), nilai = harga acuan.
        $opening = array_map(fn ($r) => ['batch_id' => $item($r[0]), 'qty' => $r[9], 'unit_cost' => $r[5]], ItemSeeder::ITEMS);
        // Batch berkedaluwarsa dicari SEBELUM waktu sistem digeser ke bulan lalu, supaya tanggalnya sama dengan yang dibuat ItemSeeder.
        foreach (ItemSeeder::EXPIRING as $r) {
            $opening[] = ['batch_id' => Item::where('name', $r[0])->firstOrFail()->batchFor(today()->addDays($r[9]))->id, 'qty' => $r[8], 'unit_cost' => $r[4]];
        }
        Carbon::setTestNow($monthStart->copy()->setTime(8, 0));
        $post(StockDocumentType::Opening, ['doc_at' => $monthStart->toDateString(), 'note' => 'Saldo awal periode'], $opening, $sup);

        // Penerimaan.
        Carbon::setTestNow($monthStart->copy()->addDays(6)->setTime(9, 30));
        $post(StockDocumentType::Receiving, ['supplier_id' => $supplier('SUP-002'), 'ref_no' => 'SJ-2610-017'], [
            ['batch_id' => $item('SPR-0001'), 'qty' => 2000, 'unit_cost' => 460],
            ['batch_id' => $item('SPR-0004'), 'qty' => 2000, 'unit_cost' => 225],
        ], $entry);
        Carbon::setTestNow($monthStart->copy()->addDays(12)->setTime(13, 15));
        $post(StockDocumentType::Receiving, ['supplier_id' => $supplier('SUP-001'), 'ref_no' => 'INV-5521'], [
            ['batch_id' => $item('ATK-0001'), 'qty' => 20, 'unit_cost' => 290000],
            ['batch_id' => $item('ATK-0005'), 'qty' => 48, 'unit_cost' => 9800],
        ], $entry);

        // Pengeluaran; SPR-0002, SPR-0005 dan TLS-0002 berakhir di/bawah Reorder Point.
        Carbon::setTestNow($monthStart->copy()->addDays(15)->setTime(10, 5));
        $post(StockDocumentType::Issuance, ['recipient_id' => $recipient('Budi (Teknisi)'), 'reason' => 'Overhaul mesin press 2'], [
            ['batch_id' => $item('SPR-0002'), 'qty' => 850],
            ['batch_id' => $item('SPR-0005'), 'qty' => 1500],
            ['batch_id' => $item('SPR-0008'), 'qty' => 4],
            ['batch_id' => $item('TLS-0002'), 'qty' => 3],
        ], $entry);
        Carbon::setTestNow($monthStart->copy()->addDays(20)->setTime(8, 45));
        $post(StockDocumentType::Issuance, ['recipient_id' => $recipient('Sari'), 'reason' => 'Alat tulis kantor'], [
            ['batch_id' => $item('ATK-0001'), 'qty' => 18],
            ['batch_id' => $item('ATK-0002'), 'qty' => 6],
        ], $entry);

        // Opname: satu disetujui (susut baut), satu masih menunggu persetujuan.
        Carbon::setTestNow(now()->subDays(5)->setTime(16, 0));
        $approved = $docs->create(StockDocumentType::Adjustment, ['reason' => 'Opname stok bulanan - baut dan mur'], [
            ['batch_id' => $item('SPR-0001'), 'counted_qty' => 4960],
        ], $sup);
        $docs->submit($approved);
        $docs->approve($approved, $sup);

        Carbon::setTestNow(now()->subDays(1)->setTime(15, 30));
        $pending = $docs->create(StockDocumentType::Adjustment, ['reason' => 'Opname - rak ATK A1'], [
            ['batch_id' => $item('ATK-0002'), 'counted_qty' => 18],
            ['batch_id' => $item('ATK-0003'), 'counted_qty' => 11],
        ], $sup);
        $docs->submit($pending);

        Carbon::setTestNow();
    }
}
