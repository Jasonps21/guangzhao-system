<?php

namespace App\Support;

/**
 * Angka kapital (大写) Mandarin untuk baris nominal pada nota uang duka,
 * mis. 1.000.000 → "壹佰万盾正" (盾 = rupiah, 正 = penutup nominal pas).
 */
class ChineseNumeral
{
    /** @var array<int, string> */
    private const DIGITS = ['零', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖'];

    /** @var array<int, string> */
    private const UNITS = ['', '拾', '佰', '仟'];

    /** @var array<int, string> */
    private const SECTIONS = ['', '万', '亿', '万亿'];

    /**
     * Nominal rupiah bergaya nota Tionghoa, mis. "壹佰万盾正".
     */
    public static function rupiah(int|float $number): string
    {
        return self::make($number).'盾正';
    }

    /**
     * Angka kapital tanpa satuan.
     */
    public static function make(int|float $number): string
    {
        $number = (int) round(abs($number));

        if ($number === 0) {
            return self::DIGITS[0];
        }

        $result = '';
        $sectionIndex = 0;
        // Menandai perlunya sisipan 零 saat satu bagian melompati satuan penuh,
        // mis. 1.000.008 → 壹佰万零捌.
        $needsZero = false;

        while ($number > 0 && $sectionIndex < count(self::SECTIONS)) {
            $section = $number % 10000;

            if ($needsZero) {
                $result = self::DIGITS[0].$result;
            }

            if ($section > 0) {
                $result = self::section($section).self::SECTIONS[$sectionIndex].$result;
            }

            $needsZero = $section > 0 && $section < 1000;
            $number = intdiv($number, 10000);
            $sectionIndex++;
        }

        return $result;
    }

    /**
     * Satu bagian empat digit (0–9999).
     */
    private static function section(int $number): string
    {
        $text = '';
        $position = 0;
        $trailingZero = true;

        while ($number > 0) {
            $digit = $number % 10;

            if ($digit === 0) {
                if (! $trailingZero) {
                    $trailingZero = true;
                    $text = self::DIGITS[0].$text;
                }
            } else {
                $trailingZero = false;
                $text = self::DIGITS[$digit].self::UNITS[$position].$text;
            }

            $position++;
            $number = intdiv($number, 10);
        }

        return $text;
    }
}
