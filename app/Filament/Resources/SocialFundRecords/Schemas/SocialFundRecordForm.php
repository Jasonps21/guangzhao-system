<?php

namespace App\Filament\Resources\SocialFundRecords\Schemas;

use App\Enums\SocialFundDirection;
use App\Enums\SocialFundForm;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SocialFundRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Jenis Pencatatan')
                    ->columns(2)
                    ->schema([
                        Radio::make('direction')
                            ->label('Arah Dana')
                            ->options(SocialFundDirection::class)
                            ->default(SocialFundDirection::Masuk)
                            ->required()
                            ->live()
                            ->helperText('Masuk = sumbangan diterima dari donatur. Keluar = bantuan diberikan perkumpulan.'),
                        Radio::make('fund_form')
                            ->label('Bentuk')
                            ->options(SocialFundForm::class)
                            ->default(SocialFundForm::Uang)
                            ->required()
                            ->live(),
                    ]),
                Section::make('Pihak Terkait')
                    ->description(fn (Get $get): string => self::isOutgoing($get)
                        ? 'Data penerima bantuan — nama ini yang tercetak dan bertanda tangan pada nota.'
                        : 'Data penyumbang — nama ini yang tercetak sebagai asal sumbangan pada nota.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('party_name')
                            ->label('Nama')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('party_address')
                            ->label('Alamat')
                            ->maxLength(255),
                    ]),
                Section::make('Rincian')
                    ->columns(2)
                    ->schema([
                        TextInput::make('item_description')
                            ->label('Barang yang Disumbangkan')
                            ->placeholder('mis. 10 dus air mineral')
                            ->maxLength(255)
                            ->required(fn (Get $get): bool => self::isInGoods($get))
                            ->visible(fn (Get $get): bool => self::isInGoods($get))
                            ->columnSpanFull(),
                        TextInput::make('amount')
                            // Untuk barang, nominalnya hanya taksiran nilai sehingga boleh dikosongkan.
                            ->label(fn (Get $get): string => self::isInGoods($get)
                                ? 'Nilai Taksiran (opsional)'
                                : 'Jumlah')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(0)
                            ->required(fn (Get $get): bool => ! self::isInGoods($get)),
                        DatePicker::make('transacted_at')
                            ->label('Tanggal')
                            ->default(now())
                            ->required(),
                        TextInput::make('purpose')
                            ->label('Untuk Keperluan')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function isInGoods(Get $get): bool
    {
        return self::enumValue($get('fund_form')) === SocialFundForm::Barang->value;
    }

    private static function isOutgoing(Get $get): bool
    {
        return self::enumValue($get('direction')) === SocialFundDirection::Keluar->value;
    }

    /**
     * State radio bisa berupa instance enum (nilai bawaan / hasil hidrasi model)
     * maupun string mentah (pilihan pengguna), jadi selalu disamakan dulu ke
     * string sebelum dibandingkan.
     */
    private static function enumValue(mixed $state): ?string
    {
        if ($state instanceof BackedEnum) {
            return (string) $state->value;
        }

        return $state === null ? null : (string) $state;
    }
}
