<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Akun Pengguna')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama')
                            ->required(),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('password')
                            ->label('Kata Sandi')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->helperText('Kosongkan untuk mempertahankan kata sandi lama saat mengedit.'),
                        Select::make('role')
                            ->label('Peran')
                            ->options(UserRole::class)
                            ->default(UserRole::Admin)
                            ->live()
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                        Select::make('groups')
                            ->label('Kelompok yang Ditangani')
                            ->relationship('groups', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->visible(fn (Get $get): bool => in_array($get('role'), [UserRole::Kolektor, UserRole::Kolektor->value], true))
                            ->helperText('Hanya berlaku untuk kolektor.'),
                    ]),
            ]);
    }
}
