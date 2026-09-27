<?php

namespace App\Policies;

use App\Enums\PublicationStatus;
use App\Models\Artwork;
use App\Models\User;
use App\Support\Proposals\PublishedContentGuard;
use App\Support\Proposals\ReviewerScope;

class ArtworkPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Published and non-trashed artworks are public; anything else needs
     * artworks.manage, or reviewer read access to work the review queue.
     */
    public function view(?User $user, Artwork $artwork): bool
    {
        if ($artwork->trashed()) {
            return $user?->can('artworks.manage') ?? false;
        }

        if ($artwork->publication_status !== PublicationStatus::Published->value) {
            return ($user?->can('artworks.manage') ?? false) || ReviewerScope::canReview($user);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('artworks.manage');
    }

    public function update(User $user, Artwork $artwork): bool
    {
        return $user->can('artworks.manage') && PublishedContentGuard::manageHolderMayActDirectly($user, $artwork);
    }

    public function delete(User $user, Artwork $artwork): bool
    {
        if ($user->can('artworks.manage')) {
            return PublishedContentGuard::manageHolderMayActDirectly($user, $artwork);
        }

        return PublishedContentGuard::hasApprovedRequest($user, $artwork, 'delete');
    }

    public function restore(User $user, ?Artwork $artwork = null): bool
    {
        return $user->can('artworks.manage');
    }

    public function publish(User $user, Artwork $artwork): bool
    {
        return $user->can('artworks.publish');
    }

    public function requestEdit(User $user, Artwork $artwork): bool
    {
        return ReviewerScope::canReview($user) && ! $user->can('artworks.manage');
    }

    public function requestDelete(User $user, Artwork $artwork): bool
    {
        return ReviewerScope::canReview($user) && ! $user->can('artworks.manage');
    }
}
