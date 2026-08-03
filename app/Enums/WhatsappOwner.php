<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Pemilik nomor WhatsApp yang tercatat untuk seorang anggota. Banyak anggota
 * lansia tidak memakai WhatsApp sendiri, dan yang bisa dihubungi adalah anak
 * atau menantunya — admin yang mengirim pesan perlu tahu sedang bicara dengan
 * siapa sebelum menulis.
 */
enum WhatsappOwner: string implements HasLabel
{
    case Sendiri = 'sendiri';
    case AnakMenantu = 'anak_menantu';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sendiri => 'Anggota sendiri',
            self::AnakMenantu => 'Anak / Menantu',
        };
    }
}
