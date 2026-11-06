<?php

namespace Database\Seeders;

use App\Models\AcVendor;
use App\Models\Department;
use App\Models\Employee;
use App\Models\FacAsset;
use App\Models\FacCategory;
use App\Models\FacHandover;
use App\Models\FacOrder;
use App\Models\FacPlace;
use App\Models\FacPlan;
use App\Models\User;
use App\Services\FacCare;
use App\Services\FacCustody;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Data contoh GA Perawatan Gedung dan Aset (SEMUA FIKTIF, PT XYZ): 3 gedung dengan ruangnya, 8 kategori aset, 34 aset (laptop, kalkulator, proyektor: sebagian dibawa pulang / dipinjam, satu terlambat kembali) (kondisi Baik / Perlu perawatan / Rusak), 9 jadwal berkala (Aman, Segera, Terlambat),
 *                dan 13 Perintah Kerja berstatus beragam (Draf, Diajukan, Selesai, Ditolak, Dibatalkan) dengan biaya tersebar 6 bulan agar grafik terisi. Semua tanggal RELATIF terhadap hari ini. Master idempoten menurut kode;
 *                jadwal dan tiket contoh hanya dibuat sekali (dilewati bila sudah ada).
 * Sumber hulu  : users (admin pembuat, spv penyetuju), m_ac_vendor (vendor bersama), FacCare.
 * Output       : m_fac_place, m_fac_category, m_fac_asset, g_fac_plan, g_fac_order.
 * Dipakai oleh : DatabaseSeeder.
 * Status       : Aktif (data contoh).
 */
class FacSeeder extends Seeder
{
    private const PLACES = [
        'KP' => ['Kantor Pusat', null], 'KP-LOB' => ['Lobi dan Resepsionis', 'KP'], 'KP-MTG' => ['Ruang Meeting Lt. 1', 'KP'], 'KP-SRV' => ['Ruang Server Lt. 2', 'KP'], 'KP-PNT' => ['Pantry Lt. 2', 'KP'],
        'GD' => ['Gudang Utama', null], 'GD-BM' => ['Area Bongkar Muat', 'GD'], 'GD-PNL' => ['Ruang Panel Listrik', 'GD'], 'GD-KTR' => ['Kantor Gudang', 'GD'],
        'WS' => ['Workshop', null], 'WS-PRD' => ['Area Produksi', 'WS'],
    ];

    private const CATEGORIES = [
        'BGN' => ['Bangunan', 'fa-building'], 'LST' => ['Listrik', 'fa-bolt'], 'PLB' => ['Plumbing', 'fa-faucet-drip'], 'MBL' => ['Mebel', 'fa-couch'],
        'ELK' => ['Elektronik', 'fa-computer'], 'KDR' => ['Kendaraan ringan', 'fa-car-side'], 'KSL' => ['Keselamatan', 'fa-fire-extinguisher'], 'ATK' => ['ATK tidak habis pakai', 'fa-calculator'],
    ];

    // nama => [kategori, tempat, tahun lalu dibeli, nilai, garansi (bulan sejak beli), kondisi]
    private const ASSETS = [
        ['Atap dan plafon lobi', 'BGN', 'KP-LOB', 6, 85000000, 0, 'baik', 0], ['Pintu kaca otomatis lobi', 'BGN', 'KP-LOB', 3, 32000000, 24, 'perlu', 0], ['Rolling door gudang', 'BGN', 'GD-BM', 4, 18500000, 12, 'baik', 0],
        ['Lantai epoksi workshop', 'BGN', 'WS-PRD', 2, 46000000, 0, 'baik', 0], ['Panel listrik utama', 'LST', 'GD-PNL', 5, 120000000, 36, 'baik', 0], ['Genset 100 kVA', 'LST', 'GD-PNL', 4, 285000000, 24, 'perlu', 0],
        ['UPS ruang server', 'LST', 'KP-SRV', 2, 38000000, 24, 'baik', 0], ['Lampu LED area bongkar muat', 'LST', 'GD-BM', 1, 14500000, 12, 'baik', 0], ['Stabilizer listrik kantor', 'LST', 'KP-MTG', 3, 6200000, 12, 'rusak', 0],
        ['Pompa air utama', 'PLB', 'KP-PNT', 3, 7800000, 12, 'baik', 0], ['Toren air 5.000 liter', 'PLB', 'GD-KTR', 6, 9500000, 0, 'baik', 0], ['Water heater pantry', 'PLB', 'KP-PNT', 1, 2800000, 12, 'baik', 0],
        ['Meja meeting besar', 'MBL', 'KP-MTG', 2, 12500000, 0, 'baik', 0], ['Kursi meeting (set 12)', 'MBL', 'KP-MTG', 2, 9600000, 12, 'baik', 0], ['Lemari arsip besi', 'MBL', 'GD-KTR', 5, 4200000, 0, 'baik', 0],
        ['Rak gudang heavy duty', 'MBL', 'GD-BM', 4, 28000000, 0, 'baik', 0], ['Proyektor ruang meeting', 'ELK', 'KP-MTG', 3, 8900000, 12, 'perlu', 0], ['Server rak 2U', 'ELK', 'KP-SRV', 2, 54000000, 36, 'baik', 0],
        ['Switch jaringan 24 port', 'ELK', 'KP-SRV', 2, 6400000, 36, 'baik', 0], ['CCTV 8 kanal', 'ELK', 'GD-BM', 1, 11800000, 24, 'baik', 0], ['Printer multifungsi lobi', 'ELK', 'KP-LOB', 1, 5400000, 12, 'rusak', 0],
        ['Mobil operasional Avanza', 'KDR', 'KP', 3, 245000000, 36, 'baik', 0], ['Motor pengantar dokumen', 'KDR', 'KP', 2, 21500000, 12, 'baik', 0],
        ['Laptop Lenovo ThinkPad #1', 'ELK', 'KP-LOB', 2, 14500000, 24, 'baik', 1], ['Laptop Lenovo ThinkPad #2', 'ELK', 'KP-LOB', 2, 14500000, 24, 'baik', 1], ['Laptop Dell Latitude #3', 'ELK', 'KP-LOB', 1, 16800000, 24, 'baik', 1],
        ['Kalkulator Casio #1', 'ATK', 'KP-LOB', 1, 185000, 0, 'baik', 1], ['Kalkulator Casio #2', 'ATK', 'KP-LOB', 1, 185000, 0, 'baik', 1], ['Stapler besar Max #1', 'ATK', 'GD-KTR', 2, 125000, 0, 'baik', 1],
        ['Proyektor portable', 'ELK', 'KP-MTG', 1, 7200000, 12, 'baik', 1], ['Kamera dokumentasi Canon', 'ELK', 'KP-LOB', 2, 9800000, 12, 'rusak', 1],
        ['APAR 6 kg (set 10)', 'KSL', 'GD-BM', 1, 5200000, 12, 'baik', 0], ['Hydrant box gudang', 'KSL', 'GD-BM', 4, 16500000, 0, 'baik', 0], ['Alarm kebakaran kantor', 'KSL', 'KP-LOB', 3, 24500000, 24, 'baik', 0],
    ];

    // judul => [nama aset atau kode tempat (diawali #), periode, hari sampai jatuh tempo berikutnya (negatif = lewat), vendor?]
    private const PLANS = [
        ['Cek panel listrik utama', 'Panel listrik utama', 'quarterly', -9, true], ['Tes dan servis genset', 'Genset 100 kVA', 'monthly', -3, true], ['Isi ulang dan cek APAR', 'APAR 6 kg (set 10)', 'yearly', 6, true],
        ['Kuras dan bersihkan toren air', 'Toren air 5.000 liter', 'quarterly', 11, false], ['Servis berkala mobil operasional', 'Mobil operasional Avanza', 'quarterly', 40, true], ['Cek alarm kebakaran', 'Alarm kebakaran kantor', 'monthly', 21, false],
        ['Bersih-bersih atap dan talang', '#KP-LOB', 'yearly', 75, false], ['Cek pompa air', 'Pompa air utama', 'monthly', 9, false], ['Cek CCTV dan rekaman', 'CCTV 8 kanal', 'weekly', 2, false],
    ];

    // Penggunaan aset contoh: beberapa dibawa pulang / dipinjam (satu TERLAMBAT kembali), satu dalam perbaikan, riwayat Serah Terima, satu pengembalian menunggu persetujuan, satu draf, dan opname dengan satu temuan.
    private function custody(array $assets, User $admin, User $spv): void
    {
        $emps = Employee::query()->where('is_active', true)->orderBy('id')->take(2)->get();
        $dept = Department::query()->where('is_active', true)->orderBy('id')->first();
        $e1 = $emps[0] ?? null;
        $e2 = $emps[1] ?? $e1;
        // nama aset => [jenis, karyawan, departemen, hari wajib kembali dari hari ini, hari lalu keluar]
        $out = [
            'Laptop Lenovo ThinkPad #1' => ['home', $e1, null, 3, 4], 'Laptop Lenovo ThinkPad #2' => ['loan', $e2, null, -6, 20], 'Kalkulator Casio #1' => ['home', $e1, null, 1, 2],
            'Kalkulator Casio #2' => ['loan', $e2, null, 5, 3], 'Proyektor portable' => ['loan', null, $dept, 2, 1],
        ];
        foreach ($out as $name => [$type, $emp, $dep, $due, $ago]) {
            if (! $emp && ! $dep) {
                continue;
            }
            $date = today()->subDays($ago);
            $h = FacHandover::create([
                'type' => $type, 'asset_id' => $assets[$name]->id, 'employee_id' => $emp?->id, 'department_id' => $dep?->id, 'doc_date' => $date, 'expected_return' => today()->addDays($due),
                'purpose' => $type === 'home' ? 'Kerja di rumah (data contoh)' : 'Dipinjam untuk keperluan kerja (data contoh)', 'condition_out' => 'baik', 'status' => 'approved',
                'no' => FacHandover::nextNo($date), 'created_by' => $admin->id, 'submitted_at' => $date->copy()->setTime(9, 0), 'approved_by' => $spv->id, 'approved_at' => $date->copy()->setTime(10, 0),
            ]);
            FacCustody::apply($h);
        }
        FacAsset::query()->where('name', 'Kamera dokumentasi Canon')->update(['position' => 'repair']);
        if ($e1) {   // pengembalian menunggu persetujuan + draf pinjam
            $date = today()->subDay();
            FacHandover::create(['type' => 'return', 'asset_id' => $assets['Kalkulator Casio #1']->id, 'employee_id' => $e1->id, 'doc_date' => $date, 'condition_out' => 'baik', 'status' => 'approval',
                'no' => FacHandover::nextNo($date), 'created_by' => $admin->id, 'submitted_at' => now()]);
            FacHandover::create(['type' => 'loan', 'asset_id' => $assets['Stapler besar Max #1']->id, 'employee_id' => $e1->id, 'doc_date' => today(), 'expected_return' => today()->addDays(7), 'condition_out' => 'baik', 'status' => 'draft', 'created_by' => $admin->id]);
        }
        $check = fn (array $scope, array $rows) => tap(DB::table('g_fac_check')->insertGetId($scope + ['checked_at' => today()->subDays(2), 'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now(), 'note' => 'Data contoh (fiktif)']),
            fn ($id) => collect($rows)->each(fn ($found, $n) => DB::table('g_fac_check_line')->insert(['check_id' => $id, 'asset_id' => $assets[$n]->id, 'found' => $found])));
        $check(['place_id' => FacPlace::query()->where('code', 'KP-MTG')->value('id')], ['Proyektor portable' => true, 'Meja meeting besar' => true]);
        $e2 && $check(['employee_id' => $e2->id], ['Laptop Lenovo ThinkPad #2' => false, 'Kalkulator Casio #2' => true]);   // laptop yang terlambat tidak ditemukan
    }

    public function run(): void
    {
        $places = [];
        foreach (self::PLACES as $code => [$name, $parent]) {
            $places[$code] = FacPlace::updateOrCreate(['code' => $code], ['name' => $name, 'parent_id' => $parent ? $places[$parent]->id : null, 'pic_name' => $parent ? null : 'Bagian GA (contoh)', 'is_active' => true]);
        }
        $cats = [];
        foreach (self::CATEGORIES as $code => [$name, $icon]) {
            $cats[$code] = FacCategory::updateOrCreate(['code' => $code], ['name' => $name, 'icon' => $icon, 'is_active' => true]);
        }
        $assets = [];
        foreach (self::ASSETS as [$name, $cat, $place, $years, $price, $warranty, $cond, $monitored]) {
            $a = FacAsset::firstOrNew(['name' => $name]);
            $new = ! $a->exists;
            $a->fill(['category_id' => $cats[$cat]->id, 'place_id' => $places[$place]->id, 'is_active' => true, 'pic_name' => 'Bagian GA (contoh)']);
            $new && $a->fill([
                'asset_code' => FacAsset::nextCode(), 'bought_at' => today()->subYears($years)->subDays(crc32($name) % 90), 'price' => $price, 'cond' => $cond,
                'warranty_until' => $warranty ? today()->subYears($years)->addMonths($warranty) : null, 'note' => 'Data contoh (fiktif)', 'monitored' => (bool) $monitored,
            ]);
            $a->save();
            $assets[$name] = $a;
        }

        $vendor = AcVendor::query()->where('is_active', true)->orderBy('id')->first()
            ?? AcVendor::create(['code' => AcVendor::nextCode(), 'name' => 'CV Teknik Mandiri (contoh)', 'contact_name' => 'Pak Budi (contoh)', 'phone' => '0812-0000-1002', 'email' => 'vendor.gedung@example.test', 'note' => 'Data contoh (fiktif)']);

        if (FacPlan::query()->exists()) {
            return;   // jadwal dan tiket contoh hanya sekali
        }
        foreach (self::PLANS as [$title, $target, $period, $days, $withVendor]) {
            $plan = new FacPlan(['title' => $title, 'period' => $period, 'vendor_id' => $withVendor ? $vendor->id : null, 'is_active' => true]);
            str_starts_with($target, '#') ? $plan->place_id = $places[substr($target, 1)]->id : $plan->asset_id = $assets[$target]->id;
            // jatuh tempo = selesai terakhir + periode, jadi mundurkan selesai terakhir sepanjang satu periode lalu geser sesuai hari
            $probe = new FacPlan(['period' => $period, 'last_done_at' => today()]);
            $step = (int) today()->diffInDays(FacCare::nextDue($probe));
            $plan->start_at = today()->subYear();
            $plan->last_done_at = today()->addDays($days - $step);
            $plan->save();
        }

        $admin = User::query()->where('username', 'admin')->first() ?? User::query()->where('role', User::ROLE_ADMINISTRATOR)->orderBy('id')->first();
        $spv = User::query()->where('username', 'spv')->first() ?? $admin;
        if (! $admin) {
            return;
        }
        // [judul, aset, prioritas, status, hari lalu dilaporkan, hari selesai setelah lapor, vendor?, tenaga, material, catatan]
        $orders = [
            ['Lampu proyektor ruang meeting redup', 'Proyektor ruang meeting', 'normal', 'approved', 150, 2, true, 450000, 1250000, 'Lampu proyektor diganti'],
            ['Servis genset berkala', 'Genset 100 kVA', 'normal', 'approved', 125, 1, true, 1500000, 2750000, 'Ganti oli dan filter'],
            ['Perbaikan pompa air macet', 'Pompa air utama', 'high', 'approved', 100, 1, true, 650000, 1850000, 'Ganti kapasitor'],
            ['Ganti lampu LED bongkar muat', 'Lampu LED area bongkar muat', 'low', 'approved', 82, 1, false, 300000, 1450000, 'Ganti 6 unit'],
            ['Perbaikan pintu kaca otomatis', 'Pintu kaca otomatis lobi', 'high', 'approved', 60, 3, true, 1200000, 3200000, 'Sensor dan motor diganti'],
            ['Isi ulang APAR', 'APAR 6 kg (set 10)', 'normal', 'approved', 41, 2, true, 400000, 2100000, null],
            ['Servis mobil operasional 40.000 km', 'Mobil operasional Avanza', 'normal', 'approved', 22, 1, true, 850000, 2950000, null],
            ['Ganti kunci dan engsel pintu lobi', 'Pintu kaca otomatis lobi', 'normal', 'approved', 5, 1, false, 250000, 480000, null],
            ['Stabilizer listrik kantor rusak', 'Stabilizer listrik kantor', 'urgent', 'approval', 6, 3, true, 350000, 0, 'Menunggu persetujuan biaya'],
            ['Printer lobi macet kertas', 'Printer multifungsi lobi', 'normal', 'draft', 3, null, false, 0, 0, null],
            ['Kebocoran atap lobi saat hujan', '#KP-LOB', 'high', 'draft', 2, null, true, 0, 0, null],
            ['Kunci rolling door rusak', 'Rolling door gudang', 'normal', 'rejected', 30, 2, true, 900000, 600000, 'Ditolak: harga di atas pembanding'],
            ['Cek CCTV tidak merekam', 'CCTV 8 kanal', 'normal', 'cancelled', 14, 1, false, 0, 0, 'Sudah normal setelah restart'],
        ];
        foreach ($orders as [$title, $target, $priority, $status, $ago, $take, $withVendor, $labor, $material, $note]) {
            $reported = today()->subDays($ago);
            $o = new FacOrder([
                'title' => $title, 'priority' => $priority, 'status' => $status, 'reported_at' => $reported, 'handler' => $withVendor ? 'vendor' : 'internal', 'vendor_id' => $withVendor ? $vendor->id : null,
                'technician' => $withVendor ? null : 'Teknisi GA (contoh)', 'labor_cost' => $labor, 'material_cost' => $material, 'note' => $note, 'created_by' => $admin->id,
                'finished_at' => $take !== null ? $reported->copy()->addDays($take) : null, 'description' => 'Data contoh (fiktif)',
            ]);
            if (str_starts_with($target, '#')) {
                $o->place_id = $places[substr($target, 1)]->id;
            } else {
                [$o->asset_id, $o->place_id] = [$assets[$target]->id, $assets[$target]->place_id];
            }
            if ($status !== 'draft') {
                $o->no = FacOrder::nextNo($reported);
                $o->submitted_at = $reported->copy()->addDays($take ?? 1)->setTime(9, 0);
            }
            if (in_array($status, ['approved', 'rejected'], true)) {
                $o->approved_by = $spv->id;
                $o->approved_at = $o->submitted_at->copy()->addDay();
                $o->reason = $status === 'rejected' ? $note : null;
            }
            $o->status === 'cancelled' && $o->reason = $note;
            $o->save();
        }
        FacAsset::query()->whereIn('name', ['Stabilizer listrik kantor'])->update(['cond' => 'rusak']);
        $this->custody($assets, $admin, $spv);
    }
}
