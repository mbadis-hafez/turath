<?php

namespace App\Support;

/**
 * Arabic-Indic (٠–٩) and Persian (۰–۹) digits read as ASCII, one character for
 * one, so a match's character offsets in the converted text are its offsets
 * in the original — which is how callers keep the value as it was written.
 */
final class ArabicDigits
{
    public const MAP = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    public static function toAscii(string $text): string
    {
        return strtr($text, self::MAP);
    }

    /**
     * The first match of $pattern (read with ASCII digits), as it appears in
     * $text. With a named group "value", only that group.
     */
    public static function match(string $pattern, string $text): ?string
    {
        $ascii = self::toAscii($text);
        if (preg_match($pattern, $ascii, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }
        [$matched, $byteOffset] = isset($m['value']) && $m['value'][1] >= 0 ? $m['value'] : $m[0];

        return mb_substr($text, mb_strlen(substr($ascii, 0, $byteOffset)), mb_strlen($matched));
    }
}
