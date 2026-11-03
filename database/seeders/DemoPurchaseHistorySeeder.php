<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Department;
use App\Models\Item;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;

/* REMARK
 * Tujuan       : Data demo riwayat beli untuk layar Keputusan Pesanan: 3 pemasok fiktif (e-mail dan syarat bayar berbeda), 2 barang baru (bahan habis pakai berkemasan botol dan suku cadang berkemasan pcs), dan 10 pembelian dalam 6 bulan terakhir
 *                (tiap barang 5 pembelian dari ketiga pemasok, harga naik-turun) yang masing-masing PO Selesai + PV terkonfirmasi dengan RCV di Stok. Layar Keputusan Pesanan menampilkan beli terakhir, harga terakhir termurah, jumlah beli per pemasok, dan rincian Riwayat.
 *                Ditambah dua PP contoh: satu PP Disetujui yang menunggu Keputusan Pesanan (barang-barang di atas, untuk membandingkan pemasok) dan satu PP yang sebagian barisnya sudah dijadikan PO (status Dipesan sebagian; PO Diajukan tampil di kolom Dari PP).
 *                Semua dibuat lewat rute aplikasi sendiri dengan waktu sistem digeser ke tanggal pembelian (seperti SampleTransactionSeeder), jadi nomor, tanggal, status, dan stok persis seperti pemakaian nyata. Idempoten: dilewati bila pemasok contoh sudah ada.
 * Sumber hulu  : Department, Category, User (admin, supervisor, entry), rute pr.*, po-decision.make, po.*, pv.*.
 * Output       : m_supplier DMO-S1..S3, m_item DMO-0001 dan DMO-0002, p_* dan dokumen Penerimaan Barang.
 * Dipakai oleh : DatabaseSeeder hanya bila config sstock.demo (produk demo AdminKU), setelah DemoPurchaseSeeder; ikut adminku:reset-demo.
 * Status       : Aktif (data demo).
 */
class DemoPurchaseHistorySeeder extends Seeder
{
    // [kode, nama, e-mail, telepon, kontak, syarat bayar (hari)]
    public const SUPPLIERS = [
        ['DMO-S1', 'CV Sinar Mandiri Abadi', 'penjualan@sinarmandiri.example', '031-5550101', 'Bu Lestari', 30],
        ['DMO-S2', 'PT Karya Teknik Nusantara', 'order@karyateknik.example', '031-5550202', 'Pak Hendra', 14],
        ['DMO-S3', 'Toko Maju Bersama', 'tokomaju@maju.example', '031-5550303', 'Pak Anton', 0],
    ];

    // [kode, nama, kategori, satuan, lokasi, harga acuan, ROP, ROQ, lead time (hari)]
    public const ITEMS = [
        ['DMO-0001', 'Tinta Printer Epson 003 Hitam 65 ml', 'ATK', 'bottle', 'A2-05', 92000, 6, 12, 5],
        ['DMO-0002', 'Filter Oli Forklift', 'SPR', 'pcs', 'B4-03', 165000, 3, 6, 10],
    ];

    // [hari yang lalu, indeks pemasok, indeks barang, qty, harga satuan]: urut dari yang terlama supaya rantai saldo stok berurutan waktu.
    // Barang 0: terakhir S1 90.500, S2 87.500 (termurah), S3 96.000. Barang 1: terakhir S1 181.000, S2 176.500, S3 164.000 (termurah).
    public const PURCHASES = [
        [170, 0, 0, 12, 88000],
        [160, 1, 1, 6, 172000],
        [128, 1, 0, 12, 91500],
        [118, 2, 1, 4, 168000],
        [95, 2, 0, 6, 96000],
        [77, 0, 1, 6, 181000],
        [60, 0, 0, 12, 90500],
        [41, 1, 1, 6, 176500],
        [24, 1, 0, 12, 87500],
        [12, 2, 1, 4, 164000],
    ];

    public function run(): void
    {
        if (Supplier::where('code', self::SUPPLIERS[0][0])->exists()) {
            return;   // idempoten
        }
        // Penolong HTTP di dalam proses (trait pengujian Laravel) terpisah dari Seeder karena Seeder::call() bentrok dengan MakesHttpRequests::call().
        $http = new class
        {
            use InteractsWithAuthentication, MakesHttpRequests;

            public $app;
        };
        $http->app = app();
        // Sama seperti DemoPurchaseSeeder: APP_URL berawalan (mis. /adminku di Biznet) dibuang dari jalur lewat SCRIPT_NAME, dan pemeriksaan CSRF lolos seperti situs yang sama.
        $prefix = rtrim((string) parse_url(url('/'), PHP_URL_PATH), '/');
        $http->withServerVariables(['SCRIPT_NAME' => $prefix.'/index.php', 'SCRIPT_FILENAME' => public_path('index.php'), 'PHP_SELF' => $prefix.'/index.php']);
        $http->withHeaders(['Sec-Fetch-Site' => 'same-origin']);
        config(['session.driver' => 'array']);

        $admin = User::where('username', 'admin')->firstOrFail();
        $boss = User::where('username', 'spv')->firstOrFail();
        $entry = User::where('username', 'adm')->firstOrFail();

        $suppliers = [];
        foreach (self::SUPPLIERS as [$code, $name, $email, $phone, $contact, $term]) {
            $suppliers[] = Supplier::create(['code' => $code, 'name' => $name, 'email' => $email, 'phone' => $phone, 'contact_name' => $contact, 'payment_term_days' => $term, 'address' => 'Jl. Contoh Industri, Surabaya']);
        }
        $categories = Category::pluck('id', 'code');
        $items = [];
        foreach (self::ITEMS as [$code, $name, $cat, $unit, $location, $price, $rop, $roq, $lead]) {
            Location::firstOrCreate(['code' => $location], ['name' => 'Rak '.$location]);
            $items[] = Item::where('code', $code)->first() ?? Item::register([
                'name' => $name, 'category_id' => $categories[$cat], 'unit' => $unit, 'location' => $location,
                'unit_price' => $price, 'reorder_point' => $rop, 'reorder_qty' => $roq, 'lead_time_days' => $lead,
            ], $code);
        }

        $base = today();   // patokan hari ini sebelum waktu sistem digeser (setTestNow mengubah today())
        try {
            foreach (self::PURCHASES as [$daysAgo, $s, $i, $qty, $price]) {
                $this->purchase($http, $admin, $boss, $suppliers[$s], $items[$i], $qty, $price, $base->copy()->subDays($daysAgo));
            }
            $this->requests($http, $entry, $boss, $admin, $suppliers, $items, $base);
        } finally {
            Carbon::setTestNow();
        }
    }

    // Satu pembelian lengkap: PO dibuat (admin), di-Confirm, disetujui (supervisor), lalu 3 hari kemudian barang diterima: PV di-Confirm = RCV di Stok.
    private function purchase($http, User $admin, User $boss, Supplier $supplier, Item $item, int $qty, int $price, Carbon $date): void
    {
        Carbon::setTestNow($date->copy()->setTime(9, 0));
        $payload = ['doc_date' => $date->toDateString(), 'supplier_id' => $supplier->id, 'payment_term_days' => $supplier->payment_term_days, 'need_date' => $date->copy()->addDays(7)->toDateString(),
            'ship_to' => 'Gudang utama', 'vat_pct' => 0, 'note' => 'Pembelian rutin (data demo)', 'lines' => [['item_id' => $item->id, 'qty' => $qty, 'price' => $price]]];
        $http->actingAs($admin)->post(route('po.store'), $payload);
        $po = PurchaseOrder::query()->latest('id')->firstOrFail();
        $http->put(route('po.update', $po), ['_action' => 'confirm'] + $payload);
        Carbon::setTestNow($date->copy()->setTime(14, 0));
        $http->actingAs($boss)->put(route('po.update', $po), ['_action' => 'approve']);

        $received = $date->copy()->addDays(3);
        Carbon::setTestNow($received->copy()->setTime(10, 30));
        $line = $po->lines()->firstOrFail();
        $pv = ['order_id' => $po->id, 'doc_date' => $received->toDateString(), 'ref_no' => 'SJ-'.$received->format('ymd').'-'.$supplier->id, 'receiver' => 'Staf Gudang', 'note' => 'Barang diterima (data demo)', 'lines' => [$line->id => ['qty' => $qty]]];
        $http->actingAs($admin)->post(route('pv.store'), $pv);
        $receipt = PurchaseReceipt::query()->latest('id')->firstOrFail();
        $http->put(route('pv.update', $receipt), ['_action' => 'confirm'] + $pv);
    }

    // Dua PP contoh dari staf Data Entry: A menunggu Keputusan Pesanan; B sebagian barisnya sudah jadi PO (Diajukan).
    private function requests($http, User $entry, User $boss, User $admin, array $suppliers, array $items, Carbon $base): void
    {
        $gudang = Department::where('code', 'GDG')->firstOrFail();
        $maintenance = Department::where('code', 'MNT')->firstOrFail();
        $kertas = Item::where('code', 'ATK-0001')->firstOrFail();
        [$baut, $mur, $grease] = [Item::where('code', 'SPR-0002')->firstOrFail(), Item::where('code', 'SPR-0005')->firstOrFail(), Item::where('code', 'SPR-0011')->firstOrFail()];

        $make = function (string $note, Department $dept, int $daysAgo, array $lines) use ($http, $entry, $boss, $base): PurchaseRequest {
            $date = $base->copy()->subDays($daysAgo);
            Carbon::setTestNow($date->copy()->setTime(8, 30));
            $payload = ['doc_date' => $date->toDateString(), 'department_id' => $dept->id, 'need_date' => $date->copy()->addDays(10)->toDateString(), 'note' => $note, 'lines' => $lines];
            $http->actingAs($entry)->post(route('pr.store'), $payload);
            $pr = PurchaseRequest::query()->latest('id')->firstOrFail();
            $http->put(route('pr.update', $pr), ['_action' => 'confirm'] + $payload);
            Carbon::setTestNow($date->copy()->setTime(13, 0));
            $http->actingAs($boss)->put(route('pr.update', $pr), ['_action' => 'approve']);

            return $pr->fresh();
        };

        // PP A: Disetujui, belum dipesan: tinta, filter oli, dan kertas; Keputusan Pesanan membandingkan pemasok untuk tinta dan filter oli.
        $make('Stok tinta dan filter oli menipis, kertas untuk akhir bulan (data demo)', $gudang, 3, [
            ['item_id' => $items[0]->id, 'qty' => 12],
            ['item_id' => $items[1]->id, 'qty' => 4],
            ['item_id' => $kertas->id, 'qty' => 5],
        ]);

        // PP B: Disetujui, dua dari tiga baris dipesan ke pemasok kedua lewat Keputusan Pesanan; PO di-Confirm (Diajukan, menunggu Supervisor) dan tampil di kolom Dari PP.
        $b = $make('Perbaikan rutin lini produksi (data demo)', $maintenance, 6, [
            ['item_id' => $baut->id, 'qty' => 500],
            ['item_id' => $mur->id, 'qty' => 500],
            ['item_id' => $grease->id, 'qty' => 6],
        ]);
        Carbon::setTestNow($base->copy()->subDays(5)->setTime(9, 15));
        foreach ($b->lines->take(2) as $line) {
            $http->actingAs($admin)->post(route('po-decision.make'), ['supplier_id' => $suppliers[1]->id, 'item_id' => $line->item_id, 'qty' => $line->qty, 'line' => $line->id]);
        }
        $po = PurchaseOrder::query()->latest('id')->firstOrFail();
        $prices = [$baut->id => 800, $mur->id => 380];
        $payload = ['doc_date' => $base->copy()->subDays(5)->toDateString(), 'supplier_id' => $suppliers[1]->id, 'payment_term_days' => $suppliers[1]->payment_term_days, 'need_date' => $base->copy()->addDays(2)->toDateString(), 'ship_to' => 'Gudang utama', 'vat_pct' => 0,
            'note' => 'Sebagian dari PP perbaikan rutin (data demo)', 'lines' => $po->lines()->get()->map(fn ($l) => ['item_id' => $l->item_id, 'qty' => $l->qty, 'price' => $prices[$l->item_id]])->all()];
        $http->actingAs($admin)->put(route('po.update', $po), ['_action' => 'confirm'] + $payload);
    }
}
