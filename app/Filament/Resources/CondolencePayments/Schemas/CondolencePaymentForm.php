<?php

namespace App\Filament\Resources\CondolencePayments\Schemas;

use App\Models\Member;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class CondolencePaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Penerima')
                    ->description('Pilih anggota agar nama & nomor anggota terisi otomatis, atau ketik manual untuk penerima non-anggota.')
                    ->columns(2)
                    ->schema([
                        Select::make('member_id')
                            ->label('Anggota')
                            ->searchable()
                            ->live()
                            ->getSearchResultsUsing(fn (string $search): array => Member::query()
                                ->where(fn ($query) => $query
                                    ->where('name_indonesian', 'like', "%{$search}%")
                                    ->orWhere('name_pinyin', 'like', "%{$search}%")
                                    ->orWhere('name_hanzi', 'like', "%{$search}%")
                                    ->orWhere('member_number', 'like', "%{$search}%"))
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (Member $member): array => [
                                    $member->id => $member->member_number.' — '.$member->displayName(),
                                ])
                                ->all())
                            ->getOptionLabelUsing(fn ($value): ?string => Member::find($value)?->displayName())
                            // Data anggota disalin ke nota supaya nota lama tidak
                            // ikut berubah kalau data anggotanya nanti diperbarui.
                            ->afterStateUpdated(function (Set $set, $state): void {
                                $member = $state === null ? null : Member::find($state);

                                if ($member === null) {
                                    return;
                                }

                                $set('recipient_name', $member->displayName());
                                $set('recipient_member_number', $member->member_number);
                                $set('deceased_name_hanzi', $member->name_hanzi);
                            })
                            ->columnSpanFull(),
                        TextInput::make('recipient_name')
                            ->label('Nama Penerima (TN/NY)')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('recipient_member_number')
                            ->label('No. Anggota')
                            ->maxLength(255),
                        TextInput::make('deceased_name_hanzi')
                            ->label('Nama Hanzi (untuk baris mandarin di nota)')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
                Section::make('Uang Duka')
                    ->columns(2)
                    ->schema([
                        TextInput::make('amount')
                            ->label('Jumlah')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(0)
                            ->required(),
                        DatePicker::make('given_at')
                            ->label('Tanggal Pemberian')
                            ->default(now())
                            ->required(),
                        TextInput::make('escort_name')
                            ->label('Pengantar')
                            ->helperText('Nama petugas/pengurus yang mengantar uang duka.')
                            ->maxLength(255),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
