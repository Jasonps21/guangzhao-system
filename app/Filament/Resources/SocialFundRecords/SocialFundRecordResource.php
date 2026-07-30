<?php

namespace App\Filament\Resources\SocialFundRecords;

use App\Filament\Resources\SocialFundRecords\Pages\CreateSocialFundRecord;
use App\Filament\Resources\SocialFundRecords\Pages\EditSocialFundRecord;
use App\Filament\Resources\SocialFundRecords\Pages\ListSocialFundRecords;
use App\Filament\Resources\SocialFundRecords\Schemas\SocialFundRecordForm;
use App\Filament\Resources\SocialFundRecords\Tables\SocialFundRecordsTable;
use App\Models\SocialFundRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SocialFundRecordResource extends Resource
{
    protected static ?string $model = SocialFundRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $navigationLabel = 'Dana Sosial';

    protected static string|UnitEnum|null $navigationGroup = 'Sosial';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Dana Sosial';

    protected static ?string $pluralModelLabel = 'Dana Sosial';

    protected static ?string $recordTitleAttribute = 'receipt_number';

    public static function form(Schema $schema): Schema
    {
        return SocialFundRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SocialFundRecordsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSocialFundRecords::route('/'),
            'create' => CreateSocialFundRecord::route('/create'),
            'edit' => EditSocialFundRecord::route('/{record}/edit'),
        ];
    }
}
