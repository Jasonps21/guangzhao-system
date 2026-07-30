<?php

namespace Tests\Unit;

use App\Support\ChineseNumeral;
use App\Support\Terbilang;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TerbilangTest extends TestCase
{
    #[DataProvider('indonesianNumbers')]
    public function test_it_spells_numbers_in_indonesian(int $number, string $expected): void
    {
        $this->assertSame($expected, Terbilang::make($number));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function indonesianNumbers(): array
    {
        return [
            'nol' => [0, 'nol'],
            'satuan' => [7, 'tujuh'],
            'sepuluh' => [10, 'sepuluh'],
            'sebelas' => [11, 'sebelas'],
            'belasan' => [15, 'lima belas'],
            'puluhan' => [21, 'dua puluh satu'],
            'seratus' => [100, 'seratus'],
            'ratusan' => [250, 'dua ratus lima puluh'],
            'seribu' => [1000, 'seribu'],
            'ribuan' => [15000, 'lima belas ribu'],
            'ratusan ribu' => [750000, 'tujuh ratus lima puluh ribu'],
            'sejuta' => [1000000, 'satu juta'],
            'juta dengan sisa' => [1500000, 'satu juta lima ratus ribu'],
            'melompati kelompok' => [1000500, 'satu juta lima ratus'],
            'miliar' => [2000000000, 'dua miliar'],
        ];
    }

    public function test_it_appends_the_currency_word(): void
    {
        $this->assertSame('satu juta rupiah', Terbilang::rupiah(1000000));
    }

    public function test_it_rounds_and_ignores_the_sign(): void
    {
        $this->assertSame('seribu', Terbilang::make(1000.4));
        $this->assertSame('seribu', Terbilang::make(-1000));
    }

    #[DataProvider('chineseNumbers')]
    public function test_it_spells_numbers_in_chinese_capitals(int $number, string $expected): void
    {
        $this->assertSame($expected, ChineseNumeral::make($number));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function chineseNumbers(): array
    {
        return [
            'nol' => [0, '零'],
            'satuan' => [5, '伍'],
            'puluhan' => [30, '叁拾'],
            'ratusan' => [500, '伍佰'],
            'ribuan' => [15000, '壹万伍仟'],
            'sejuta' => [1000000, '壹佰万'],
            'dua setengah juta' => [2500000, '贰佰伍拾万'],
            'sisipan nol' => [1000008, '壹佰万零捌'],
        ];
    }

    public function test_it_appends_the_chinese_currency_suffix(): void
    {
        // Bunyi nominal pada nota uang duka yang selama ini dicetak.
        $this->assertSame('壹佰万盾正', ChineseNumeral::rupiah(1000000));
    }
}
