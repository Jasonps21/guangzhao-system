<?php

namespace App\Filament\Resources\Members\Tables;

use App\Enums\MemberStatus;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member_number')
                    ->label('No. Anggota')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name_indonesian')
                    ->label('Nama')
                    ->searchable(['name_indonesian', 'name_pinyin', 'name_hanzi'])
                    ->sortable(),
                TextColumn::make('name_hanzi')
                    ->label('中文名 (Mandarin)')
                    ->description(fn ($record): ?string => $record->name_pinyin)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('group.name')
                    ->label('Kelompok')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('monthly_dues_amount')
                    ->label('Iuran/Bulan')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('phone')
                    ->label('Telepon')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(MemberStatus::class),
                SelectFilter::make('group')
                    ->label('Kelompok')
                    ->relationship('group', 'name')
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('card')
                    ->label('Kartu')
                    ->icon('heroicon-o-identification')
                    ->url(fn ($record): string => route('members.card', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('member_number');
    }
}
