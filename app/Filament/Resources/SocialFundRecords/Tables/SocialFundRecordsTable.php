<?php

namespace App\Filament\Resources\SocialFundRecords\Tables;

use App\Enums\SocialFundDirection;
use App\Enums\SocialFundForm;
use App\Models\SocialFundRecord;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SocialFundRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('receipt_number')
                    ->label('No. Nota')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('transacted_at')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('direction')
                    ->label('Arah')
                    ->badge(),
                TextColumn::make('party_name')
                    ->label('Nama')
                    ->description(fn (SocialFundRecord $record): ?string => $record->party_address)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('fund_form')
                    ->label('Bentuk')
                    ->badge()
                    ->color('gray')
                    ->visibleFrom('lg'),
                TextColumn::make('amount')
                    ->label('Jumlah')
                    // Barang tanpa taksiran nilai tampil sebagai deskripsi barangnya.
                    ->state(fn (SocialFundRecord $record): string => $record->amount !== null
                        ? 'Rp '.number_format($record->amount, 0, ',', '.')
                        : ($record->item_description ?? '—'))
                    ->description(fn (SocialFundRecord $record): ?string => $record->amount !== null && $record->isInGoods()
                        ? $record->item_description
                        : null),
                TextColumn::make('purpose')
                    ->label('Keperluan')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable()
                    ->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('direction')
                    ->label('Arah Dana')
                    ->options(SocialFundDirection::class),
                SelectFilter::make('fund_form')
                    ->label('Bentuk')
                    ->options(SocialFundForm::class),
            ])
            ->recordActions([
                Action::make('print')
                    ->label('Cetak Nota')
                    ->icon('heroicon-o-printer')
                    ->url(fn (SocialFundRecord $record): string => route('social-fund.receipt', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('transacted_at', 'desc');
    }
}
