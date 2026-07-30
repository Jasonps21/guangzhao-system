<?php

namespace App\Filament\Resources\CondolencePayments\Pages;

use App\Filament\Resources\CondolencePayments\CondolencePaymentResource;
use App\Models\CondolencePayment;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCondolencePayment extends EditRecord
{
    protected static string $resource = CondolencePaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('printReceipt')
                ->label('Cetak Tanda Terima')
                ->icon('heroicon-o-printer')
                ->url(fn (CondolencePayment $record): string => route('condolence.receipt', $record))
                ->openUrlInNewTab(),
            Action::make('printHandover')
                ->label('Cetak Tanda Pemberian')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (CondolencePayment $record): string => route('condolence.handover', $record))
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }
}
