<?php

namespace App\Filament\Resources\DuesRecords;

use App\Filament\Resources\DuesRecords\Pages\CreateDuesRecord;
use App\Filament\Resources\DuesRecords\Pages\EditDuesRecord;
use App\Filament\Resources\DuesRecords\Pages\ListDuesRecords;
use App\Filament\Resources\DuesRecords\Schemas\DuesRecordForm;
use App\Filament\Resources\DuesRecords\Tables\DuesRecordsTable;
use App\Models\DuesRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DuesRecordResource extends Resource
{
    protected static ?string $model = DuesRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Iuran';

    protected static string|UnitEnum|null $navigationGroup = 'Iuran';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Tagihan Iuran';

    protected static ?string $pluralModelLabel = 'Tagihan Iuran';

    public static function form(Schema $schema): Schema
    {
        return DuesRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DuesRecordsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDuesRecords::route('/'),
            'create' => CreateDuesRecord::route('/create'),
            'edit' => EditDuesRecord::route('/{record}/edit'),
        ];
    }
}
