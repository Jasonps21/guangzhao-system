<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Enums\RelationshipType;
use App\Models\Member;
use App\Models\MemberRelationship;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FamilyRelationshipsRelationManager extends RelationManager
{
    protected static string $relationship = 'familyRelationships';

    protected static ?string $title = 'Kekeluargaan';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('relationship_type')
                ->label('Hubungan')
                ->options(RelationshipType::class)
                ->required()
                ->helperText('Dari sudut pandang anggota ini. Sisi kebalikannya dibuat otomatis.'),
            Select::make('related_member_id')
                ->label('Anggota')
                ->required()
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => $this->searchableMembers()
                    ->where(function (Builder $query) use ($search): void {
                        $query->where('name_indonesian', 'like', "%{$search}%")
                            ->orWhere('name_pinyin', 'like', "%{$search}%")
                            ->orWhere('name_hanzi', 'like', "%{$search}%")
                            ->orWhere('member_number', 'like', "%{$search}%");
                    })
                    ->limit(30)
                    ->get()
                    ->mapWithKeys(fn (Member $member): array => [
                        $member->id => $member->displayName().' ('.$member->member_number.')',
                    ])
                    ->all())
                ->getOptionLabelUsing(fn (mixed $value): ?string => Member::find($value)?->displayName()),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('relationship_type')
                    ->label('Hubungan')
                    ->badge()
                    ->state(fn (MemberRelationship $record): string => $record->label()),
                TextColumn::make('relatedMember.name_indonesian')
                    ->label('Anggota')
                    ->state(fn (MemberRelationship $record): string => $record->relatedMember?->displayName() ?? '—')
                    ->searchable(['name_indonesian', 'name_pinyin', 'name_hanzi']),
                TextColumn::make('relatedMember.member_number')
                    ->label('Nomor Anggota')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Kekeluargaan'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * Anggota yang boleh dipilih: bukan diri sendiri dan belum direlasikan.
     *
     * @return Builder<Member>
     */
    protected function searchableMembers(): Builder
    {
        $owner = $this->getOwnerRecord();

        return Member::query()
            ->whereKeyNot($owner->getKey())
            ->whereNotIn('id', $owner->familyRelationships()->pluck('related_member_id'));
    }
}
