<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Golongan kegiatan pada log audit. Nilainya dipakai apa adanya sebagai
 * `log_name` di tabel `activity_log`.
 *
 * PerubahanData sengaja bernilai 'default' — itulah nama log bawaan
 * spatie/laravel-activitylog, sehingga ribuan baris yang sudah tercatat
 * sebelum fitur ini ada tetap tergolong benar tanpa perlu diubah.
 */
enum ActivityCategory: string implements HasColor, HasLabel
{
    case PerubahanData = 'default';
    case Autentikasi = 'autentikasi';
    case Cetak = 'cetak';
    case Berkas = 'berkas';

    public function getLabel(): string
    {
        return match ($this) {
            self::PerubahanData => 'Perubahan Data',
            self::Autentikasi => 'Masuk & Keluar',
            self::Cetak => 'Pencetakan',
            self::Berkas => 'Ekspor & Impor',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PerubahanData => 'info',
            self::Autentikasi => 'gray',
            self::Cetak => 'warning',
            self::Berkas => 'success',
        };
    }
}
