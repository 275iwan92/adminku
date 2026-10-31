<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Recipient;
use Illuminate\Database\Seeder;

/* REMARK
 * Tujuan       : Departemen dan Penerima barang contoh untuk transaksi Issuance.
 * Output       : tabel m_department, m_recipient.
 * Dipakai oleh : DatabaseSeeder (sebelum SampleTransactionSeeder).
 * Status       : Aktif (data contoh).
 */
class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [];
        foreach ([['MNT', 'Maintenance'], ['PRD', 'Produksi'], ['ADM', 'Admin'], ['GDG', 'Gudang'], ['QC', 'Quality Control']] as [$code, $name]) {
            $departments[$code] = Department::firstOrCreate(['code' => $code], ['name' => $name])->id;
        }
        foreach ([['Budi (Teknisi)', 'MNT'], ['Agus (Teknisi)', 'MNT'], ['Sari', 'ADM'], ['Dewi', 'PRD'], ['Rudi', 'GDG'], ['Rina', 'QC']] as [$name, $dept]) {
            Recipient::firstOrCreate(['name' => $name], ['department_id' => $departments[$dept]]);
        }
    }
}
