<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DuesCategory: string implements HasLabel
{
    case Prasejahtera = 'prasejahtera';
    case KurangMampu = 'kurang_mampu';
    case Menengah = 'menengah';
    case Mampu = 'mampu';

    public function getLabel(): string
    {
        return match ($this) {
            self::Prasejahtera => 'Prasejahtera',
            self::KurangMampu => 'Kurang Mampu',
            self::Menengah => 'Menengah',
            self::Mampu => 'Mampu',
        };
    }

    /**
     * Suggested default monthly dues amount for the category.
     */
    public function defaultAmount(): float
    {
        return match ($this) {
            self::Prasejahtera => 0,
            self::KurangMampu => 15000,
            self::Menengah => 30000,
            self::Mampu => 100000,
        };
    }
}
