<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// §6.1 — buat tagihan iuran tiap awal bulan untuk semua anggota aktif.
Schedule::command('dues:generate')->monthlyOn(1, '01:00');

// §K — backup DB harian + ekspor Excel bulanan ke email yayasan.
Schedule::command('backup:run --only-db')->dailyAt('02:00');
Schedule::command('dues:export-monthly')->monthlyOn(1, '03:00');
