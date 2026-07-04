<?php

namespace App\Filament\Widgets;

use App\Models\DuesRecord;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Reactive;

class DuesArrearsList extends TableWidget
{
    #[Reactive]
    public int $year;

    #[Reactive]
    public int $month;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Daftar Tunggakan')
            ->description('Anggota yang belum membayar iuran pada periode ini.')
            ->query(fn (): Builder => DuesRecord::query()
                ->forPeriod($this->year, $this->month)
                ->unpaid()
                ->with('member.group'))
            ->columns([
                TextColumn::make('member.member_number')
                    ->label('No. Anggota'),
                TextColumn::make('member_name')
                    ->label('Nama')
                    ->weight(FontWeight::Medium)
                    ->state(fn (DuesRecord $record): ?string => $record->member?->displayName()),
                TextColumn::make('member.group.name')
                    ->label('Kelompok')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Tanpa Kelompok'),
                TextColumn::make('amount_due')
                    ->label('Tagihan')
                    ->alignEnd()
                    ->color('danger')
                    ->weight(FontWeight::Medium)
                    ->formatStateUsing(fn (mixed $state): string => 'Rp '.number_format((float) $state, 0, ',', '.')),
            ])
            ->emptyStateHeading('Tidak ada tunggakan')
            ->emptyStateDescription('Semua anggota sudah membayar iuran periode ini. 🎉')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
