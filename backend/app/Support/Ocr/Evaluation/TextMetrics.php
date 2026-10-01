<?php

namespace App\Support\Ocr\Evaluation;

use App\Support\ArabicNormalizer;

/**
 * Edit-distance error rates between a machine reading and the reference a
 * person confirmed. Character and word level, over Unicode code points (PHP's
 * levenshtein() counts bytes, which would make every Arabic letter two errors).
 * Rates are micro-averaged: total edits over total reference length, so a long
 * page weighs more than a one-word field.
 *
 * "Normalized" folds the spelling variants ArabicNormalizer folds (alef forms,
 * ta marbuta, alef maqsura, diacritics), separating misreadings from
 * orthography.
 */
final class TextMetrics
{
    /**
     * @return array{errors: int, length: int}
     */
    public static function charErrors(string $hypothesis, string $reference, bool $normalized = false): array
    {
        $ref = self::chars($normalized ? ArabicNormalizer::normalize($reference) : $reference);
        $hyp = self::chars($normalized ? ArabicNormalizer::normalize($hypothesis) : $hypothesis);

        return ['errors' => self::distance($hyp, $ref), 'length' => count($ref)];
    }

    /**
     * @return array{errors: int, length: int}
     */
    public static function wordErrors(string $hypothesis, string $reference, bool $normalized = false): array
    {
        $ref = self::words($normalized ? ArabicNormalizer::normalize($reference) : $reference);
        $hyp = self::words($normalized ? ArabicNormalizer::normalize($hypothesis) : $hypothesis);

        return ['errors' => self::distance($hyp, $ref), 'length' => count($ref)];
    }

    /** Null when there was nothing to measure. */
    public static function rate(int $errors, int $length): ?float
    {
        return $length === 0 ? null : round($errors / $length, 4);
    }

    /** Whitespace-insensitive equality: the test for "the same value". */
    public static function same(?string $a, ?string $b): bool
    {
        return self::squash($a) === self::squash($b);
    }

    /**
     * Levenshtein distance over token lists, two rows at a time.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    public static function distance(array $a, array $b): int
    {
        if ($a === []) {
            return count($b);
        }
        if ($b === []) {
            return count($a);
        }

        $previous = range(0, count($b));
        foreach ($a as $i => $tokenA) {
            $current = [$i + 1];
            foreach ($b as $j => $tokenB) {
                $current[] = min($previous[$j + 1] + 1, $current[$j] + 1, $previous[$j] + ($tokenA === $tokenB ? 0 : 1));
            }
            $previous = $current;
        }

        return $previous[count($b)];
    }

    /**
     * @return list<string>
     */
    private static function chars(string $text): array
    {
        $squashed = self::squash($text) ?? '';

        return $squashed === '' ? [] : mb_str_split($squashed);
    }

    /**
     * @return list<string>
     */
    private static function words(string $text): array
    {
        $squashed = self::squash($text) ?? '';

        return $squashed === '' ? [] : explode(' ', $squashed);
    }

    private static function squash(?string $text): ?string
    {
        return $text === null ? null : trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
