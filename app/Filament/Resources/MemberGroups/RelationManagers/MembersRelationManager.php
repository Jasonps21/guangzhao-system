<?php

namespace App\Filament\Resources\MemberGroups\RelationManagers;

use App\Models\Member;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Anggota Kelompok';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name_indonesian')
            ->columns([
                TextColumn::make('member_number')
                    ->label('No. Anggota')
                    ->searchable(),
                TextColumn::make('name_indonesian')
                    ->label('Nama')
                    ->description(fn (Member $record): ?string => $record->name_pinyin)
                    ->searchable(['name_indonesian', 'name_pinyin', 'name_hanzi']),
                TextColumn::make('monthly_dues_amount')
                    ->label('Iuran/Bulan')
                    ->money('IDR'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->defaultSort('member_number');
    }
}
