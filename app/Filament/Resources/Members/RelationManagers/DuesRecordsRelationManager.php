<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Enums\CollectionMethod;
use App\Enums\DuesStatus;
use App\Models\DuesRecord;
use App\Support\PaymentService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class DuesRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'duesRecords';

    protected static ?string $title = 'Riwayat Iuran';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('period')
                    ->label('Periode')
                    ->state(fn (DuesRecord $record): string => Carbon::create($record->period_year, $record->period_month, 1)->translatedFormat('F Y'))
                    ->sortable(['period_year', 'period_month']),
                TextColumn::make('amount_due')
                    ->label('Tagihan')
                    ->money('IDR'),
                TextColumn::make('amount_paid')
                    ->label('Dibayar')
                    ->money('IDR')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('paid_at')
                    ->label('Tgl Bayar')
                    ->date('d M Y')
                    ->placeholder('—'),
                TextColumn::make('recordedBy.name')
                    ->label('Dicatat oleh')
                    ->placeholder('—'),
            ])
            ->defaultSort('period_year', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(DuesStatus::class),
            ])
            ->headerActions([
                $this->payRangeAction(),
            ])
            ->recordActions([
                Action::make('markPaid')
                    ->label('Tandai Lunas')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (DuesRecord $record): bool => $record->status === DuesStatus::BelumBayar)
                    ->requiresConfirmation()
                    ->action(function (DuesRecord $record): void {
                        app(PaymentService::class)->pay($record, auth()->user(), CollectionMethod::Admin);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markPaidBulk')
                        ->label('Tandai Lunas')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $service = app(PaymentService::class);
                            $user = auth()->user();
                            foreach ($records as $record) {
                                $service->pay($record, $user, CollectionMethod::Admin);
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /**
     * §6.4 — bayar beberapa bulan sekaligus.
     */
    protected function payRangeAction(): Action
    {
        return Action::make('payRange')
            ->label('Bayar Beberapa Bulan')
            ->icon('heroicon-o-calendar-days')
            ->modalWidth(Width::Large)
            ->schema([
                Select::make('year')
                    ->label('Tahun')
                    ->options(self::yearOptions())
                    ->default((int) now()->year)
                    ->required(),
                Select::make('from_month')
                    ->label('Dari Bulan')
                    ->options(self::monthOptions())
                    ->default((int) now()->month)
                    ->required(),
                Select::make('to_month')
                    ->label('Sampai Bulan')
                    ->options(self::monthOptions())
                    ->default(12)
                    ->required(),
            ])
            ->action(function (array $data): void {
                app(PaymentService::class)->payRange(
                    $this->getOwnerRecord(),
                    (int) $data['year'],
                    (int) $data['from_month'],
                    (int) $data['to_month'],
                    auth()->user(),
                    CollectionMethod::Admin,
                );
            });
    }

    /**
     * @return array<int, string>
     */
    protected static function monthOptions(): array
    {
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = Carbon::create(null, $m, 1)->translatedFormat('F');
        }

        return $months;
    }

    /**
     * @return array<int, int>
     */
    protected static function yearOptions(): array
    {
        $current = (int) now()->year;
        $years = [];
        for ($y = $current - 2; $y <= $current + 1; $y++) {
            $years[$y] = $y;
        }

        return $years;
    }
}
