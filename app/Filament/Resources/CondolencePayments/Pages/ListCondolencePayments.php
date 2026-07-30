<?php

namespace App\Filament\Resources\CondolencePayments\Pages;

use App\Filament\Resources\CondolencePayments\CondolencePaymentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCondolencePayments extends ListRecords
{
    protected static string $resource = CondolencePaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
