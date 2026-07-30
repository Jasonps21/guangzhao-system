<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Bentuk sumbangan/bantuan: uang tunai atau barang. Untuk barang, nominalnya
 * bersifat taksiran dan yang dicetak di nota adalah deskripsi barangnya.
 */
enum SocialFundForm: string implements HasLabel
{
    case Uang = 'uang';
    case Barang = 'barang';

    public function getLabel(): string
    {
        return match ($this) {
            self::Uang => 'Uang',
            self::Barang => 'Barang',
        };
    }
}
