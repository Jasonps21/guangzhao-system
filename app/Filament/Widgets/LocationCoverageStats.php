<?php

namespace App\Filament\Widgets;

use App\Enums\MemberStatus;
use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class LocationCoverageStats extends BaseWidget
{
    protected function getStats(): array
    {
        $active = fn (): Builder => Member::query()->where('status', MemberStatus::Aktif);

        $total = $active()->count();
        $complete = $active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotNull('house_photo_path')
            ->count();
        $missingLocation = $active()
            ->where(fn (Builder $q) => $q->whereNull('latitude')->orWhereNull('longitude'))
            ->count();
        $missingPhoto = $active()->whereNull('house_photo_path')->count();

        $percentage = $total > 0 ? (int) round($complete / $total * 100) : 0;

        return [
            Stat::make('Lokasi Rumah Lengkap', $percentage.'%')
                ->description($complete.' dari '.$total.' anggota aktif')
                ->color($percentage >= 80 ? 'success' : ($percentage >= 50 ? 'warning' : 'danger')),
            Stat::make('Belum Ada Titik Lokasi', (string) $missingLocation)
                ->description('Perlu diambil titik GPS-nya di lapangan')
                ->color($missingLocation === 0 ? 'success' : 'danger'),
            Stat::make('Belum Ada Foto Rumah', (string) $missingPhoto)
                ->description('Perlu difoto saat kunjungan')
                ->color($missingPhoto === 0 ? 'success' : 'warning'),
        ];
    }
}
