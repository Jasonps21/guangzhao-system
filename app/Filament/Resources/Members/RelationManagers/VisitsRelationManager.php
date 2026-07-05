<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Models\MemberVisit;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VisitsRelationManager extends RelationManager
{
    protected static string $relationship = 'visits';

    protected static ?string $title = 'Riwayat Kunjungan';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('visited_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('collector.name')
                    ->label('Petugas')
                    ->placeholder('—'),
                TextColumn::make('outcome')
                    ->label('Hasil')
                    ->badge()
                    ->placeholder('—'),
                ImageColumn::make('photo_path')
                    ->label('Foto')
                    ->disk('public')
                    ->square()
                    ->placeholder('—'),
                TextColumn::make('address_snapshot')
                    ->label('Alamat saat itu')
                    ->limit(40)
                    ->tooltip(fn (MemberVisit $record): ?string => $record->address_snapshot)
                    ->placeholder('—'),
                TextColumn::make('note')
                    ->label('Catatan / patokan')
                    ->limit(40)
                    ->tooltip(fn (MemberVisit $record): ?string => $record->note)
                    ->placeholder('—'),
            ])
            ->defaultSort('visited_at', 'desc')
            ->recordActions([
                Action::make('maps')
                    ->label('Peta')
                    ->icon('heroicon-o-map-pin')
                    ->url(fn (MemberVisit $record): ?string => $record->latitude !== null && $record->longitude !== null
                        ? 'https://www.google.com/maps/search/?api=1&query='.$record->latitude.','.$record->longitude
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn (MemberVisit $record): bool => $record->latitude !== null && $record->longitude !== null),
            ]);
    }
}
