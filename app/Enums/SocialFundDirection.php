<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Arah aliran dana sosial: sumbangan yang MASUK dari donatur, atau bantuan
 * sosial yang KELUAR diberikan perkumpulan. Menentukan bunyi nota cetaknya.
 */
enum SocialFundDirection: string implements HasColor, HasLabel
{
    case Masuk = 'masuk';
    case Keluar = 'keluar';

    public function getLabel(): string
    {
        return match ($this) {
            self::Masuk => 'Masuk (Sumbangan Diterima)',
            self::Keluar => 'Keluar (Bantuan Diberikan)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Masuk => 'success',
            self::Keluar => 'warning',
        };
    }
}
