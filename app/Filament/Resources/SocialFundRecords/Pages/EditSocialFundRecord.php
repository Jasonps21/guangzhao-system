<?php

namespace App\Filament\Resources\SocialFundRecords\Pages;

use App\Filament\Resources\SocialFundRecords\SocialFundRecordResource;
use App\Models\SocialFundRecord;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSocialFundRecord extends EditRecord
{
    protected static string $resource = SocialFundRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Cetak Nota')
                ->icon('heroicon-o-printer')
                ->url(fn (SocialFundRecord $record): string => route('social-fund.receipt', $record))
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }
}
