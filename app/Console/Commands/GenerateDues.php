<?php

namespace App\Console\Commands;

use App\Support\DuesGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateDues extends Command
{
    /**
     * @var string
     */
    protected $signature = 'dues:generate {year? : Tahun periode (default: tahun ini)} {month? : Bulan periode 1-12 (default: bulan ini)}';

    /**
     * @var string
     */
    protected $description = 'Buat tagihan iuran bulanan untuk semua anggota aktif (idempotent).';

    public function handle(DuesGenerator $generator): int
    {
        $now = Carbon::now();
        $year = (int) ($this->argument('year') ?? $now->year);
        $month = (int) ($this->argument('month') ?? $now->month);

        if ($month < 1 || $month > 12) {
            $this->error("Bulan tidak valid: {$month}. Gunakan 1-12.");

            return self::FAILURE;
        }

        $period = Carbon::create($year, $month, 1)->translatedFormat('F Y');
        $this->info("Membuat tagihan iuran untuk periode {$period}...");

        $created = $generator->generate($year, $month);

        $this->info("Selesai. {$created} tagihan baru dibuat (tagihan yang sudah ada dilewati).");

        return self::SUCCESS;
    }
}
