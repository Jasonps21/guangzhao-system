<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum GroupBasis: string implements HasLabel
{
    case Wilayah = 'wilayah';
    case Status = 'status';

    public function getLabel(): string
    {
        return match ($this) {
            self::Wilayah => 'Wilayah',
            self::Status => 'Status',
        };
    }
}
