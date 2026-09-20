<?php

namespace App\Support\Proposals;

use DateTimeInterface;

class FieldValues
{
    /** Blank is null everywhere else in this codebase, so a proposal can clear a field but never set it to "". */
    public static function normalize(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_string($value) && trim($value) === '') {
            return null;
        }

        return is_string($value) ? trim($value) : $value;
    }

    /** Compares a stored value with a proposed one without tripping over "55.00" vs 55. */
    public static function differ(mixed $a, mixed $b): bool
    {
        $a = self::normalize($a);
        $b = self::normalize($b);

        if (is_array($a) || is_array($b)) {
            return $a !== $b;
        }
        if ($a === null || $b === null) {
            return $a !== $b;
        }
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) > 1e-9;
        }

        return (string) $a !== (string) $b;
    }
}
