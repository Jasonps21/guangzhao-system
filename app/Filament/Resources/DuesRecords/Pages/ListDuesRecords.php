<?php

namespace App\Filament\Resources\DuesRecords\Pages;

use App\Filament\Resources\DuesRecords\DuesRecordResource;
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
                    Select::make('group_id')
                        ->label('Kelompok (kosong = semua)')
                        ->options(fn () => MemberGroup::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable(),
                ])
                ->action(function (array $data) {
                    $params = ['year' => $data['year'], 'month' => $data['month']];
                    if (filled($data['group_id'] ?? null)) {
                        $params['group'] = $data['group_id'];
                    }

                    return redirect()->route('dues.coupons', $params);
                }),

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
}
