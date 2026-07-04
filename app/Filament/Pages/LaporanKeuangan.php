<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use UnitEnum;

class LaporanKeuangan extends Page
{
    protected string $view = 'filament.pages.laporan-keuangan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Laporan Keuangan';

    protected static string|UnitEnum|null $navigationGroup = 'Iuran';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Laporan Keuangan';

    /**
     * Selected period in `Y-m` format (bound to the month input).
     */
    public string $period;

    public function mount(): void
    {
        $this->period = now()->format('Y-m');
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $date = $this->resolvePeriod();

        return [
            'year' => (int) $date->year,
            'month' => (int) $date->month,
            'periodLabel' => $date->translatedFormat('F Y'),
        ];
    }

    private function resolvePeriod(): Carbon
    {
        return rescue(
            fn (): Carbon => Carbon::createFromFormat('Y-m', $this->period)->startOfMonth(),
            now()->startOfMonth(),
            report: false,
        );
    }
}
