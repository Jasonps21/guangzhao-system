<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DuesStatus: string implements HasColor, HasLabel
{
    case BelumBayar = 'belum_bayar';
    case MenungguPersetujuan = 'menunggu_persetujuan';
    case Lunas = 'lunas';

    public function getLabel(): string
    {
        return match ($this) {
            self::BelumBayar => 'Belum Bayar',
            self::MenungguPersetujuan => 'Menunggu Persetujuan',
            self::Lunas => 'Lunas',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::BelumBayar => 'danger',
            self::MenungguPersetujuan => 'warning',
            self::Lunas => 'success',
        };
    }
}
