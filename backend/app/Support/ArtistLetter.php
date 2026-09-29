<?php

namespace App\Support;

class ArtistLetter
{
    /**
     * Return the directory first-letter for a name, normalized per locale.
     *
     * Arabic: alef forms (أ إ آ ا ٱ) are normalized to أ.
     * English: the first ASCII letter is uppercased.
     *
     * @return non-empty-string|null
     */
    public static function of(?string $name, string $locale): ?string
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $first = mb_substr($name, 0, 1);

        if ($locale === 'ar') {
            return str_replace(['إ', 'آ', 'ا', 'ٱ'], 'أ', $first);
        }

        $upper = mb_strtoupper($first);

        return preg_match('/^[A-Z]$/u', $upper) ? $upper : null;
    }
}
