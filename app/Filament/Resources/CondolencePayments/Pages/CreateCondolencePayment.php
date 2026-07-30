<?php

namespace App\Filament\Resources\CondolencePayments\Pages;

use App\Filament\Resources\CondolencePayments\CondolencePaymentResource;
use App\Models\CondolencePayment;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCondolencePayment extends CreateRecord
{
    protected static string $resource = CondolencePaymentResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['recorded_by'] = auth()->id();

        return $data;
    }

    /**
     * Setelah tersimpan, kedua nota langsung bisa dicetak dari notifikasi —
     * petugas biasanya butuh keduanya seketika saat menyerahkan uang duka.
     */
    protected function getCreatedNotification(): ?Notification
    {
        /** @var CondolencePayment $record */
        $record = $this->getRecord();

        return Notification::make()
            ->success()
            ->title('Uang duka tercatat — nota '.$record->receipt_number)
            ->actions([
                Action::make('printReceipt')
                    ->label('Tanda Terima')
                    ->url(route('condolence.receipt', $record), shouldOpenInNewTab: true),
                Action::make('printHandover')
                    ->label('Tanda Pemberian')
                    ->url(route('condolence.handover', $record), shouldOpenInNewTab: true),
            ]);
    }
}
