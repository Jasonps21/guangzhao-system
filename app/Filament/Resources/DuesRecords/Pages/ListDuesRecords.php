<?php

namespace App\Filament\Resources\DuesRecords\Pages;

use App\Filament\Resources\DuesRecords\DuesRecordResource;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Support\DuesGenerator;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;

class ListDuesRecords extends ListRecords
{
    protected static string $resource = DuesRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // §6.1 — generate tagihan bulanan secara manual.
            Action::make('generate')
                ->label('Generate Tagihan')
                ->icon('heroicon-o-sparkles')
                ->schema([
                    TextInput::make('year')
                        ->label('Tahun')
                        ->numeric()
                        ->default((int) now()->year)
                        ->required(),
                    Select::make('month')
                        ->label('Bulan')
                        ->options(self::monthOptions())
                        ->default((int) now()->month)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $created = app(DuesGenerator::class)->generate((int) $data['year'], (int) $data['month']);

                    Notification::make()
                        ->title("{$created} tagihan baru dibuat.")
                        ->success()
                        ->send();
                }),

            // §F — cetak kupon iuran 10-up F4.
            Action::make('printCoupons')
                ->label('Cetak Kupon')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->modalDescription('Kupon disiapkan satu kelompok sekali cetak. Setelah tombol ditekan, tunggu sampai PDF-nya terbuka — cukup sekali, jangan diulang.')
                ->modalSubmitActionLabel('Siapkan Kupon')
                ->schema([
                    TextInput::make('year')
                        ->label('Tahun')
                        ->numeric()
                        ->default((int) now()->year)
                        ->required(),
                    Select::make('month')
                        ->label('Bulan')
                        ->options(self::monthOptions())
                        ->default((int) now()->month)
                        ->required(),
                    // Wajib satu kelompok: mencetak seluruh anggota sekaligus memakan
                    // puluhan detik, dan kupon memang dibagikan per kelompok.
                    Select::make('group_id')
                        ->label('Kelompok')
                        ->helperText('Pilih satu kelompok. Mencetak seluruh anggota sekaligus membuat prosesnya sangat lama.')
                        ->options(fn () => MemberGroup::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required(),
                ])
                ->action(fn (array $data) => redirect()->route('dues.coupons', [
                    'year' => $data['year'],
                    'month' => $data['month'],
                    'group' => $data['group_id'],
                ])),

            // Kupon robek / kena air, dan anggota yang tagihannya belum
            // digenerate. Sengaja tombol terpisah dari cetak per kelompok:
            // pilihannya per nama dan bebas lintas kelompok.
            Action::make('printSelectedCoupons')
                ->label('Cetak Kupon Anggota Tertentu')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->modalDescription('Untuk kupon yang robek atau rusak, dan anggota yang tagihannya belum digenerate. Boleh memilih lintas kelompok — semuanya dicetak dalam satu tumpukan.')
                ->modalSubmitActionLabel('Siapkan Kupon')
                ->schema([
                    TextInput::make('year')
                        ->label('Tahun')
                        ->numeric()
                        ->default((int) now()->year)
                        ->required(),
                    Select::make('month')
                        ->label('Bulan')
                        ->options(self::monthOptions())
                        ->default((int) now()->month)
                        ->required(),
                    Select::make('member_ids')
                        ->label('Anggota')
                        ->helperText('Cari nama atau nomor anggota. Boleh beberapa sekaligus, dan tidak harus satu kelompok.')
                        ->multiple()
                        ->searchable()
                        ->required()
                        // Sengaja tanpa ->preload(): 1.240 anggota terlalu berat
                        // untuk dimuat sekaligus di dropdown.
                        ->getSearchResultsUsing(fn (string $search): array => self::searchMembers($search))
                        ->getOptionLabelsUsing(fn (array $values): array => self::memberLabels($values)),
                ])
                ->action(fn (array $data) => redirect()->route('dues.coupons.selected', [
                    'year' => $data['year'],
                    'month' => $data['month'],
                    'members' => $data['member_ids'],
                ])),

            CreateAction::make(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected static function monthOptions(): array
    {
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = Carbon::create(null, $m, 1)->translatedFormat('F');
        }

        return $months;
    }

    /**
     * Hasil pencarian anggota aktif, dibatasi agar dropdown tetap ringan.
     *
     * @return array<int, string>
     */
    protected static function searchMembers(string $search): array
    {
        return Member::query()
            ->with('group')
            ->active()
            ->searchByNameOrNumber($search)
            ->orderBy('name_indonesian')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Member $member): array => [$member->getKey() => self::memberLabel($member)])
            ->all();
    }

    /**
     * Label anggota yang sudah terpilih, supaya tetap terbaca saat modal dibuka
     * ulang — hasil pencarian tidak lagi tersedia saat itu.
     *
     * @param  array<int, int|string>  $values
     * @return array<int, string>
     */
    protected static function memberLabels(array $values): array
    {
        return Member::query()
            ->with('group')
            ->whereKey($values)
            ->get()
            ->mapWithKeys(fn (Member $member): array => [$member->getKey() => self::memberLabel($member)])
            ->all();
    }

    /**
     * Nama, nomor anggota, dan kelompoknya — supaya nama yang mirip tetap bisa
     * dibedakan saat memilih.
     */
    protected static function memberLabel(Member $member): string
    {
        $name = $member->name_indonesian ?: ($member->name_pinyin ?: $member->name_hanzi);

        return sprintf(
            '%s — %s · %s',
            $name ?: '(tanpa nama)',
            $member->member_number,
            $member->group?->name ?? 'Tanpa Kelompok',
        );
    }
}
