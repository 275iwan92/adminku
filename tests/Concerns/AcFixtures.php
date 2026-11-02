<?php

namespace Tests\Concerns;

use App\Models\AcArea;
use App\Models\AcOrder;
use App\Models\AcRoom;
use App\Models\AcUnit;
use App\Models\AcVendor;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\AcCare;

/* REMARK
 * Tujuan       : Pembuat data uji untuk tes modul GA Perawatan AC (area, ruang, unit, vendor, pengguna berhak Grup) supaya tiap tes ringkas.
 * Dipakai oleh : tes AcCareTest, AcVisitsTest, AcScreensTest, AcMasterTest (sebagian).
 * Status       : Aktif.
 */
trait AcFixtures
{
    protected function acArea(string $name = 'Kantor Lt. 1'): AcArea
    {
        return AcArea::firstOrCreate(['name' => $name], ['code' => AcCare::uniqueCode('m_ac_area', $name)]);
    }

    protected function acRoom(?AcArea $area = null, string $name = 'Ruang Meeting', array $over = []): AcRoom
    {
        $area ??= $this->acArea();

        return AcRoom::firstOrCreate(['name' => $name, 'area_id' => $area->id], $over + ['code' => AcCare::uniqueCode('m_ac_room', $name), 'x' => 10, 'y' => 20, 'w' => 30, 'h' => 20, 'pic_name' => 'Budi', 'pic_phone' => '0812-3456-7001']);
    }

    /** Unit AC dengan cuci terakhir $washAgo hari lalu (jatuh tempo 3 bulan sesudahnya). */
    protected function acUnit(?AcRoom $room = null, ?int $washAgo = 10, array $over = []): AcUnit
    {
        $room ??= $this->acRoom();

        return AcUnit::create($over + [
            'asset_code' => AcUnit::nextCode(), 'room_id' => $room->id, 'brand' => 'Daikin', 'model' => 'FTV25', 'capacity_pk' => 1,
            'last_wash_at' => $washAgo === null ? null : today()->subDays($washAgo), 'last_major_at' => today()->subMonths(6), 'installed_at' => today()->subYears(2),
        ]);
    }

    protected function acVendor(string $name = 'CV Sejuk Contoh', array $over = []): AcVendor
    {
        return AcVendor::firstOrCreate(['name' => $name], $over + ['code' => AcVendor::nextCode(), 'phone' => '0812-3456-7000', 'email' => 'vendor@example.test', 'contact_name' => 'Pak Andi']);
    }

    /** Unit yang sudah terlambat cuci (cuci terakhir 4 bulan lalu). */
    protected function acLate(?AcRoom $room = null, array $over = []): AcUnit
    {
        return $this->acUnit($room, 125, $over);
    }

    protected function acOrder(AcVendor $vendor, array $units, string $date, ?User $by = null): AcOrder
    {
        return AcCare::schedule($vendor, $date, null, array_map(fn ($u) => $u->id, $units), $by ?? User::factory()->administrator()->create());
    }

    protected function acUser(array $access): User
    {
        $group = UserGroup::create(['code' => 'G'.UserGroup::count(), 'name' => 'GA uji '.UserGroup::count()]);
        $group->syncAccess($access);

        return User::factory()->create(['group_id' => $group->id]);
    }
}
