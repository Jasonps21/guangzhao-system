<?php

namespace App\Filament\Widgets;

use App\Enums\DuesStatus;
use App\Enums\MemberStatus;
use App\Models\DuesRecord;
use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DuesStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $year = (int) now()->year;
        $month = (int) now()->month;

        $collected = DuesRecord::query()
            ->forPeriod($year, $month)
            ->where('status', DuesStatus::Lunas)
            ->sum('amount_paid');

        $arrearsAmount = DuesRecord::query()
            ->forPeriod($year, $month)
            ->where('status', DuesStatus::BelumBayar)
            ->sum('amount_due');

        $arrearsCount = DuesRecord::query()
            ->forPeriod($year, $month)
            ->where('status', DuesStatus::BelumBayar)
            ->count();

        $pendingCount = DuesRecord::query()
            ->forPeriod($year, $month)
            ->where('status', DuesStatus::MenungguPersetujuan)
            ->count();

        $activeMembers = Member::query()->where('status', MemberStatus::Aktif)->count();

        $period = now()->translatedFormat('F Y');

        return [
            Stat::make('Iuran Terkumpul ('.$period.')', 'Rp '.number_format((float) $collected, 0, ',', '.'))
                ->description('Pembayaran lunas periode berjalan')
                ->color('success'),
            Stat::make('Tunggakan ('.$period.')', 'Rp '.number_format((float) $arrearsAmount, 0, ',', '.'))
                ->description($arrearsCount.' tagihan belum dibayar')
                ->color('danger'),
            Stat::make('Menunggu Persetujuan ('.$period.')', (string) $pendingCount)
                ->description('Setoran kolektor belum diverifikasi')
                ->color('warning'),
            Stat::make('Anggota Aktif', (string) $activeMembers)
                ->description('Sedang ditagih iuran')
                ->color('primary'),
        ];
    }
}
