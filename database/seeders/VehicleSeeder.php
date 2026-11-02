<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleOdometer;
use App\Models\VehicleType;
use App\Services\VehicleCare;
use App\Support\Constants;
use Illuminate\Database\Seeder;

/* REMARK
 * Tujuan       : Data contoh GA Perawatan Aset Kendaraan: tiga jenis kendaraan beserta rencana perawatannya, empat kendaraan FIKTIF (satu mobil penumpang tidak wajib KIR, tiga small truck), dan riwayat dokumen / servis / odometer
 *                bertanggal RELATIF terhadap hari ini lewat jalur resmi (VehicleCare) supaya ketiga status ada: JY-1 dan JY-4 Aman, JY-2 Segera (pajak 20 hari lagi, oli 400 km lagi), JY-3 Terlambat (pajak, KIR, oli, filter udara lewat).
 *                Master (jenis, rencana, kendaraan) idempoten menurut kode alami; transaksi hanya dibuat sekali per kendaraan (dilewati bila kendaraan sudah punya catatan odometer). Ambang Segera disimpan di sys_constant bila belum ada.
 * Sumber hulu  : users (admin sebagai pencatat), VehicleCare, Constants.
 * Output       : m_vehicle_type, m_vehicle_type_plan, m_vehicle, g_vehicle_doc, g_vehicle_service(+_line), g_vehicle_odometer, sys_constant (ga.vehicle.*).
 * Dipakai oleh : DatabaseSeeder.
 * Status       : Aktif (data contoh).
 */
class VehicleSeeder extends Seeder
{
    // Rencana dasar: [nama, interval km, interval bulan]. Filter oli diasumsikan 10.000 km / 12 bln.
    private const BASE = [
        ['Oli mesin', 5000, 6], ['Filter oli', 10000, 12], ['Filter udara', 20000, 12], ['Kampas rem', 30000, null], ['Ban', 40000, null], ['V-belt', 40000, null], ['Aki', null, 24],
    ];

    private const TYPES = [
        'MOBIL' => ['Mobil Penumpang', []],
        'PICKUP' => ['Pickup', [['Oli gardan / transmisi', 20000, 12]]],
        'BOKS' => ['Boks', [['Oli gardan / transmisi', 20000, 12]]],
    ];

    // Kendaraan fiktif. start = [hari lalu, km] odometer awal; services = [hari lalu, km, bengkel, biaya, [pekerjaan]]; odo = [hari lalu, km] catatan terakhir;
    // docs = [jenis => sisa hari berlaku-sampai (dari hari ini)].
    private const VEHICLES = [
        ['N 1001 JY', 'MOBIL', 'Avanza Operasional Contoh', 2021, false, [200, 46000],
            [[75, 48400, 'Bengkel Contoh Utama', 520000, ['Oli mesin', 'Filter oli']]], [2, 50500], ['pajak' => 200, 'stnk' => 900, 'asuransi' => 150]],
        ['N 1002 JY', 'PICKUP', 'Gran Max Pickup Contoh', 2020, true, [300, 60000],
            [[100, 65000, 'Bengkel Maju Jaya Contoh', 650000, ['Oli mesin', 'Filter oli']]], [1, 69600], ['pajak' => 20, 'stnk' => 1000, 'kir' => 120, 'asuransi' => 200]],
        ['N 1003 JY', 'PICKUP', 'L300 Pickup Contoh', 2018, true, [420, 78000],
            [[170, 83000, 'Bengkel Maju Jaya Contoh', 900000, ['Oli mesin', 'Filter oli', 'Oli gardan / transmisi']]], [3, 89300], ['pajak' => -12, 'stnk' => 700, 'kir' => -40, 'asuransi' => 60]],
        ['N 1004 JY', 'BOKS', 'Elf Boks Contoh', 2019, true, [250, 30000],
            [[120, 34000, 'Bengkel Contoh Utama', 1200000, ['Oli mesin', 'Filter oli', 'Filter udara', 'Oli gardan / transmisi']], [30, 36000, 'Bengkel Contoh Utama', 3400000, ['Ban', 'Kampas rem']]],
            [3, 36900], ['pajak' => 140, 'stnk' => 1200, 'kir' => 100, 'asuransi' => 170]],
    ];

    public function run(): void
    {
        foreach (['ga.vehicle.soon_days' => 30, 'ga.vehicle.soon_km' => 1000] as $key => $value) {
            Constants::get($key) === null && Constants::set($key, $value, 'ga');
        }

        $types = [];
        foreach (self::TYPES as $code => [$name, $extra]) {
            $type = VehicleType::updateOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
            foreach (array_merge(self::BASE, $extra) as $i => [$plan, $km, $months]) {
                $type->plans()->updateOrCreate(['name' => $plan], ['interval_km' => $km, 'interval_months' => $months, 'sort' => $i + 1]);
            }
            $types[$code] = $type;
        }

        $by = User::query()->where('username', 'admin')->first() ?? User::query()->where('role', User::ROLE_ADMINISTRATOR)->orderBy('id')->firstOrFail();
        $day = fn (int $ago) => today()->subDays($ago);

        foreach (self::VEHICLES as [$plate, $typeCode, $name, $year, $kir, $start, $services, $last, $docs]) {
            $vehicle = Vehicle::firstOrNew(['plate' => $plate]);
            $new = ! $vehicle->exists;
            $vehicle->fill(['vehicle_type_id' => $types[$typeCode]->id, 'name' => $name, 'year' => $year, 'kir_required' => $kir, 'is_active' => true, 'note' => 'Data contoh (fiktif)']);
            $new && $vehicle->fill(['odometer_km' => $start[1], 'odometer_at' => $day($start[0])]);
            $vehicle->save();
            if (VehicleOdometer::query()->where('vehicle_id', $vehicle->id)->exists()) {
                continue;   // transaksi contoh hanya sekali per kendaraan
            }

            VehicleCare::startOdometer($vehicle, $by, $day($start[0]));
            $plans = $types[$typeCode]->plans()->pluck('id', 'name');
            foreach ($services as [$ago, $km, $workshop, $cost, $jobs]) {
                VehicleCare::recordService($vehicle, [
                    'serviced_at' => $day($ago)->toDateString(), 'odometer_km' => $km, 'workshop' => $workshop, 'cost' => $cost, 'jobs' => array_map(fn ($j) => $plans[$j], $jobs),
                ], $by);
            }
            VehicleCare::recordOdometer($vehicle, ['read_at' => $day($last[0])->toDateString(), 'km' => $last[1], 'recorded_by' => 'Sopir Contoh'], null, $by);
            foreach ($docs as $type => $left) {
                $until = today()->addDays($left);
                VehicleCare::recordDoc($vehicle, [
                    'doc_type' => $type, 'doc_no' => strtoupper($type).'-'.$vehicle->id.'-CONTOH', 'valid_until' => $until->toDateString(),
                    'paid_at' => $until->copy()->subMonthsNoOverflow(VehicleCare::DOCS[$type][1])->toDateString(), 'cost' => ['pajak' => 3250000, 'stnk' => 1800000, 'kir' => 150000, 'asuransi' => 4200000][$type],
                ], null, $by);
            }
        }
    }
}
