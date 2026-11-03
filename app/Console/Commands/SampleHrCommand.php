<?php

namespace App\Console\Commands;

use App\Services\SampleHr;
use Illuminate\Console\Command;

/* REMARK
 * Tujuan       : Data contoh HR kanonik dari snapshot repo (database/sample/hr). Tanpa opsi: membuat yang belum ada (idempoten, tidak menimpa). --reset: mengembalikan karyawan dan kandidat contoh ke snapshot dan membuang yang bertanda contoh tetapi tidak ada di snapshot (data nyata tidak disentuh). --export: membuat ulang snapshot dari database sekarang.
 * Sumber hulu  : App\Services\SampleHr.
 * Output       : daftar pesan di konsol.
 * Dipakai oleh : php artisan sample:hr [--reset|--export]; layar Data Contoh memakai layanan yang sama.
 * Status       : Aktif.
 */
class SampleHrCommand extends Command
{
    protected $signature = 'sample:hr {--reset : Kembalikan data contoh HR ke snapshot (dan buang yang bertanda contoh tetapi tidak ada di snapshot)} {--export : Buat ulang snapshot dari database sekarang}';

    protected $description = 'Data contoh HR kanonik (karyawan dan kandidat dengan foto dan KTP tiruan) dari snapshot repo';

    public function handle(): int
    {
        if ($this->option('export')) {
            $this->components->info(implode("\n", SampleHr::export()));

            return self::SUCCESS;
        }
        foreach (SampleHr::apply((bool) $this->option('reset')) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
