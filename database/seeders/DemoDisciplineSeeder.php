<?php

namespace Database\Seeders;

use App\Models\Discipline;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;

/* REMARK
 * Tujuan       : Data demo Kedisiplinan: SP 1, SP 2, SP 3 untuk dua karyawan contoh (E-C001, E-C002): sebagian terbit bulan ini (tampil sebagai kartu), sisanya riwayat (grid). Tanggal dihitung dari hari ini.
 * Sumber hulu  : Employee (EmployeeSampleSeeder), Discipline::nextNumber.
 * Output       : h_discipline.
 * Dipakai oleh : DatabaseSeeder hanya bila config sstock.demo.
 * Status       : Aktif (data demo).
 */
class DemoDisciplineSeeder extends Seeder
{
    // [kode karyawan, jenis, hari lalu diterbitkan (null = awal bulan ini), alasan, dasar aturan]
    private const ROWS = [
        ['E-C001', 'sp1', 240, 'Terlambat masuk kerja lebih dari 5 kali dalam satu bulan.', 'Peraturan Perusahaan Pasal 12 ayat 1'],
        ['E-C001', 'sp2', null, 'Mengulangi keterlambatan dan meninggalkan area kerja tanpa izin.', 'Peraturan Perusahaan Pasal 12 ayat 2'],
        ['E-C002', 'sp1', 230, 'Laporan kehadiran bulanan terlambat disampaikan.', 'Peraturan Perusahaan Pasal 14'],
        ['E-C002', 'sp2', 35, 'Kesalahan berulang pada data kehadiran yang berdampak pada penggajian.', 'Peraturan Perusahaan Pasal 14 ayat 2'],
        ['E-C002', 'sp3', null, 'Tidak mengindahkan SP 2: kembali salah mencatat data kehadiran.', 'Peraturan Perusahaan Pasal 14 ayat 3'],
    ];

    public function run(): void
    {
        $by = User::where('username', 'admin')->value('id');
        foreach (self::ROWS as [$code, $type, $ago, $reason, $rule]) {
            $e = Employee::where('code', $code)->first();
            $on = $ago === null ? today()->startOfMonth() : today()->subDays($ago);
            if (! $e || Discipline::where('employee_id', $e->id)->where('type', $type)->whereDate('issued_on', $on)->exists()) {
                continue;
            }
            [$year, $seq, $no] = Discipline::nextNumber($type, $on);
            Discipline::create(['doc_no' => $no, 'year' => $year, 'seq' => $seq, 'type' => $type, 'employee_id' => $e->id, 'issued_on' => $on, 'until_on' => $on->copy()->addMonths(Discipline::VALID_MONTHS),
                'reason' => $reason, 'rule_ref' => $rule, 'signer_name' => 'Detta Rahma', 'signer_title' => 'Manajer HR', 'created_by' => $by]);
        }
    }
}
