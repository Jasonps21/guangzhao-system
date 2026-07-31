<?php

namespace App\Filament\Widgets;

use App\Enums\DuesStatus;
use App\Enums\MemberStatus;
use App\Filament\Resources\DuesRecords\DuesRecordResource;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\PaymentSubmissions\PaymentSubmissionResource;
use App\Models\DuesRecord;
use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class DuesStatsOverview extends BaseWidget
{
    /**
     * Angka iuran tidak berubah tiap detik; polling hanya membebani server
     * dan basis data tanpa memberi informasi baru kepada pengurus.
     */
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $year = (int) now()->year;
        $month = (int) now()->month;
        $period = now()->translatedFormat('F Y');

        $current = $this->periodTotals($year, $month);
        $history = $this->recentMonths();

        return [
            Stat::make('Iuran Terkumpul ('.$period.')', 'Rp '.number_format($current['collected'], 0, ',', '.'))
                ->description($this->trendDescription($history['collected']))
                ->descriptionIcon($this->trendIcon($history['collected']))
                ->chart($history['collected'])
                ->color('success')
                ->url($this->duesUrl(DuesStatus::Lunas, $year, $month)),

            Stat::make('Tunggakan ('.$period.')', 'Rp '.number_format($current['arrears'], 0, ',', '.'))
                ->description($current['arrearsCount'].' tagihan belum dibayar')
                ->descriptionIcon('heroicon-m-arrow-right-circle')
                ->chart($history['arrears'])
                ->color('danger')
                ->url($this->duesUrl(DuesStatus::BelumBayar, $year, $month)),

            Stat::make('Menunggu Persetujuan', (string) $current['pendingCount'])
                ->description($current['pendingCount'] > 0
                    ? 'Setoran kolektor perlu diverifikasi'
                    : 'Tidak ada setoran tertunda')
                ->descriptionIcon($current['pendingCount'] > 0
                    ? 'heroicon-m-exclamation-circle'
                    : 'heroicon-m-check-circle')
                ->color($current['pendingCount'] > 0 ? 'warning' : 'gray')
                ->url(PaymentSubmissionResource::getUrl('index')),

            Stat::make('Anggota Aktif', (string) $current['activeMembers'])
                ->description('Sedang ditagih iuran')
                ->descriptionIcon('heroicon-m-arrow-right-circle')
                ->color('primary')
                ->url(MemberResource::getUrl('index')),
        ];
    }

    /**
     * @return array{collected: float, arrears: float, arrearsCount: int, pendingCount: int, activeMembers: int}
     */
    private function periodTotals(int $year, int $month): array
    {
        /**
         * Satu query agregat untuk seluruh periode, bukan lima query terpisah
         * seperti sebelumnya.
         */
        $row = DuesRecord::query()
            ->forPeriod($year, $month)
            ->selectRaw('SUM(CASE WHEN status = ? THEN amount_paid ELSE 0 END) AS collected', [DuesStatus::Lunas->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN amount_due ELSE 0 END) AS arrears', [DuesStatus::BelumBayar->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS arrears_count', [DuesStatus::BelumBayar->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending_count', [DuesStatus::MenungguPersetujuan->value])
            ->first();

        return [
            'collected' => (float) ($row->collected ?? 0),
            'arrears' => (float) ($row->arrears ?? 0),
            'arrearsCount' => (int) ($row->arrears_count ?? 0),
            'pendingCount' => (int) ($row->pending_count ?? 0),
            'activeMembers' => Member::query()->where('status', MemberStatus::Aktif)->count(),
        ];
    }

    /**
     * Enam periode terakhir sebagai garis mini di kartu — angka satu bulan
     * tanpa pembanding tidak memberi tahu apakah keadaan membaik atau memburuk.
     *
     * @return array{collected: array<int, float>, arrears: array<int, float>}
     */
    private function recentMonths(): array
    {
        $months = collect(range(5, 0))
            ->map(fn (int $back): Carbon => now()->startOfMonth()->subMonths($back));

        $totals = DuesRecord::query()
            ->where(function ($query) use ($months): void {
                foreach ($months as $month) {
                    $query->orWhere(fn ($q) => $q
                        ->where('period_year', (int) $month->year)
                        ->where('period_month', (int) $month->month));
                }
            })
            ->selectRaw('period_year, period_month')
            ->selectRaw('SUM(CASE WHEN status = ? THEN amount_paid ELSE 0 END) AS collected', [DuesStatus::Lunas->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN amount_due ELSE 0 END) AS arrears', [DuesStatus::BelumBayar->value])
            ->groupBy('period_year', 'period_month')
            ->get()
            ->keyBy(fn ($row): string => $row->period_year.'-'.$row->period_month);

        $collected = [];
        $arrears = [];

        foreach ($months as $month) {
            $row = $totals->get($month->year.'-'.$month->month);
            $collected[] = (float) ($row->collected ?? 0);
            $arrears[] = (float) ($row->arrears ?? 0);
        }

        return ['collected' => $collected, 'arrears' => $arrears];
    }

    /**
     * @param  array<int, float>  $series
     */
    private function trendDescription(array $series): string
    {
        $previous = $series[count($series) - 2] ?? 0.0;
        $latest = $series[count($series) - 1] ?? 0.0;

        if ($previous <= 0.0) {
            return 'Pembayaran lunas periode berjalan';
        }

        $change = (int) round(($latest - $previous) / $previous * 100);

        return match (true) {
            $change > 0 => $change.'% lebih tinggi dari bulan lalu',
            $change < 0 => abs($change).'% lebih rendah dari bulan lalu',
            default => 'Sama dengan bulan lalu',
        };
    }

    /**
     * @param  array<int, float>  $series
     */
    private function trendIcon(array $series): string
    {
        $previous = $series[count($series) - 2] ?? 0.0;
        $latest = $series[count($series) - 1] ?? 0.0;

        return $latest >= $previous
            ? 'heroicon-m-arrow-trending-up'
            : 'heroicon-m-arrow-trending-down';
    }

    /**
     * Tautan ke daftar iuran yang sudah tersaring — angka di dasbor jadi titik
     * awal penelusuran, bukan sekadar papan pengumuman.
     */
    private function duesUrl(DuesStatus $status, int $year, int $month): string
    {
        // Filament v5 mem-bind properti `tableFilters` ke URL dengan nama `filters`
        // (#[Url(as: 'filters')] pada ListRecords). Memakai `tableFilters` di sini
        // membuat parameternya diabaikan tanpa pesan kesalahan apa pun.
        return DuesRecordResource::getUrl('index', [
            'filters' => [
                'status' => ['value' => $status->value],
                'period' => ['year' => $year, 'month' => $month],
            ],
        ]);
    }
}
