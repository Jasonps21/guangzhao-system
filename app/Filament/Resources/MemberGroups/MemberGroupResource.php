<?php

namespace App\Filament\Resources\MemberGroups;

use App\Filament\Resources\MemberGroups\Pages\CreateMemberGroup;
use App\Filament\Resources\MemberGroups\Pages\EditMemberGroup;
use App\Filament\Resources\MemberGroups\Pages\ListMemberGroups;
use App\Filament\Resources\MemberGroups\RelationManagers\MembersRelationManager;
use App\Filament\Resources\MemberGroups\Schemas\MemberGroupForm;
use App\Filament\Resources\MemberGroups\Tables\MemberGroupsTable;
use App\Models\MemberGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MemberGroupResource extends Resource
{
    protected static ?string $model = MemberGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Kelompok';

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Kelompok';

    protected static ?string $pluralModelLabel = 'Kelompok';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return MemberGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MemberGroupsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MembersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMemberGroups::route('/'),
            'create' => CreateMemberGroup::route('/create'),
            'edit' => EditMemberGroup::route('/{record}/edit'),
        ];
    }
}
