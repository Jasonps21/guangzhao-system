<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VisitOutcome: string implements HasColor, HasLabel
{
    case Bertemu = 'bertemu';
    case TidakDiRumah = 'tidak_di_rumah';
    case Pindah = 'pindah';
    case RumahKosong = 'rumah_kosong';

    public function getLabel(): string
    {
        return match ($this) {
            self::Bertemu => 'Bertemu',
            self::TidakDiRumah => 'Tidak di rumah',
            self::Pindah => 'Sudah pindah',
            self::RumahKosong => 'Rumah kosong',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Bertemu => 'success',
            self::TidakDiRumah => 'warning',
            self::Pindah => 'danger',
            self::RumahKosong => 'gray',
        };
    }
}
