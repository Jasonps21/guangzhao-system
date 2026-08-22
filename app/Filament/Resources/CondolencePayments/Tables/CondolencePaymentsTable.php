<?php

namespace App\Filament\Resources\CondolencePayments\Tables;

use App\Models\CondolencePayment;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CondolencePaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('receipt_number')
                    ->label('No. Nota')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('given_at')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('recipient_name')
                    ->label('Penerima')
                    ->description(fn (CondolencePayment $record): ?string => $record->recipient_member_number)
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Uang Duka')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('escort_name')
                    ->label('Pengantar')
                    ->placeholder('—')
                    ->toggleable()
                    ->visibleFrom('lg'),
                TextColumn::make('recordedBy.name')
                    ->label('Dicatat oleh')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('given_this_year')
                    ->label('Tahun ini')
                    ->query(fn (Builder $query) => $query->whereYear('given_at', now()->year)),
            ])
            ->recordActions([
                // Dua nota terpisah dari satu catatan: lembar untuk keluarga
                // penerima dan lembar arsip pengurus.
                Action::make('printReceipt')
                    ->label('Tanda Terima')
                    ->icon('heroicon-o-printer')
                    ->url(fn (CondolencePayment $record): string => route('condolence.receipt', $record))
                    ->openUrlInNewTab(),
                Action::make('printHandover')
                    ->label('Tanda Pemberian')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (CondolencePayment $record): string => route('condolence.handover', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('given_at', 'desc');
    }
}
