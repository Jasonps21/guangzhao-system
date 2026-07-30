<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Jenis hubungan kekeluargaan antar-anggota. Disimpan netral (tanpa gender)
 * agar tidak pernah timpang; label suami/istri dsb. dihitung dari gender
 * anggota terkait lewat {@see genderedLabel()}.
 */
enum RelationshipType: string implements HasLabel
{
    case Spouse = 'spouse';
    case Child = 'child';
    case Parent = 'parent';
    case Grandchild = 'grandchild';
    case Grandparent = 'grandparent';

    /**
     * Label netral untuk dipilih di form (perspektif pemilik relasi).
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Spouse => 'Pasangan',
            self::Child => 'Anak',
            self::Parent => 'Orang Tua',
            self::Grandchild => 'Cucu',
            self::Grandparent => 'Kakek/Nenek',
        };
    }

    /**
     * Kebalikan relasi — dipakai untuk menyimpan sisi timbal-balik otomatis.
     * (A punya "anak" B berarti B punya "orang tua" A.)
     */
    public function inverse(): self
    {
        return match ($this) {
            self::Spouse => self::Spouse,
            self::Child => self::Parent,
            self::Parent => self::Child,
            self::Grandchild => self::Grandparent,
            self::Grandparent => self::Grandchild,
        };
    }

    /**
     * Label yang memperhitungkan gender anggota terkait. Jika gender belum
     * diisi, jatuh kembali ke label netral.
     */
    public function genderedLabel(?Gender $gender): string
    {
        return match ($this) {
            self::Spouse => match ($gender) {
                Gender::Male => 'Suami',
                Gender::Female => 'Istri',
                null => 'Pasangan',
            },
            self::Child => match ($gender) {
                Gender::Male => 'Anak (Laki-laki)',
                Gender::Female => 'Anak (Perempuan)',
                null => 'Anak',
            },
            self::Parent => match ($gender) {
                Gender::Male => 'Ayah',
                Gender::Female => 'Ibu',
                null => 'Orang Tua',
            },
            self::Grandchild => match ($gender) {
                Gender::Male => 'Cucu (Laki-laki)',
                Gender::Female => 'Cucu (Perempuan)',
                null => 'Cucu',
            },
            self::Grandparent => match ($gender) {
                Gender::Male => 'Kakek',
                Gender::Female => 'Nenek',
                null => 'Kakek/Nenek',
            },
        };
    }
}
