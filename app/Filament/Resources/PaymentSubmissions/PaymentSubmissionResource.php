<?php

namespace App\Filament\Resources\PaymentSubmissions;

use App\Enums\SubmissionStatus;
use App\Filament\Resources\PaymentSubmissions\Pages\ListPaymentSubmissions;
use App\Filament\Resources\PaymentSubmissions\Tables\PaymentSubmissionsTable;
use App\Models\PaymentSubmission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PaymentSubmissionResource extends Resource
{
    protected static ?string $model = PaymentSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Persetujuan Setoran';

    protected static string|UnitEnum|null $navigationGroup = 'Iuran';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Setoran Kolektor';

    protected static ?string $pluralModelLabel = 'Setoran Kolektor';

    public static function table(Table $table): Table
    {
        return PaymentSubmissionsTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = PaymentSubmission::query()->pending()->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return SubmissionStatus::Pending->getColor();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentSubmissions::route('/'),
        ];
    }
}
