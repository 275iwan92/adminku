<?php

namespace Database\Seeders;

use App\Models\AcArea;
use App\Models\AcOrder;
use App\Models\AcRoom;
use App\Models\AcUnit;
use App\Models\AcVendor;
use App\Models\User;
use App\Services\AcCare;
use App\Support\Constants;
use Illuminate\Database\Seeder;

/* REMARK
 * Tujuan       : Data contoh GA Perawatan AC (SEMUA FIKTIF): satu vendor contoh (nomor WhatsApp dan e-mail @example.test), 4 area (Kantor Lt. 1, Kantor Lt. 2, Gudang, Workshop), 7 ruang dengan persegi denah dan PIC,
 *                9 unit AC (AC-0001 .. AC-0009; Daikin / Panasonic / LG / Gree / Sharp, 1 sampai 3 PK) dengan cuci terakhir BERTANGGAL RELATIF terhadap hari ini supaya ketiga status ada (3 Terlambat, 3 Segera, 3 Aman),
 *                dan satu pesanan kunjungan Draf berisi 2 AC (lewat AcCare::schedule, jalur resmi). Pengaturan ga.ac.* diisi bila belum ada.
 *                Master (vendor, area, ruang, unit) idempoten menurut kode alami; tanggal cuci awal hanya diisi saat unit BARU dibuat (dijalankan ulang tidak menggeser riwayat); pesanan contoh hanya dibuat sekali (dilewati bila sudah ada pesanan).
 * Sumber hulu  : users (admin sebagai pembuat pesanan), AcCare, Constants.
 * Output       : m_ac_vendor, m_ac_area, m_ac_room, m_ac_unit, g_ac_service_order / line, sys_constant (ga.ac.*).
 * Dipakai oleh : DatabaseSeeder.
 * Status       : Aktif (data contoh).
 */
class AcSeeder extends Seeder
{
    private const SETTINGS = [
        'ga.ac.wash_months' => 3, 'ga.ac.major_months' => 12, 'ga.ac.capacity' => 3, 'ga.ac.days' => '1,2,3,4,5',
        'ga.ac.start_time' => '09:00', 'ga.ac.end_time' => '16:00', 'ga.ac.soon_days' => 30, 'ga.ac.pic_notice_time' => '15:00',
    ];

    private const AREAS = ['KL1' => 'Kantor Lt. 1', 'KL2' => 'Kantor Lt. 2', 'GDG' => 'Gudang', 'WRK' => 'Workshop'];

    // kode ruang => [kode area, nama, kiri, atas, lebar, tinggi (persen denah), PIC, WhatsApp, e-mail]
    private const ROOMS = [
        'MTG' => ['KL1', 'Ruang Meeting', 5, 8, 40, 34, 'Budi Santoso (contoh)', '0812-0000-2001', 'budi.contoh@example.test'],
        'DIR' => ['KL1', 'Ruang Direksi', 50, 8, 45, 34, 'Rina Wijaya (contoh)', '0812-0000-2002', 'rina.contoh@example.test'],
        'ADM' => ['KL1', 'Ruang Admin', 5, 50, 90, 40, 'Sari Dewi (contoh)', '0812-0000-2003', 'sari.contoh@example.test'],
        'HRD' => ['KL2', 'Ruang HRD', 5, 8, 45, 45, 'Dewi Lestari (contoh)', '0812-0000-2004', 'dewi.contoh@example.test'],
        'KEU' => ['KL2', 'Ruang Keuangan', 55, 8, 40, 45, 'Agus Pratama (contoh)', '0812-0000-2005', 'agus.contoh@example.test'],
        'KGD' => ['GDG', 'Kantor Gudang', 8, 10, 50, 45, 'Hendra Kusuma (contoh)', '0812-0000-2006', 'hendra.contoh@example.test'],
        'WRK' => ['WRK', 'Ruang Workshop', 10, 10, 80, 70, 'Joko Susilo (contoh)', '0812-0000-2007', 'joko.contoh@example.test'],
    ];

    // kode aset => [kode ruang, merek, tipe, PK, titik kiri, titik atas, hari lewat jatuh tempo cuci (negatif = hari lagi), servis besar terakhir (hari lalu)]
    private const UNITS = [
        'AC-0001' => ['MTG', 'Daikin', 'FTV25', 1.0, 15, 22, 45, 150],
        'AC-0002' => ['DIR', 'Panasonic', 'CS-YN18', 2.0, 72, 22, 12, 345],
        'AC-0003' => ['ADM', 'LG', 'S09EN', 1.0, 22, 70, -8, 150],
        'AC-0004' => ['ADM', 'Gree', 'GWC12', 1.5, 65, 70, -25, 120],
        'AC-0005' => ['HRD', 'Sharp', 'AH-A9', 1.0, 27, 30, -60, 90],
        'AC-0006' => ['KEU', 'Daikin', 'FTV50', 2.0, 75, 30, -75, 180],
        'AC-0007' => ['KGD', 'LG', 'S12EN', 1.5, 22, 32, 30, 150],
        'AC-0008' => ['KGD', 'Gree', 'GWC30', 3.0, 45, 40, -3, 240],
        'AC-0009' => ['WRK', 'Panasonic', 'CS-YN24', 3.0, 50, 45, -80, 60],
    ];

    public function run(): void
    {
        foreach (self::SETTINGS as $key => $value) {
            Constants::get($key) === null && Constants::set($key, $value, 'ga');
        }

        $vendor = AcVendor::updateOrCreate(['code' => 'VND-001'], [
            'name' => 'CV Sejuk Nusantara (contoh)', 'contact_name' => 'Pak Andi (contoh)', 'phone' => '0812-0000-1001', 'email' => 'vendor.ac@example.test', 'note' => 'Data contoh (fiktif)', 'is_active' => true,
        ]);

        $areas = [];
        foreach (self::AREAS as $code => $name) {
            $areas[$code] = AcArea::updateOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        }

        $rooms = [];
        foreach (self::ROOMS as $code => [$area, $name, $x, $y, $w, $h, $pic, $phone, $mail]) {
            $rooms[$code] = AcRoom::updateOrCreate(['code' => $code], [
                'area_id' => $areas[$area]->id, 'name' => $name, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'pic_name' => $pic, 'pic_phone' => $phone, 'pic_email' => $mail, 'is_active' => true,
            ]);
        }

        foreach (self::UNITS as $asset => [$room, $brand, $model, $pk, $px, $py, $late, $majorDays]) {
            $unit = AcUnit::firstOrNew(['asset_code' => $asset]);
            $new = ! $unit->exists;
            $unit->fill(['room_id' => $rooms[$room]->id, 'brand' => $brand, 'model' => $model, 'capacity_pk' => $pk, 'pos_x' => $px, 'pos_y' => $py, 'note' => 'Data contoh (fiktif)', 'is_active' => true]);
            $new && $unit->fill([
                'installed_at' => today()->subYears(2), 'last_wash_at' => today()->subMonths(AcCare::washMonths())->subDays($late), 'last_major_at' => today()->subDays($majorDays),
            ]);
            $unit->save();
        }

        if (AcOrder::query()->exists()) {
            return;   // pesanan contoh hanya sekali
        }
        $by = User::query()->where('username', 'admin')->first() ?? User::query()->where('role', User::ROLE_ADMINISTRATOR)->orderBy('id')->first();
        if (! $by) {
            return;
        }
        $day = today()->addDay();
        while (! in_array($day->dayOfWeekIso, AcCare::visitDays(), true)) {
            $day->addDay();   // kunjungan contoh: hari kunjungan berikutnya
        }
        AcCare::schedule($vendor, $day->toDateString(), null, AcUnit::query()->whereIn('asset_code', ['AC-0003', 'AC-0004'])->pluck('id')->all(), $by);
    }
}
