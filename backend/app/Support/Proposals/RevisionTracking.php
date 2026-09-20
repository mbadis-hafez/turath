<?php

namespace App\Support\Proposals;

/**
 * D67 records a revision for direct edits too, via the entity observers. The
 * proposal and rollback paths write their own, richer revision, so they
 * suppress the observer's while they apply.
 */
class RevisionTracking
{
    private static bool $suppressed = false;

    public static function suppressed(): bool
    {
        return self::$suppressed;
    }

    public static function withoutTracking(callable $callback): mixed
    {
        $previous = self::$suppressed;
        self::$suppressed = true;

        try {
            return $callback();
        } finally {
            self::$suppressed = $previous;
        }
    }
}
