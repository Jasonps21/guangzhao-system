<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CollectionMethod: string implements HasLabel
{
    case Lapangan = 'lapangan';
    case Admin = 'admin';
    case Migrasi = 'migrasi';

    public function getLabel(): string
    {
        return match ($this) {
            self::Lapangan => 'Lapangan (Kolektor)',
            self::Admin => 'Admin',
            self::Migrasi => 'Saldo Awal (Excel)',
        };
    }
}
