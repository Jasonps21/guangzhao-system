<?php

namespace App\Support;

/**
 * Ejaan angka dalam bahasa Indonesia untuk baris "Terbilang" pada nota cetak,
 * mis. 1.500.000 → "satu juta lima ratus ribu rupiah".
 */
class Terbilang
{
    /** @var array<int, string> */
    private const UNITS = ['', 'ribu', 'juta', 'miliar', 'triliun'];

    /** @var array<int, string> */
    private const DIGITS = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan'];

    /**
     * Ejaan angka bulat, tanpa satuan mata uang.
     */
    public static function make(int|float $number): string
    {
        $number = (int) round(abs($number));

        if ($number === 0) {
            return 'nol';
        }

        $words = [];
        $group = 0;

        while ($number > 0) {
            $chunk = $number % 1000;

            if ($chunk > 0) {
                // "seribu", bukan "satu ribu" — hanya untuk kelipatan ribu pertama.
                $chunkWords = ($chunk === 1 && $group === 1)
                    ? 'seribu'
                    : trim(self::hundreds($chunk).' '.self::UNITS[$group]);

                array_unshift($words, $chunkWords);
            }

            $number = intdiv($number, 1000);
            $group++;
        }

        return trim(implode(' ', $words));
    }

    /**
     * Ejaan nominal rupiah untuk nota, mis. "satu juta rupiah".
     */
    public static function rupiah(int|float $number): string
    {
        return self::make($number).' rupiah';
    }

    /**
     * Ejaan 1–999.
     */
    private static function hundreds(int $number): string
    {
        $words = [];
        $hundred = intdiv($number, 100);
        $rest = $number % 100;

        if ($hundred === 1) {
            $words[] = 'seratus';
        } elseif ($hundred > 1) {
            $words[] = self::DIGITS[$hundred].' ratus';
        }

        $words[] = self::tens($rest);

        return trim(implode(' ', array_filter($words)));
    }

    /**
     * Ejaan 0–99, termasuk bentuk khas "sepuluh", "sebelas", "dua belas".
     */
    private static function tens(int $number): string
    {
        if ($number === 0) {
            return '';
        }

        if ($number < 10) {
            return self::DIGITS[$number];
        }

        if ($number === 10) {
            return 'sepuluh';
        }

        if ($number === 11) {
            return 'sebelas';
        }

        if ($number < 20) {
            return self::DIGITS[$number - 10].' belas';
        }

        $ten = intdiv($number, 10);
        $unit = $number % 10;

        return trim(self::DIGITS[$ten].' puluh '.self::DIGITS[$unit]);
    }
}
