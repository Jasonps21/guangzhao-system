<?php

namespace App\Filament\Widgets;

use App\Support\DuesReport;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;
use Livewire\Attributes\Reactive;

class DuesGroupSummary extends TableWidget
{
    #[Reactive]
    public int $year;

    #[Reactive]
    public int $month;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Rekap Per Kelompok')
            ->description('Perbandingan iuran terkumpul dan tunggakan tiap kelompok.')
            ->records(fn (): Collection => app(DuesReport::class)
                ->perGroup($this->year, $this->month)
                ->values())
            ->paginated(false)
            ->columns([
                TextColumn::make('group')
                    ->label('Kelompok')
                    ->weight(FontWeight::Medium)
                    ->icon('heroicon-o-user-group')
                    ->iconColor('primary'),
                TextColumn::make('collected')
                    ->label('Terkumpul')
                    ->alignEnd()
                    ->color('success')
                    ->weight(FontWeight::Medium)
                    ->formatStateUsing($this->rupiah(...)),
                TextColumn::make('arrears')
                    ->label('Tunggakan')
                    ->alignEnd()
                    ->color('danger')
                    ->weight(FontWeight::Medium)
                    ->formatStateUsing($this->rupiah(...)),
                TextColumn::make('paid_count')
                    ->label('Lunas')
                    ->alignCenter()
                    ->badge()
                    ->color('success'),
                TextColumn::make('unpaid_count')
                    ->label('Belum')
                    ->alignCenter()
                    ->badge()
                    ->color('danger'),
            ])
            ->emptyStateHeading('Belum ada data')
            ->emptyStateDescription('Belum ada data untuk periode ini.')
            ->emptyStateIcon('heroicon-o-chart-bar');
    }

    protected function rupiah(mixed $state): string
    {
        return 'Rp '.number_format((float) $state, 0, ',', '.');
    }
}
