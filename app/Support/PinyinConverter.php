<?php

namespace App\Support;

use Overtrue\Pinyin\Pinyin;
use Overtrue\Pinyin\ToneStyle;

class PinyinConverter
{
    /**
     * Convert a Chinese name (Hanzi) to a romanised Pinyin name using surname mode,
     * e.g. "张三" => "Zhang San". Returned as a suggestion that can be edited. (§6.6)
     */
    public function fromName(string $hanzi): string
    {
        $hanzi = trim($hanzi);

        if ($hanzi === '') {
            return '';
        }

        return Pinyin::name($hanzi, ToneStyle::NONE)
            ->map(fn (string $syllable): string => ucfirst($syllable))
            ->join(' ');
    }
}
