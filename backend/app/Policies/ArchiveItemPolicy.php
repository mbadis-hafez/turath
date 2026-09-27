<?php

namespace App\Policies;

use App\Models\ArchiveItem;
use App\Models\User;
use App\Support\ArchiveAccessResolver;
use App\Support\Proposals\PublishedContentGuard;
use App\Support\Proposals\ReviewerScope;

class ArchiveItemPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Gates whether the item is discoverable at all (404 vs not) — a
     * published-but-access-restricted item is still "viewable" here; the
     * controller separately decides the full vs restricted response shape
     * via ArchiveAccessResolver::canViewFull().
     */
    public function view(?User $user, ArchiveItem $item): bool
    {
        if ($item->trashed()) {
            return $user?->can('archive.manage') ?? false;
        }

        return ArchiveAccessResolver::canViewMetadataOnly($user, $item);
    }

    public function create(User $user): bool
    {
        return $user->can('archive.manage');
    }

    public function update(User $user, ArchiveItem $item): bool
    {
        return $user->can('archive.manage') && PublishedContentGuard::manageHolderMayActDirectly($user, $item);
    }

    public function delete(User $user, ArchiveItem $item): bool
    {
        if ($user->can('archive.manage')) {
            return PublishedContentGuard::manageHolderMayActDirectly($user, $item);
        }

        return PublishedContentGuard::hasApprovedRequest($user, $item, 'delete');
    }

    public function restore(User $user, ?ArchiveItem $item = null): bool
    {
        return $user->can('archive.manage');
    }

    public function publish(User $user, ArchiveItem $item): bool
    {
        return $user->can('archive.publish');
    }

    public function requestEdit(User $user, ArchiveItem $item): bool
    {
        return ReviewerScope::canReview($user) && ! $user->can('archive.manage');
    }

    public function requestDelete(User $user, ArchiveItem $item): bool
    {
        return ReviewerScope::canReview($user) && ! $user->can('archive.manage');
    }
}
