<?php

namespace App\Filament\Resources\DuesRecords\Tables;

use App\Enums\CollectionMethod;
use App\Enums\DuesStatus;
use App\Models\DuesRecord;
use App\Support\PaymentService;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class DuesRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member.member_number')
                    ->label('No. Anggota')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('member.name_indonesian')
                    ->label('Nama')
                    ->searchable(['name_indonesian', 'name_pinyin', 'name_hanzi'])
                    ->description(fn (DuesRecord $record): ?string => $record->member?->group?->name),
                TextColumn::make('period')
                    ->label('Periode')
                    ->state(fn (DuesRecord $record): string => Carbon::create($record->period_year, $record->period_month, 1)->translatedFormat('M Y'))
                    ->sortable(['period_year', 'period_month']),
                TextColumn::make('amount_due')
                    ->label('Tagihan')
                    ->money('IDR'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('paid_at')
                    ->label('Tgl Bayar')
                    ->date('d M Y')
                    ->placeholder('—'),
                TextColumn::make('recordedBy.name')
                    ->label('Dicatat oleh')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('collection_method')
                    ->label('Metode')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(DuesStatus::class),
                SelectFilter::make('group')
                    ->label('Kelompok')
                    ->relationship('member.group', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('period')
                    ->schema([
                        TextInput::make('year')
                            ->label('Tahun')
                            ->numeric(),
                        Select::make('month')
                            ->label('Bulan')
                            ->options(array_combine(range(1, 12), array_map(
                                fn (int $m): string => Carbon::create(null, $m, 1)->translatedFormat('F'),
                                range(1, 12),
                            ))),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['year'] ?? null, fn (Builder $q, $year) => $q->where('period_year', $year))
                            ->when($data['month'] ?? null, fn (Builder $q, $month) => $q->where('period_month', $month));
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // §6.3 — admin menandai lunas secara massal.
                    BulkAction::make('markPaid')
                        ->label('Tandai Lunas')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Tandai lunas?')
                        ->modalDescription('Tagihan terpilih akan ditandai LUNAS sesuai nominal tagihan.')
                        ->action(function (Collection $records): void {
                            $service = app(PaymentService::class);
                            $user = auth()->user();
                            foreach ($records as $record) {
                                $service->pay($record, $user, CollectionMethod::Admin);
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('period_year', 'desc');
    }
}
