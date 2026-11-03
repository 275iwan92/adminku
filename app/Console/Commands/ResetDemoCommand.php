<?php

namespace App\Console\Commands;

use App\Services\SampleData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/* REMARK
 * Tujuan       : Mengembalikan SEMUA data produk demo AdminKU ke keadaan awal (akun demo, master dan transaksi Stok, GA, HR, Purchase, perusahaan fiktif, berkas foto / KTP / lampiran). Idempoten: hasilnya selalu sama.
 *                Hanya jalan bila SSTOCK_DEMO=true; di aplikasi nyata (Jays) menolak, supaya data tidak pernah terhapus tanpa sengaja. Data wilayah (geo_*) dipertahankan.
 * Sumber hulu  : config sstock.demo, App\Services\SampleData::refresh (sama dengan tombol Segarkan di layar Data Contoh).
 * Output       : database dan berkas demo kembali awal.
 * Dipakai oleh : php artisan adminku:reset-demo; langkah pemasangan demo.
 * Status       : Aktif.
 */
#[Signature('adminku:reset-demo {--force : Tanpa konfirmasi}')]
#[Description('Kembalikan semua data demo AdminKU ke keadaan awal (hanya bila SSTOCK_DEMO=true)')]
class ResetDemoCommand extends Command
{
    public function handle(): int
    {
        if (! config('sstock.demo')) {
            $this->error('Ditolak: SSTOCK_DEMO bukan true. Perintah ini hanya untuk produk demo AdminKU, bukan aplikasi dengan data nyata.');

            return self::FAILURE;
        }
        if (! $this->option('force') && $this->input->isInteractive() && ! $this->confirm('Semua data demo akan dikembalikan ke awal. Lanjut?')) {
            return self::FAILURE;
        }

        SampleData::refresh('artisan', force: true);
        $counts = array_combine(array_values(SampleData::COUNTED), SampleData::counts());
        $this->info('Data demo dikembalikan ke keadaan awal: '.implode(', ', array_map(fn ($label, $n) => "$n $label", array_keys($counts), $counts)).'.');

        return self::SUCCESS;
    }
}
