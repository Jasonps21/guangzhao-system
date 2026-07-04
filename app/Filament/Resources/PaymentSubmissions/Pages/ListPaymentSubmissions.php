<?php

namespace App\Filament\Resources\PaymentSubmissions\Pages;

use App\Filament\Resources\PaymentSubmissions\PaymentSubmissionResource;
use Filament\Resources\Pages\ListRecords;

class ListPaymentSubmissions extends ListRecords
{
    protected static string $resource = PaymentSubmissionResource::class;
}
