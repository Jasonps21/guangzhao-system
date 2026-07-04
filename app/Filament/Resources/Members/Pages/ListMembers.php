<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use App\Models\MemberGroup;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ListRecords;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // §6.x — cetak daftar anggota per kelompok (PDF A4).
            Action::make('printList')
                ->label('Cetak Daftar')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->schema([
                    Select::make('group_id')
                        ->label('Kelompok (kosong = semua)')
                        ->options(fn () => MemberGroup::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable(),
                    Toggle::make('active_only')
                        ->label('Hanya anggota aktif')
                        ->default(true),
                ])
                ->action(function (array $data) {
                    $params = ['active_only' => ($data['active_only'] ?? true) ? 1 : 0];
                    if (filled($data['group_id'] ?? null)) {
                        $params['group'] = $data['group_id'];
                    }

                    return redirect()->route('members.list', $params);
                }),

            CreateAction::make(),
        ];
    }
}
