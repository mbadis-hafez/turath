<?php

namespace App\Policies;

use App\Enums\PublicationStatus;
use App\Models\Artwork;
use App\Models\User;

class ArtworkPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Published and non-trashed artworks are public; anything else needs
     * artworks.manage.
     */
    public function view(?User $user, Artwork $artwork): bool
    {
        if ($artwork->trashed()) {
            return $user?->can('artworks.manage') ?? false;
        }

        if ($artwork->publication_status !== PublicationStatus::Published->value) {
            return $user?->can('artworks.manage') ?? false;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('artworks.manage');
    }

    public function update(User $user, Artwork $artwork): bool
    {
        return $user->can('artworks.manage');
    }

    public function delete(User $user, Artwork $artwork): bool
    {
        return $user->can('artworks.manage');
    }

    public function restore(User $user, ?Artwork $artwork = null): bool
    {
        return $user->can('artworks.manage');
    }
}
