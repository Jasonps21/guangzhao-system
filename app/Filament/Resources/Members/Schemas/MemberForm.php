<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Enums\DuesCategory;
use App\Enums\Gender;
use App\Enums\MemberStatus;
use App\Support\ImageCompressor;
use App\Support\PinyinConverter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Identitas & lokasi memakai lebar penuh karena isinya tiga kolom;
                // Kontak dan Iuran cukup pendek sehingga bisa berdampingan.
                Section::make('Identitas Anggota')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
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
                            DatePicker::make('joined_at')
                                ->label('Tanggal Bergabung')
                                // Pemilih bawaan Filament, bukan input tanggal browser
                                // yang memaksa format bulan/hari/tahun gaya Amerika.
                                ->native(false)
                                ->displayFormat('d M Y')
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
                        Grid::make(3)->schema([
                            // Dua pilihan saling lepas: radio sejajar, sekali klik —
                            // dropdown akan memaksa buka-pilih-tutup tanpa alasan.
                            Radio::make('gender')
                                ->label('Jenis Kelamin')
                                ->options(Gender::class)
                                ->inline()
                                ->inlineLabel(false)
                                ->helperText('Dipakai untuk label kekeluargaan (suami/istri, ayah/ibu, dst.).')
                                ->columnSpan(2),
                            FileUpload::make('photo_path')
                                ->label('Foto')
                                ->image()
                                ->avatar()
                                // Menampilkan siluet orang selama foto belum ada
                                // (lihat resources/views/filament/partials/admin-styles).
                                ->extraAttributes(['class' => 'gz-avatar-upload'])
                                ->disk('local')
                                ->directory('member-photos')
                                ->visibility('private')
                                ->maxSize(15360)
                                ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(ImageCompressor::class)->store(
                                    $file,
                                    disk: 'local',
                                    directory: 'member-photos',
                                    maxDimension: 800,
                                    visibility: 'private',
                                    format: 'jpeg',
                                ))
                                ->columnSpan(1),
                        ]),
                    ]),

                Section::make('Kontak')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('phone')
                                ->label('Telepon')
                                ->tel()
                                ->columnSpan(1),
                            Textarea::make('address')
                                ->label('Alamat')
                                ->rows(2)
                                ->columnSpan(2),
                        ]),
                    ]),

                // Kelompok dan no. urut adalah urusan penagihan — disatukan dengan
                // nominal iurannya, bukan diselipkan di antara data kontak.
                Section::make('Iuran & Penagihan')
                    ->schema([
                        Grid::make(2)->schema([
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
                            Select::make('dues_category')
                                ->label('Kategori Iuran')
                                ->options(DuesCategory::class)
                                ->live()
                                // Filament meng-cast state select enum menjadi objek enum,
                                // jadi hook ini menerima DuesCategory — bukan string.
                                ->afterStateUpdated(function (?DuesCategory $state, Set $set): void {
                                    if ($state !== null) {
                                        $set('monthly_dues_amount', $state->defaultAmount());
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

                Section::make('Lokasi & Rumah')
                    ->description('Titik GPS & foto rumah agar penagih penerus tetap bisa menemukan rumah anggota.')
                    ->columnSpanFull()
                    ->schema([
                        // Titik dipilih di peta; kolom angka di bawahnya hanya
                        // untuk koreksi manual atau saat internet mati.
                        ViewField::make('location_picker')
                            ->hiddenLabel()
                            ->dehydrated(false)
                            ->view('filament.forms.components.location-picker')
                            ->columnSpanFull(),
                        Grid::make(2)->schema([
                            TextInput::make('latitude')
                                ->label('Latitude')
                                ->helperText('Terisi otomatis dari peta di atas.')
                                ->numeric()
                                ->step('any')
                                ->minValue(-90)
                                ->maxValue(90)
                                ->columnSpan(1),
                            TextInput::make('longitude')
                                ->label('Longitude')
                                ->helperText('Terisi otomatis dari peta di atas.')
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
                            ->maxSize(15360)
                            ->helperText('Foto dikompres otomatis (WebP, maks 1600px) agar tetap tajam namun hemat penyimpanan.')
                            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(ImageCompressor::class)->store(
                                $file,
                                disk: 'public',
                                directory: 'member-houses',
                                maxDimension: 1600,
                                visibility: 'public',
                            ))
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),

                Section::make('Catatan')
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('notes')
                            ->hiddenLabel()
                            ->placeholder('Catatan bebas tentang anggota ini.')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }
}
