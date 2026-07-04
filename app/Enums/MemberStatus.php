<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MemberStatus: string implements HasColor, HasLabel
{
    case Aktif = 'aktif';
    case MengundurkanDiri = 'mengundurkan_diri';
    case Pindah = 'pindah';
    case Meninggal = 'meninggal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::MengundurkanDiri => 'Mengundurkan Diri',
            self::Pindah => 'Pindah',
            self::Meninggal => 'Meninggal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Aktif => 'success',
            self::MengundurkanDiri => 'warning',
            self::Pindah => 'gray',
            self::Meninggal => 'danger',
        };
    }

    /**
     * Statuses that are still billed / printed.
     */
    public function isActive(): bool
    {
        return $this === self::Aktif;
    }
}
