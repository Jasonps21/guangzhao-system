<?php

namespace App\Filament\Widgets;

use App\Enums\MemberStatus;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LocationCoverageStats extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $row = Member::query()
            ->where('status', MemberStatus::Aktif)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL AND house_photo_path IS NOT NULL THEN 1 ELSE 0 END) AS complete')
            ->selectRaw('SUM(CASE WHEN latitude IS NULL OR longitude IS NULL THEN 1 ELSE 0 END) AS missing_location')
            ->selectRaw('SUM(CASE WHEN house_photo_path IS NULL THEN 1 ELSE 0 END) AS missing_photo')
            ->first();

        $total = (int) ($row->total ?? 0);
        $complete = (int) ($row->complete ?? 0);
        $missingLocation = (int) ($row->missing_location ?? 0);
        $missingPhoto = (int) ($row->missing_photo ?? 0);

        $percentage = $total > 0 ? (int) round($complete / $total * 100) : 0;

        return [
            Stat::make('Lokasi Rumah Lengkap', $percentage.'%')
                ->description($complete.' dari '.$total.' anggota aktif')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color($percentage >= 80 ? 'success' : ($percentage >= 50 ? 'warning' : 'danger')),

            Stat::make('Belum Ada Titik Lokasi', (string) $missingLocation)
                ->description($missingLocation === 0
                    ? 'Semua titik sudah terkumpul'
                    : 'Perlu diambil titik GPS-nya di lapangan')
                ->descriptionIcon($missingLocation === 0
                    ? 'heroicon-m-check-circle'
                    : 'heroicon-m-arrow-right-circle')
                ->color($missingLocation === 0 ? 'success' : 'danger')
                ->url($this->memberUrl('missing_location')),

            Stat::make('Belum Ada Foto Rumah', (string) $missingPhoto)
                ->description($missingPhoto === 0
                    ? 'Semua rumah sudah difoto'
                    : 'Perlu difoto saat kunjungan')
                ->descriptionIcon($missingPhoto === 0
                    ? 'heroicon-m-check-circle'
                    : 'heroicon-m-arrow-right-circle')
                ->color($missingPhoto === 0 ? 'success' : 'warning')
                ->url($this->memberUrl('missing_house_photo')),
        ];
    }

    /**
     * Buka daftar anggota yang sudah tersaring pada filter terkait, supaya
     * pengurus langsung melihat siapa saja yang dimaksud angka tersebut.
     */
    private function memberUrl(string $filter): string
    {
        // Lihat catatan di DuesStatsOverview: nama parameternya `filters`, bukan
        // `tableFilters`.
        return MemberResource::getUrl('index', [
            'filters' => [$filter => ['isActive' => true]],
        ]);
    }
}
