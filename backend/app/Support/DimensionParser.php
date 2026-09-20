<?php

namespace App\Support;

use App\ValueObjects\ParsedDimensions;

/**
 * Parses the messy free-text dimension strings found in the source
 * spreadsheets into structured height/width/depth (cm) fields. Reused
 * verbatim by the F4 importer — never throws, and the original string is
 * always preserved by the caller as `dimensions_raw` regardless of whether
 * parsing succeeds.
 */
class DimensionParser
{
    public static function parse(string $raw): ParsedDimensions
    {
        $trimmed = trim($raw);
        $hasCmUnit = self::hasCmUnit($trimmed);

        if ($trimmed === '') {
            return new ParsedDimensions(confidence: 'low', unitDetected: 'unknown', raw: null);
        }

        $normalized = self::normalize($trimmed);
        $unit = $hasCmUnit ? 'cm' : 'unknown';

        if ($normalized === '') {
            return new ParsedDimensions(confidence: 'low', unitDetected: $unit, raw: $trimmed);
        }

        // A fraction (e.g. "1/2") in a segment can never be safely resolved
        // to a dimension value; extract only the unambiguous height when
        // present rather than guessing at the rest.
        if (str_contains($normalized, '/')) {
            return self::parseWithFraction($normalized, $unit, $trimmed);
        }

        $compact = (string) preg_replace('/\s+/', '', $normalized);

        if ($hwd = self::matchHwd($compact)) {
            return new ParsedDimensions(
                heightCm: $hwd['h'],
                widthCm: $hwd['w'] ?? null,
                depthCm: $hwd['d'] ?? null,
                unitDetected: 'cm',
                confidence: 'high',
                raw: $trimmed,
            );
        }

        if ($bare = self::matchBareList($compact)) {
            return new ParsedDimensions(
                heightCm: $bare[0] ?? null,
                widthCm: $bare[1] ?? null,
                depthCm: $bare[2] ?? null,
                unitDetected: 'cm',
                confidence: 'low',
                raw: $trimmed,
            );
        }

        return new ParsedDimensions(confidence: 'low', unitDetected: $unit, raw: $trimmed);
    }

    private static function hasCmUnit(string $value): bool
    {
        return str_contains(mb_strtolower($value), 'cm') || str_contains($value, 'سم');
    }

    private static function normalize(string $raw): string
    {
        $value = mb_strtolower($raw);
        $value = str_replace('×', 'x', $value);

        // Comma-decimal (e.g. "91,5") to dot, but only between two digits
        // with a 1-2 digit fraction — never touch a genuine thousands
        // separator (none occur in this domain, but stay defensive).
        $value = (string) preg_replace('/(\d),(\d{1,2})(?!\d)/', '$1.$2', $value);

        $value = str_replace('cm', '', $value);
        $value = (string) preg_replace('/سم/u', '', $value);

        return trim($value);
    }

    /**
     * @return array{h: float, w?: float, d?: float}|null
     */
    private static function matchHwd(string $compact): ?array
    {
        if (! preg_match('/^(\d+(?:\.\d+)?)hx(\d+(?:\.\d+)?)w(?:x(\d+(?:\.\d+)?)d)?$/', $compact, $m)) {
            return null;
        }

        $result = ['h' => (float) $m[1], 'w' => (float) $m[2]];

        if (isset($m[3])) {
            $result['d'] = (float) $m[3];
        }

        return $result;
    }

    /**
     * Bare "NxN" / "NxNxN" with no H/W/D letters — order is assumed to be
     * height, width, depth, but since the source convention doesn't
     * guarantee that order, callers must treat this as low confidence.
     *
     * @return array<int, float>|null
     */
    private static function matchBareList(string $compact): ?array
    {
        $parts = explode('x', $compact);

        if (count($parts) < 2 || count($parts) > 3) {
            return null;
        }

        $numbers = [];

        foreach ($parts as $part) {
            if (! preg_match('/^\d+(?:\.\d+)?$/', $part)) {
                return null;
            }

            $numbers[] = (float) $part;
        }

        return $numbers;
    }

    private static function parseWithFraction(string $normalized, string $unit, string $raw): ParsedDimensions
    {
        if (preg_match('/(\d+(?:\.\d+)?)\s*h\b/', $normalized, $m)) {
            return new ParsedDimensions(heightCm: (float) $m[1], unitDetected: $unit, confidence: 'low', raw: $raw);
        }

        return new ParsedDimensions(unitDetected: $unit, confidence: 'low', raw: $raw);
    }
}
