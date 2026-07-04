<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Kolektor = 'kolektor';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin / Bendahara',
            self::Kolektor => 'Kolektor',
        };
    }
}
