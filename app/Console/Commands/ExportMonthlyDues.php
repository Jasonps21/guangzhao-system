<?php

namespace App\Console\Commands;

use App\Exports\MonthlyDuesExport;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;

class ExportMonthlyDues extends Command
{
    /**
     * @var string
     */
    protected $signature = 'dues:export-monthly {year?} {month?}';

    /**
     * @var string
     */
    protected $description = 'Ekspor rekap iuran bulanan ke Excel dan kirim ke email yayasan. (§K)';

    public function handle(): int
    {
        $now = Carbon::now()->subMonthNoOverflow();
        $year = (int) ($this->argument('year') ?? $now->year);
        $month = (int) ($this->argument('month') ?? $now->month);

        $filename = "rekap-iuran-{$year}-".str_pad((string) $month, 2, '0', STR_PAD_LEFT).'.xlsx';
        $path = 'exports/'.$filename;

        Excel::store(new MonthlyDuesExport($year, $month), $path, 'local');

        $email = config('mail.foundation_address');

        if ($email) {
            $period = Carbon::create($year, $month, 1)->translatedFormat('F Y');
            Mail::raw("Terlampir rekap iuran periode {$period}.", function ($message) use ($email, $path, $filename): void {
                $message->to($email)
                    ->subject('Rekap Iuran '.config('app.name'))
                    ->attach(storage_path('app/private/'.$path), ['as' => $filename]);
            });

            $this->info("Rekap dikirim ke {$email}.");
        } else {
            $this->warn('FOUNDATION_EMAIL belum diatur — file disimpan di storage/app/private/'.$path);
        }

        return self::SUCCESS;
    }
}
