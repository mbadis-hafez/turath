<?php

namespace App\Policies;

use App\Enums\PublicationStatus;
use App\Models\Artist;
use App\Models\User;
use App\Support\Proposals\PublishedContentGuard;
use App\Support\Proposals\ReviewerScope;

class ArtistPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Published and non-trashed artists are public; anything else needs
     * artists.manage, or reviewer read access to work the review queue.
     */
    public function view(?User $user, Artist $artist): bool
    {
        if ($artist->trashed()) {
            return $user?->can('artists.manage') ?? false;
        }

        if ($artist->publication_status !== PublicationStatus::Published->value) {
            return ($user?->can('artists.manage') ?? false) || ReviewerScope::canReview($user);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('artists.manage');
    }

    public function update(User $user, Artist $artist): bool
    {
        return $user->can('artists.manage') && PublishedContentGuard::manageHolderMayActDirectly($user, $artist);
    }

    public function delete(User $user, Artist $artist): bool
    {
        if ($user->can('artists.manage')) {
            return PublishedContentGuard::manageHolderMayActDirectly($user, $artist);
        }

        return PublishedContentGuard::hasApprovedRequest($user, $artist, 'delete');
    }

    public function restore(User $user, ?Artist $artist = null): bool
    {
        return $user->can('artists.manage');
    }

    public function verify(User $user, Artist $artist): bool
    {
        return $user->can('artists.verify');
    }

    public function publish(User $user, Artist $artist): bool
    {
        return $user->can('artists.publish');
    }

    /** A Reviewer (never an Editor/Admin, who can already edit/delete directly) may ask to. */
    public function requestEdit(User $user, Artist $artist): bool
    {
        return ReviewerScope::canReview($user) && ! $user->can('artists.manage');
    }

    public function requestDelete(User $user, Artist $artist): bool
    {
        return ReviewerScope::canReview($user) && ! $user->can('artists.manage');
    }
}
