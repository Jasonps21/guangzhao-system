<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Enums\DuesCategory;
use App\Enums\Gender;
use App\Enums\MemberStatus;
use App\Enums\WhatsappOwner;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Support\ImageCompressor;
use App\Support\PinyinConverter;
use Closure;
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
                            // Turunan kelompok + no. urut, dibentuk model saat disimpan.
                            // Tidak bisa diketik agar keduanya tak pernah bercerai lagi.
                            TextInput::make('member_number')
                                ->label('Nomor Anggota')
                                ->helperText('Terisi otomatis dari Kelompok dan No. Urut.')
                                ->disabled()
                                ->columnSpan(1),
                            Select::make('status')
                                ->label('Status')
                                ->options(MemberStatus::class)
                                ->default(MemberStatus::Aktif)
                                ->required()
                                // Status ikut menentukan kode: anggota yang keluar diarsipkan.
                                ->live()
                                ->afterStateUpdated(fn (Get $get, Set $set) => self::syncMemberNumber($get, $set))
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
                            // Sering bukan nomor anggota sendiri: yang memegang
                            // WhatsApp kerap anak atau menantunya.
                            TextInput::make('whatsapp_phone')
                                ->label('Nomor WhatsApp')
                                ->tel()
                                ->columnSpan(1),
                            Select::make('whatsapp_owner')
                                ->label('Nomor WhatsApp milik')
                                ->options(WhatsappOwner::class)
                                ->columnSpan(1),
                            Textarea::make('address')
                                ->label('Alamat')
                                ->rows(2)
                                ->columnSpanFull(),
                        ]),
                    ]),

                // Kelompok dan no. urut adalah urusan penagihan — disatukan dengan
                // nominal iurannya, bukan diselipkan di antara data kontak.
                Section::make('Iuran & Penagihan')
                    ->schema([
                        Grid::make(2)->schema([
                            // Wajib: anggota tanpa kelompok tidak muncul di daftar
                            // kolektor mana pun, dan nomor anggotanya tak bisa dibentuk.
                            Select::make('group_id')
                                ->label('Kelompok')
                                ->relationship('group', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                    if (filled($state) && blank($get('no_urut'))) {
                                        $set('no_urut', self::nextNoUrut((int) $state));
                                    }

                                    self::syncMemberNumber($get, $set);
                                })
                                ->columnSpan(1),
                            TextInput::make('no_urut')
                                ->label('No. Urut (dalam kelompok)')
                                ->helperText(fn (Get $get, ?Member $record): string => self::seatHistory($get, $record)
                                    ?? 'Urutan anggota saat pembagian kupon dicetak. Menentukan nomor anggota.')
                                ->required()
                                ->integer()
                                ->minValue(1)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set) => self::syncMemberNumber($get, $set))
                                ->rule(fn (Get $get, ?Member $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                    $holder = self::activeSeatHolder($get, $record, $value);

                                    if ($holder !== null) {
                                        $fail("No. urut ini masih dipakai {$holder->displayName()} ({$holder->member_number}), anggota aktif di kelompok yang sama.");
                                    }
                                })
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

    /**
     * No. urut berikutnya di sebuah kelompok, termasuk anggota yang sudah
     * dihapus agar nomor anggotanya tidak bentrok di kolom unik.
     */
    private static function nextNoUrut(int $groupId): int
    {
        return (int) Member::withTrashed()->where('group_id', $groupId)->max('no_urut') + 1;
    }

    /**
     * Tampilkan nomor anggota yang akan tersimpan, mengikuti aturan di model.
     */
    private static function syncMemberNumber(Get $get, Set $set): void
    {
        $group = filled($get('group_id')) ? MemberGroup::query()->find($get('group_id')) : null;
        $noUrut = $get('no_urut');

        if ($group !== null && is_numeric($noUrut) && (int) $noUrut > 0) {
            $number = Member::numberFor($group, (int) $noUrut);

            $set('member_number', self::statusOf($get)->isActive() ? $number : $number.'-K');
        }
    }

    /**
     * Anggota AKTIF lain yang menduduki no. urut ini di kelompok yang sama.
     *
     * Anggota yang sudah keluar tidak dihitung — kursinya boleh diisi orang
     * baru, seperti kebiasaan pengurus. Anggota yang terhapus tetap dihitung
     * karena kodenya masih memegang kolom unik. Bila anggota di formulir ini
     * sendiri tidak aktif, ia tidak menduduki kursi, jadi tak ada yang dicek.
     */
    private static function activeSeatHolder(Get $get, ?Member $record, mixed $noUrut): ?Member
    {
        if (! self::statusOf($get)->isActive() || blank($get('group_id')) || ! is_numeric($noUrut)) {
            return null;
        }

        return Member::withTrashed()
            ->where('group_id', $get('group_id'))
            ->where('no_urut', (int) $noUrut)
            ->where('status', MemberStatus::Aktif)
            ->when($record?->exists, fn ($query) => $query->whereKeyNot($record->getKey()))
            ->first();
    }

    /**
     * "Sebelumnya milik ..." — siapa yang dulu menduduki kursi ini, agar petugas
     * tahu ia sedang mengisi kursi kosong, bukan menimpa orang lain.
     */
    private static function seatHistory(Get $get, ?Member $record): ?string
    {
        $noUrut = $get('no_urut');

        if (blank($get('group_id')) || ! is_numeric($noUrut)) {
            return null;
        }

        $previous = Member::query()
            ->where('group_id', $get('group_id'))
            ->where('no_urut', (int) $noUrut)
            ->where('status', '!=', MemberStatus::Aktif)
            ->when($record?->exists, fn ($query) => $query->whereKeyNot($record->getKey()))
            ->latest('status_changed_at')
            ->first();

        if ($previous === null) {
            return null;
        }

        $when = $previous->status_changed_at?->translatedFormat('F Y');

        return "Nomor ini sebelumnya milik {$previous->displayName()} ({$previous->status->getLabel()}".($when ? " {$when}" : '').').';
    }

    private static function statusOf(Get $get): MemberStatus
    {
        $status = $get('status');

        return $status instanceof MemberStatus
            ? $status
            : (MemberStatus::tryFrom((string) $status) ?? MemberStatus::Aktif);
    }
}
