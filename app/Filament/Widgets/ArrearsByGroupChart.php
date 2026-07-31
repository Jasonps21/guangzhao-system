<?php

namespace App\Filament\Widgets;

use App\Enums\DuesStatus;
use App\Enums\MemberStatus;
use App\Models\DuesRecord;
use Filament\Widgets\ChartWidget;

/**
 * Status penagihan per kelompok pada periode berjalan.
 *
 * Dasbor lama hanya memberi satu angka tunggakan untuk seluruh perkumpulan,
 * yang tidak menjawab pertanyaan sebenarnya: kelompok mana yang perlu didorong.
 * Diurutkan dari yang tunggakannya terbanyak.
 *
 * Warna biru/oranye dipilih lewat validator palet, bukan hijau/merah — pasangan
 * hijau-merah tidak terbedakan oleh mata buta warna deutan/protan (ΔE 4,6).
 */
class ArrearsByGroupChart extends ChartWidget
{
    protected ?string $heading = 'Status Penagihan per Kelompok';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    public function getDescription(): ?string
    {
        return 'Periode '.now()->translatedFormat('F Y').'. Kelompok dengan tunggakan terbanyak ada di atas.';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = DuesRecord::query()
            ->join('members', 'members.id', '=', 'dues_records.member_id')
            ->join('member_groups', 'member_groups.id', '=', 'members.group_id')
            ->where('dues_records.period_year', (int) now()->year)
            ->where('dues_records.period_month', (int) now()->month)
            ->where('members.status', MemberStatus::Aktif)
            ->selectRaw('member_groups.name AS group_name')
            ->selectRaw('SUM(CASE WHEN dues_records.status = ? THEN 1 ELSE 0 END) AS paid', [DuesStatus::Lunas->value])
            ->selectRaw('SUM(CASE WHEN dues_records.status = ? THEN 1 ELSE 0 END) AS unpaid', [DuesStatus::BelumBayar->value])
            ->groupBy('member_groups.name')
            ->get()
            ->sortByDesc(fn ($row): int => (int) $row->unpaid)
            ->values();

        return [
            'datasets' => [
                [
                    'label' => 'Belum bayar',
                    'data' => $rows->map(fn ($r): int => (int) $r->unpaid)->all(),
                    'backgroundColor' => '#d97706',
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Lunas',
                    'data' => $rows->map(fn ($r): int => (int) $r->paid)->all(),
                    'backgroundColor' => '#2563eb',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $rows->map(fn ($r): string => $r->group_name)->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            // Batang mendatar: nama kelompok terbaca utuh tanpa dimiringkan.
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'x' => [
                    'stacked' => true,
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
                'y' => [
                    'stacked' => true,
                    'grid' => ['display' => false],
                ],
            ],
        ];
    }
}
