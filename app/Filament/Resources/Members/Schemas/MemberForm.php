<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Enums\DuesCategory;
use App\Enums\MemberStatus;
use App\Support\PinyinConverter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Anggota')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('member_number')
                                ->label('Nomor Anggota')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->columnSpan(1),
                            Select::make('status')
                                ->label('Status')
                                ->options(MemberStatus::class)
                                ->default(MemberStatus::Aktif)
                                ->required()
                                ->columnSpan(1),
                        ]),
                        Grid::make(3)->schema([
                            TextInput::make('name_hanzi')
                                ->label('Nama Mandarin (汉字)')
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                    // §6.6 — saran pinyin otomatis, hanya bila pinyin masih kosong.
                                    if (filled($state) && blank($get('name_pinyin'))) {
                                        $set('name_pinyin', app(PinyinConverter::class)->fromName($state));
                                    }
                                })
                                ->columnSpan(1),
                            TextInput::make('name_pinyin')
                                ->label('Nama Pinyin')
                                ->helperText('Terisi otomatis dari nama Mandarin — boleh dikoreksi.')
                                ->columnSpan(1),
                            TextInput::make('name_indonesian')
                                ->label('Nama Indonesia')
                                ->columnSpan(1),
                        ]),
                    ]),

                Section::make('Kontak & Kelompok')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('phone')
                                ->label('Telepon')
                                ->tel()
                                ->columnSpan(1),
                            Select::make('group_id')
                                ->label('Kelompok')
                                ->relationship('group', 'name')
                                ->searchable()
                                ->preload()
                                ->columnSpan(1),
                            TextInput::make('no_urut')
                                ->label('No. Urut (dalam kelompok)')
                                ->helperText('Urutan anggota saat pembagian kupon dicetak.')
                                ->numeric()
                                ->minValue(1)
                                ->columnSpan(1),
                            Toggle::make('bill_at_home')
                                ->label('Ditagih di rumah')
                                ->helperText('Cetak "(tagih dirumah)" pada kupon iuran.')
                                ->columnSpan(1),
                            Textarea::make('address')
                                ->label('Alamat')
                                ->rows(2)
                                ->columnSpanFull(),
                        ]),
                    ]),

                Section::make('Lokasi & Rumah')
                    ->description('Titik GPS & foto rumah agar penagih penerus tetap bisa menemukan rumah anggota.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('latitude')
                                ->label('Latitude')
                                ->numeric()
                                ->step('any')
                                ->minValue(-90)
                                ->maxValue(90)
                                ->columnSpan(1),
                            TextInput::make('longitude')
                                ->label('Longitude')
                                ->numeric()
                                ->step('any')
                                ->minValue(-180)
                                ->maxValue(180)
                                ->columnSpan(1),
                        ]),
                        Placeholder::make('maps_link')
                            ->label('Peta')
                            ->content(function (Get $get): HtmlString {
                                $lat = $get('latitude');
                                $lng = $get('longitude');

                                if (blank($lat) || blank($lng)) {
                                    return new HtmlString('<span style="color:#a8a29e">Belum ada titik lokasi.</span>');
                                }

                                $url = 'https://www.google.com/maps/search/?api=1&query='.$lat.','.$lng;

                                return new HtmlString('<a href="'.e($url).'" target="_blank" rel="noopener" style="color:#2563eb;font-weight:600">Buka di Google Maps ↗</a>');
                            }),
                        FileUpload::make('house_photo_path')
                            ->label('Foto Rumah')
                            ->image()
                            ->disk('public')
                            ->directory('member-houses')
                            ->visibility('public')
                            ->imageEditor()
                            ->maxSize(8192)
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),

                Section::make('Iuran')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('dues_category')
                                ->label('Kategori Iuran')
                                ->options(DuesCategory::class)
                                ->live()
                                ->afterStateUpdated(function (?string $state, Set $set): void {
                                    if (filled($state)) {
                                        $set('monthly_dues_amount', DuesCategory::from($state)->defaultAmount());
                                    }
                                })
                                ->columnSpan(1),
                            TextInput::make('monthly_dues_amount')
                                ->label('Iuran per Bulan (Rp)')
                                ->numeric()
                                ->prefix('Rp')
                                ->default(0)
                                ->required()
                                ->columnSpan(1),
                        ]),
                    ]),

                Section::make('Lainnya')
                    ->schema([
                        Grid::make(2)->schema([
                            DatePicker::make('joined_at')
                                ->label('Tanggal Bergabung')
                                ->columnSpan(1),
                            FileUpload::make('photo_path')
                                ->label('Foto')
                                ->image()
                                ->avatar()
                                ->directory('member-photos')
                                ->visibility('private')
                                ->columnSpan(1),
                            Textarea::make('notes')
                                ->label('Catatan')
                                ->rows(2)
                                ->columnSpanFull(),
                        ]),
                    ])
                    ->collapsed(),
            ]);
    }
}
