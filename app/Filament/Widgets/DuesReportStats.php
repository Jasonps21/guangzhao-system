<?php

namespace App\Filament\Widgets;

use App\Support\DuesReport;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

class DuesReportStats extends StatsOverviewWidget
{
    #[Reactive]
    public int $year;

    #[Reactive]
    public int $month;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $report = app(DuesReport::class);

        $totals = $report->totals($this->year, $this->month);
        $perGroup = $report->perGroup($this->year, $this->month);

        $paidCount = (int) $perGroup->sum('paid_count');
        $unpaidCount = (int) $perGroup->sum('unpaid_count');
        $expected = $totals['collected'] + $totals['arrears'];
        $rate = $expected > 0 ? round(($totals['collected'] / $expected) * 100, 1) : 0.0;

        $rupiah = fn (float $value): string => 'Rp '.number_format($value, 0, ',', '.');

        return [
            Stat::make('Terkumpul', $rupiah($totals['collected']))
                ->description($paidCount.' pembayaran lunas')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('Tunggakan', $rupiah($totals['arrears']))
                ->description($unpaidCount.' tagihan belum dibayar')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
            Stat::make('Target Iuran', $rupiah($expected))
                ->description(($paidCount + $unpaidCount).' tagihan periode ini')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('gray'),
            Stat::make('Tingkat Penagihan', number_format($rate, 1, ',', '.').'%')
                ->description('dari target iuran')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color('warning'),
        ];
    }
}
