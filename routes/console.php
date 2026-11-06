<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Privasi: riwayat posisi GPS sopir disimpan 7 hari.
Schedule::command('delivery:prune-positions')->daily();
