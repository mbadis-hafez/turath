<?php

namespace App\Support;

use App\Enums\AccessLevel;
use App\Enums\PublicationStatus;
use App\Models\ArchiveItem;
use App\Models\User;
use Carbon\CarbonImmutable;

class ArchiveAccessResolver
{
    /**
     * Whether the requester may see the item's full description, files and
     * links.
     */
    public static function canViewFull(?User $user, ArchiveItem $item): bool
    {
        if ($user?->can('archive.manage')) {
            return true;
        }

        if ($item->publication_status !== PublicationStatus::Published->value) {
            return false;
        }

        $effectiveLevel = self::effectiveLevel($item);

        if ($item->access_level === AccessLevel::Embargoed->value && ! self::embargoLifted($item)) {
            return false;
        }

        return self::userTier($user)->rank() >= $effectiveLevel->rank();
    }

    /**
     * Whether the requester may see the item at all in discoverable form
     * (title, type, date) — true for any published item regardless of
     * access level, per D23: restricted material is discoverable, not
     * hidden.
     */
    public static function canViewMetadataOnly(?User $user, ArchiveItem $item): bool
    {
        if ($user?->can('archive.manage')) {
            return true;
        }

        return $item->publication_status === PublicationStatus::Published->value;
    }

    /**
     * The access level actually in force right now: the item's own level,
     * unless it's an embargo that has already lifted, in which case the
     * explicit post-embargo level applies.
     */
    private static function effectiveLevel(ArchiveItem $item): AccessLevel
    {
        if ($item->access_level === AccessLevel::Embargoed->value && self::embargoLifted($item)) {
            return AccessLevel::from($item->post_embargo_access_level);
        }

        return AccessLevel::from($item->access_level);
    }

    private static function embargoLifted(ArchiveItem $item): bool
    {
        if ($item->embargo_until === null) {
            return true;
        }

        return CarbonImmutable::today()->greaterThanOrEqualTo($item->embargo_until);
    }

    private static function userTier(?User $user): AccessLevel
    {
        if ($user === null) {
            return AccessLevel::Public;
        }

        if ($user->hasRole('institution')) {
            return AccessLevel::InstitutionOnly;
        }

        if ($user->hasRole('verified_researcher')) {
            return AccessLevel::Researcher;
        }

        return AccessLevel::Registered;
    }
}
