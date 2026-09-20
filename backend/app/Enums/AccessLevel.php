<?php

namespace App\Enums;

/**
 * Ordered least -> most restrictive for policy comparisons, except
 * `Embargoed`, which is a time-gated variant handled separately by
 * ArchiveAccessResolver rather than by rank.
 */
enum AccessLevel: string
{
    case Public = 'public';
    case Registered = 'registered';
    case Researcher = 'researcher';
    case InstitutionOnly = 'institution_only';
    case Embargoed = 'embargoed';

    /**
     * Rank for comparing non-embargoed levels; higher = more restrictive.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Public => 0,
            self::Registered => 1,
            self::Researcher => 2,
            self::InstitutionOnly, self::Embargoed => 3,
        };
    }
}
