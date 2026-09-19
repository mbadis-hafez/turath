<?php

namespace App\Support;

use Normalizer;

class ArabicNormalizer
{
    private const LETTER_MAP = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    public static function normalize(string $value): string
    {
        // 1. Unicode NFKC, lowercase Latin.
        $value = mb_strtolower((string) Normalizer::normalize($value, Normalizer::FORM_KC), 'UTF-8');

        // 2. Strip Arabic diacritics (U+064B–U+065F, U+0670) and tatweel (U+0640).
        $value = (string) preg_replace('/[\x{0640}\x{064B}-\x{065F}\x{0670}]/u', '', $value);

        // 3 + 4. Fold Arabic letters; Arabic-Indic / Persian digits to ASCII.
        $value = strtr($value, self::LETTER_MAP);

        // 5. Latin: compatibility decomposition, then strip combining marks.
        $value = (string) Normalizer::normalize($value, Normalizer::FORM_KD);
        $value = (string) preg_replace('/\p{Mn}/u', '', $value);

        // 6. Punctuation to space, collapse whitespace, trim.
        $value = (string) preg_replace('/\p{P}/u', ' ', $value);
        $value = (string) preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }

    public static function compact(string $value): string
    {
        return (string) preg_replace('/\s+/u', '', self::normalize($value));
    }
}
