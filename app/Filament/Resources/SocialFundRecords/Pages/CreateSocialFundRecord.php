<?php

namespace App\Filament\Resources\SocialFundRecords\Pages;

use App\Filament\Resources\SocialFundRecords\SocialFundRecordResource;
use App\Models\SocialFundRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateSocialFundRecord extends CreateRecord
{
    protected static string $resource = SocialFundRecordResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['recorded_by'] = auth()->id();

        return $data;
    }

    protected function getCreatedNotification(): ?Notification
    {
        /** @var SocialFundRecord $record */
        $record = $this->getRecord();

        return Notification::make()
            ->success()
            ->title('Tercatat — nota '.$record->receipt_number)
            ->actions([
                Action::make('print')
                    ->label('Cetak Nota')
                    ->url(route('social-fund.receipt', $record), shouldOpenInNewTab: true),
            ]);
    }
}
