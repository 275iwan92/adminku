<?php

namespace App\Console\Commands;

use App\Services\StockConsistency;
use Illuminate\Console\Command;

/* REMARK
 * Tujuan       : Memeriksa konsistensi stok (saldo batch = buku besar, rantai saldo, biaya rata-rata, nilai stok, dokumen) pada database yang sedang dipakai. HANYA MEMBACA: data yang tidak konsisten dilaporkan, tidak diperbaiki.
 * Output       : tabel pemeriksaan + rincian temuan; kode keluar 1 bila ada temuan (bisa dipasang di cron / cadangan).
 * Dipakai oleh : php artisan sstock:check-stock.
 * Status       : Aktif.
 */
class StockCheckCommand extends Command
{
    protected $signature = 'sstock:check-stock {--limit=10 : jumlah rincian yang ditampilkan per pemeriksaan}';

    protected $description = 'Periksa konsistensi stok (hanya membaca, tidak memperbaiki data)';

    private const LABELS = [
        'saldo' => 'Saldo batch = saldo terakhir buku besar', 'jumlah' => 'Saldo batch = jumlah qty buku besar', 'rantai' => 'Rantai saldo (sebelumnya + qty = sesudahnya)',
        'biaya' => 'Biaya rata-rata bergerak dihitung ulang', 'nilai' => 'Nilai stok = qty x biaya rata-rata', 'negatif' => 'Tidak ada saldo minus', 'dokumen' => 'Pergerakan sama dengan baris dokumen',
    ];

    public function handle(): int
    {
        $result = StockConsistency::run();
        $bad = 0;
        $this->info(sprintf('%d batch, %d pergerakan buku besar, nilai stok %s', $result['summary']['batch'], $result['summary']['pergerakan'], number_format($result['summary']['nilai_stok'], 2, ',', '.')));
        foreach ($result['problems'] as $check => $list) {
            $bad += count($list);
            $this->line(sprintf('%-48s %s', self::LABELS[$check], $list ? '<error> '.count($list).' temuan </error>' : '<info>OK</info>'));
            foreach (array_slice($list, 0, (int) $this->option('limit')) as $line) {
                $this->line('    - '.$line);
            }
            if (count($list) > (int) $this->option('limit')) {
                $this->line('    ... dan '.(count($list) - (int) $this->option('limit')).' lainnya');
            }
        }

        return $bad === 0 ? self::SUCCESS : self::FAILURE;
    }
}
