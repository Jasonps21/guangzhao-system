<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Dokumen yang bisa dicetak dari sistem.
 *
 * Nilainya ikut tersimpan pada properti log, sehingga pertanyaan "cetak apa?"
 * bisa dijawab dengan menyaring, bukan dengan membaca kalimatnya satu per satu.
 */
enum PrintedDocument: string implements HasLabel
{
    case KuponIuran = 'kupon_iuran';
    case KuponTerpilih = 'kupon_terpilih';
    case DaftarAnggota = 'daftar_anggota';
    case KartuAnggota = 'kartu_anggota';
    case TandaTerimaUangDuka = 'tanda_terima_uang_duka';
    case TandaPemberianUangDuka = 'tanda_pemberian_uang_duka';
    case NotaDanaSosial = 'nota_dana_sosial';
    case NotaPembayaranIuran = 'nota_pembayaran_iuran';

    public function getLabel(): string
    {
        return match ($this) {
            self::KuponIuran => 'Cetak kupon iuran',
            self::KuponTerpilih => 'Cetak kupon anggota terpilih',
            self::DaftarAnggota => 'Cetak daftar anggota',
            self::KartuAnggota => 'Cetak kartu anggota',
            self::TandaTerimaUangDuka => 'Cetak tanda terima uang duka',
            self::TandaPemberianUangDuka => 'Cetak tanda pemberian uang duka',
            self::NotaDanaSosial => 'Cetak nota dana sosial',
            self::NotaPembayaranIuran => 'Cetak nota pembayaran iuran',
        };
    }
}
