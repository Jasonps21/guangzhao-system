<?php

namespace App\Filament\Resources\DuesRecords\Pages;

use App\Filament\Resources\DuesRecords\DuesRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDuesRecord extends EditRecord
{
    protected static string $resource = DuesRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
