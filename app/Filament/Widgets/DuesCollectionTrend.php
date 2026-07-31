<?php

namespace App\Filament\Widgets;

use App\Enums\DuesStatus;
use App\Models\DuesRecord;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Penerimaan iuran 12 bulan terakhir.
 *
 * Satu deret saja, jadi tidak perlu legenda — judulnya sudah menamai datanya.
 */
class DuesCollectionTrend extends ChartWidget
{
    protected ?string $heading = 'Penerimaan Iuran 12 Bulan Terakhir';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    public function getDescription(): ?string
    {
        return 'Jumlah iuran yang lunas per bulan. Klik kartu "Iuran Terkumpul" di atas untuk melihat rinciannya.';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $months = collect(range(11, 0))
            ->map(fn (int $back): Carbon => now()->startOfMonth()->subMonths($back));

        $totals = DuesRecord::query()
            ->where('status', DuesStatus::Lunas)
            ->where(function ($query) use ($months): void {
                foreach ($months as $month) {
                    $query->orWhere(fn ($q) => $q
                        ->where('period_year', (int) $month->year)
                        ->where('period_month', (int) $month->month));
                }
            })
            ->selectRaw('period_year, period_month, SUM(amount_paid) AS total')
            ->groupBy('period_year', 'period_month')
            ->get()
            ->keyBy(fn ($row): string => $row->period_year.'-'.$row->period_month);

        return [
            'datasets' => [
                [
                    'label' => 'Iuran terkumpul (Rp)',
                    'data' => $months
                        ->map(fn (Carbon $m): float => (float) ($totals->get($m->year.'-'.$m->month)->total ?? 0))
                        ->all(),
                    'backgroundColor' => '#2563eb',
                    'borderColor' => '#2563eb',
                    'borderRadius' => 4,
                    'maxBarThickness' => 34,
                ],
            ],
            'labels' => $months
                ->map(fn (Carbon $m): string => $m->translatedFormat('M y'))
                ->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                // Satu deret: legenda hanya mengulang judul.
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'grid' => ['drawBorder' => false],
                ],
                'x' => [
                    'grid' => ['display' => false],
                ],
            ],
        ];
    }
}
