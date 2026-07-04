<?php

namespace App\Filament\Resources\MemberGroups\Schemas;

use App\Enums\GroupBasis;
use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MemberGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Kelompok')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Kelompok')
                            ->placeholder('mis. Kelompok 1 — Daya')
                            ->required(),
                        Select::make('basis')
                            ->label('Dasar Pengelompokan')
                            ->options(GroupBasis::class),
                        Textarea::make('description')
                            ->label('Keterangan')
                            ->rows(2),
                        Select::make('collectors')
                            ->label('Kolektor Penanggung Jawab')
                            ->relationship(
                                name: 'collectors',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->where('role', UserRole::Kolektor->value),
                            )
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Satu kolektor dapat menangani beberapa kelompok.'),
                    ]),
            ]);
    }
}
