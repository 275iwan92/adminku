<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;

/* REMARK
 * Tujuan       : Data demo Purchase: satu rantai lengkap PP disetujui -> Keputusan Pesanan -> PO disetujui -> PV dikonfirmasi (stok bertambah lewat Penerimaan Barang otomatis), ditambah satu PP yang menunggu persetujuan.
 *                Dibuat lewat rute aplikasi sendiri (admin membuat, supervisor menyetujui) supaya nomor, status, dan stok persis seperti pemakaian nyata; bukan salinan logika.
 * Sumber hulu  : Department, Supplier, Item (dari seeder lain), rute pr.*, po-decision.make, po.*, pv.*.
 * Output       : p_* (PP, PO, PV) dan satu dokumen Penerimaan Barang.
 * Dipakai oleh : DatabaseSeeder hanya bila config sstock.demo (produk demo AdminKU); tidak dipakai di Jays.
 * Status       : Aktif (data demo).
 */
class DemoPurchaseSeeder extends Seeder
{
    public function run(): void
    {
        // Penolong HTTP di dalam proses (trait pengujian Laravel) terpisah dari Seeder karena Seeder::call() bentrok dengan MakesHttpRequests::call().
        $http = new class
        {
            use InteractsWithAuthentication, MakesHttpRequests;

            public $app;
        };
        $http->app = app();
        // Permintaan di dalam proses meniru PHP-FPM: bila APP_URL berawalan (mis. /adminku di Biznet) awalan itu dibuang dari jalur lewat SCRIPT_NAME, seperti Nginx.
        $prefix = rtrim((string) parse_url(url('/'), PHP_URL_PATH), '/');
        $http->withServerVariables(['SCRIPT_NAME' => $prefix.'/index.php', 'SCRIPT_FILENAME' => public_path('index.php'), 'PHP_SELF' => $prefix.'/index.php']);
        $http->withHeaders(['Sec-Fetch-Site' => 'same-origin']);   // lolos pemeriksaan CSRF seperti peramban pada situs yang sama; middleware lain (hak akses, route binding) tetap jalan
        config(['session.driver' => 'array']);

        $admin = User::where('username', 'admin')->firstOrFail();
        $boss = User::where('username', 'spv')->firstOrFail();
        $dept = Department::query()->orderBy('id')->firstOrFail();
        $supplier = Supplier::query()->where('is_active', true)->orderBy('id')->firstOrFail();
        $items = Item::query()->where('is_active', true)->where('tracks_expiry', false)->orderBy('id')->limit(3)->get();
        $today = today()->toDateString();

        $lines = fn ($rows) => $rows->map(fn ($i, $n) => ['item_id' => $i->id, 'qty' => 10 * ($n + 1), 'est_price' => 15000])->all();
        $pp = ['doc_date' => $today, 'department_id' => $dept->id, 'need_date' => today()->addDays(7)->toDateString(), 'note' => 'Contoh permintaan pembelian (data demo)'];

        // PP 1: disetujui lalu dipesan dan diterima.
        $http->actingAs($admin)->post(route('pr.store'), $pp + ['lines' => $lines($items->take(2))]);
        $request = PurchaseRequest::query()->latest('id')->firstOrFail();
        $http->put(route('pr.update', $request), ['_action' => 'confirm'] + $pp + ['lines' => $lines($items->take(2))]);
        $http->actingAs($boss)->put(route('pr.update', $request), ['_action' => 'approve']);

        // PP 2: masih menunggu persetujuan.
        $http->actingAs($admin)->post(route('pr.store'), $pp + ['note' => 'Contoh PP menunggu persetujuan (data demo)', 'lines' => $lines($items->slice(2))]);
        $waiting = PurchaseRequest::query()->latest('id')->firstOrFail();
        $http->put(route('pr.update', $waiting), ['_action' => 'confirm'] + $pp + ['lines' => $lines($items->slice(2))]);

        // Keputusan Pesanan: tiap baris PP 1 masuk ke draf PO pemasok; harga diisi saat Confirm.
        foreach ($request->fresh()->lines as $line) {
            $http->actingAs($admin)->post(route('po-decision.make'), ['supplier_id' => $supplier->id, 'item_id' => $line->item_id, 'qty' => $line->qty, 'line' => $line->id]);
        }
        $po = PurchaseOrder::query()->latest('id')->firstOrFail();
        $poLines = $po->lines()->get()->map(fn ($l) => ['item_id' => $l->item_id, 'qty' => $l->qty, 'price' => 15000])->all();
        $payload = ['doc_date' => $today, 'supplier_id' => $supplier->id, 'payment_term_days' => 30, 'vat_pct' => 11, 'ship_to' => 'Gudang utama', 'note' => 'Contoh PO (data demo)', 'lines' => $poLines];
        $http->actingAs($admin)->put(route('po.update', $po), ['_action' => 'confirm'] + $payload);
        $http->actingAs($boss)->put(route('po.update', $po), ['_action' => 'approve']);

        // PV: barang diterima penuh -> Penerimaan Barang di Stok otomatis.
        $received = [];
        foreach ($po->lines()->get() as $l) {
            $received[$l->id] = ['qty' => $l->qty];
        }
        $pv = ['order_id' => $po->id, 'doc_date' => $today, 'ref_no' => 'SJ-DEMO-001', 'receiver' => 'Staf Gudang', 'note' => 'Contoh PV (data demo)', 'lines' => $received];
        $http->actingAs($admin)->post(route('pv.store'), $pv);
        $receipt = PurchaseReceipt::query()->latest('id')->firstOrFail();
        $http->put(route('pv.update', $receipt), ['_action' => 'confirm'] + $pv);
    }
}
