<?php

namespace App\Filament\Resources\CondolencePayments;

use App\Filament\Resources\CondolencePayments\Pages\CreateCondolencePayment;
use App\Filament\Resources\CondolencePayments\Pages\EditCondolencePayment;
use App\Filament\Resources\CondolencePayments\Pages\ListCondolencePayments;
use App\Filament\Resources\CondolencePayments\Schemas\CondolencePaymentForm;
use App\Filament\Resources\CondolencePayments\Tables\CondolencePaymentsTable;
use App\Models\CondolencePayment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CondolencePaymentResource extends Resource
{
    protected static ?string $model = CondolencePayment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?string $navigationLabel = 'Uang Duka';

    protected static string|UnitEnum|null $navigationGroup = 'Sosial';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Uang Duka';

    protected static ?string $pluralModelLabel = 'Uang Duka';

    protected static ?string $recordTitleAttribute = 'receipt_number';

    public static function form(Schema $schema): Schema
    {
        return CondolencePaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CondolencePaymentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCondolencePayments::route('/'),
            'create' => CreateCondolencePayment::route('/create'),
            'edit' => EditCondolencePayment::route('/{record}/edit'),
        ];
    }
}
