<?php

namespace App\Filament\Resources\DuesRecords\Schemas;

use App\Enums\CollectionMethod;
use App\Enums\DuesStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class DuesRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('member_id')
                    ->label('Anggota')
                    ->relationship('member', 'name_indonesian')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('period_year')
                    ->label('Tahun')
                    ->numeric()
                    ->default((int) now()->year)
                    ->required(),
                TextInput::make('period_month')
                    ->label('Bulan')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(12)
                    ->default((int) now()->month)
                    ->required(),
                TextInput::make('amount_due')
                    ->label('Tagihan (Rp)')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),
                Select::make('status')
                    ->label('Status')
                    ->options(DuesStatus::class)
                    ->default(DuesStatus::BelumBayar)
                    ->live()
                    ->required(),
                TextInput::make('amount_paid')
                    ->label('Dibayar (Rp)')
                    ->numeric()
                    ->prefix('Rp')
                    ->visible(fn (Get $get): bool => $get('status') === DuesStatus::Lunas->value),
                DatePicker::make('paid_at')
                    ->label('Tanggal Bayar')
                    ->visible(fn (Get $get): bool => $get('status') === DuesStatus::Lunas->value),
                Select::make('collection_method')
                    ->label('Metode')
                    ->options(CollectionMethod::class)
                    ->visible(fn (Get $get): bool => $get('status') === DuesStatus::Lunas->value),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }
}
