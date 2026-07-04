<?php

namespace App\Filament\Resources\MemberGroups\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MemberGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Kelompok')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('basis')
                    ->label('Dasar')
                    ->badge(),
                TextColumn::make('members_count')
                    ->label('Jumlah Anggota')
                    ->counts('members')
                    ->badge()
                    ->color('info'),
                TextColumn::make('collectors.name')
                    ->label('Kolektor')
                    ->badge()
                    ->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
