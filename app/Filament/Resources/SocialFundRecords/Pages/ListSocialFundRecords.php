<?php

namespace App\Filament\Resources\SocialFundRecords\Pages;

use App\Filament\Resources\SocialFundRecords\SocialFundRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSocialFundRecords extends ListRecords
{
    protected static string $resource = SocialFundRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
