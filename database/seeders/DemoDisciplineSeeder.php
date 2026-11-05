<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Discipline;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;

/* REMARK
 * Tujuan       : Data demo Kedisiplinan: SP 1, SP 2, SP 3, PHK untuk karyawan contoh (E-C001, E-C002, E-D001..E-D003): sebagian terbit bulan ini (tampil sebagai kartu), sisanya riwayat (grid). Tanggal dihitung dari hari ini.
 * Sumber hulu  : Employee (EmployeeSampleSeeder), Discipline::nextNumber.
 * Output       : h_discipline.
 * Dipakai oleh : DatabaseSeeder hanya bila config sstock.demo.
 * Status       : Aktif (data demo).
 */
class DemoDisciplineSeeder extends Seeder
{
    // Karyawan demo tambahan (kode E-D..., di luar E-C yang dikelola SampleHr): [kode, nama, L/P, nama bagian, jabatan]
    private const PEOPLE = [
        ['E-D001', 'Rudi Hartono', 'L', 'Produksi', 'Operator Mesin'],
        ['E-D002', 'Sari Wulandari', 'P', 'Gudang', 'Staf Gudang'],
        ['E-D003', 'Hendra Gunawan', 'L', 'Quality Control', 'Inspektur QC'],
    ];

    // [kode karyawan, jenis, hari lalu diterbitkan (null = awal bulan ini), alasan, dasar aturan]
    private const ROWS = [
        ['E-C001', 'sp1', 240, 'Terlambat masuk kerja lebih dari 5 kali dalam satu bulan.', 'Peraturan Perusahaan Pasal 12 ayat 1'],
        ['E-C002', 'sp1', 230, 'Laporan kehadiran bulanan terlambat disampaikan.', 'Peraturan Perusahaan Pasal 14'],
        ['E-C002', 'sp2', 35, 'Kesalahan berulang pada data kehadiran yang berdampak pada penggajian.', 'Peraturan Perusahaan Pasal 14 ayat 2'],
        ['E-D002', 'sp1', 60, 'Selisih stok barang tidak dilaporkan dalam 24 jam.', 'Peraturan Perusahaan Pasal 15'],
        ['E-D003', 'sp1', 150, 'Hasil inspeksi tidak dicatat pada lembar pemeriksaan.', 'Peraturan Perusahaan Pasal 16'],
        ['E-D003', 'sp2', 100, 'Mengulangi kelalaian pencatatan inspeksi.', 'Peraturan Perusahaan Pasal 16 ayat 2'],
        ['E-D001', 'sp1', null, 'Tidak memakai alat pelindung diri di area produksi.', 'Peraturan K3 Pasal 4'],
        ['E-C001', 'sp2', null, 'Mengulangi keterlambatan dan meninggalkan area kerja tanpa izin.', 'Peraturan Perusahaan Pasal 12 ayat 2'],
        ['E-C002', 'sp3', null, 'Tidak mengindahkan SP 2: kembali salah mencatat data kehadiran.', 'Peraturan Perusahaan Pasal 14 ayat 3'],
        ['E-D003', 'phk', null, 'Tidak mengindahkan SP 2 dan melakukan pemalsuan data hasil inspeksi.', 'Peraturan Perusahaan Pasal 16 ayat 4'],
    ];

    public function run(): void
    {
        foreach (self::PEOPLE as [$code, $name, $sex, $dept, $pos]) {
            Employee::firstOrCreate(['code' => $code], ['name' => $name, 'gender' => $sex, 'department_id' => Department::where('name', $dept)->value('id') ?? Department::value('id'), 'position' => $pos, 'employment' => 'tetap', 'join_date' => today()->subYears(2), 'work_status' => 'working', 'is_active' => true]);
        }
        $by = User::where('username', 'admin')->value('id');
        foreach (self::ROWS as [$code, $type, $ago, $reason, $rule]) {
            $e = Employee::where('code', $code)->first();
            $on = $ago === null ? today()->startOfMonth() : today()->subDays($ago);
            if (! $e || Discipline::where('employee_id', $e->id)->where('type', $type)->whereDate('issued_on', $on)->exists()) {
                continue;
            }
            [$year, $seq, $no] = Discipline::nextNumber($type, $on);
            Discipline::create(['doc_no' => $no, 'year' => $year, 'seq' => $seq, 'type' => $type, 'employee_id' => $e->id, 'issued_on' => $on, 'until_on' => $type === 'phk' ? null : $on->copy()->addMonths(Discipline::VALID_MONTHS),
                'reason' => $reason, 'rule_ref' => $rule, 'signer_name' => 'Detta Rahma', 'signer_title' => 'Manajer HR', 'created_by' => $by]);
        }
    }
}
