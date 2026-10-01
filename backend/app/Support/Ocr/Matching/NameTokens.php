<?php

namespace App\Support\Ocr\Matching;

use App\Support\ArabicNormalizer;

/**
 * The words of a name or title worth comparing, and how many two of them
 * share. Plain string comparison after ArabicNormalizer — no model, nothing
 * calibrated: a similarity here says the texts look alike, never that they
 * name the same thing.
 */
final class NameTokens
{
    /**
     * Distinct name parts: drops one-letter fragments and bare numbers (OCR
     * debris), and folds the definite article so "المغلوث" and "مغلوث" compare
     * equal.
     *
     * @return list<string>
     */
    public static function of(string $text): array
    {
        $out = [];
        foreach (preg_split('/\s+/u', ArabicNormalizer::normalize($text)) ?: [] as $token) {
            if (mb_strlen($token) < 2 || ctype_digit($token)) {
                continue;
            }
            if (mb_strlen($token) > 4 && str_starts_with($token, 'ال')) {
                $token = mb_substr($token, 2);
            }
            $out[$token] = true;
        }

        return array_map('strval', array_keys($out));
    }

    /**
     * Dice similarity of two texts' name parts, 0..1: twice the shared parts
     * over both counts together.
     */
    public static function similarity(string $a, string $b): float
    {
        $ta = self::of($a);
        $tb = self::of($b);
        if ($ta === [] || $tb === []) {
            return 0.0;
        }

        return round(2 * count(array_intersect($ta, $tb)) / (count($ta) + count($tb)), 2);
    }

    /** Equal after normalization, spaces and punctuation ignored. */
    public static function same(string $a, string $b): bool
    {
        $ca = ArabicNormalizer::compact($a);

        return $ca !== '' && $ca === ArabicNormalizer::compact($b);
    }

    /**
     * Whether $needle's words appear together, whole, inside $haystack — "متحف
     * الملك فهد" in "مقتنيات متحف الملك فهد بالرياض". Containment, not fuzziness.
     */
    public static function within(string $needle, string $haystack): bool
    {
        $n = ArabicNormalizer::normalize($needle);

        return $n !== '' && str_contains(' '.ArabicNormalizer::normalize($haystack).' ', " {$n} ");
    }
}
