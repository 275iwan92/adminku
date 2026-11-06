<?php

namespace App\Console\Commands;

use App\Models\DeliveryPosition;
use Illuminate\Console\Command;

/* REMARK
 * Tujuan       : Menghapus riwayat posisi GPS sopir yang lebih dari 7 hari (privasi).
 * Output       : baris delivery_position terhapus.
 * Dipakai oleh : routes/console.php (harian).
 * Status       : Aktif.
 */
class PruneDeliveryPositionsCommand extends Command
{
    protected $signature = 'delivery:prune-positions {--days=7}';

    protected $description = 'Hapus posisi GPS sopir yang lebih lama dari 7 hari';

    public function handle(): int
    {
        $n = DeliveryPosition::query()->where('recorded_at', '<', now()->subDays((int) $this->option('days')))->delete();
        $this->info("$n posisi dihapus.");

        return self::SUCCESS;
    }
}
