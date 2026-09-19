<?php

namespace App\Policies;

use App\Enums\PublicationStatus;
use App\Models\Artist;
use App\Models\User;

class ArtistPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Published and non-trashed artists are public; anything else needs
     * artists.manage.
     */
    public function view(?User $user, Artist $artist): bool
    {
        if ($artist->trashed()) {
            return $user?->can('artists.manage') ?? false;
        }

        if ($artist->publication_status !== PublicationStatus::Published->value) {
            return $user?->can('artists.manage') ?? false;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('artists.manage');
    }

    public function update(User $user, Artist $artist): bool
    {
        return $user->can('artists.manage');
    }

    public function delete(User $user, Artist $artist): bool
    {
        return $user->can('artists.manage');
    }

    public function restore(User $user, ?Artist $artist = null): bool
    {
        return $user->can('artists.manage');
    }

    public function verify(User $user, Artist $artist): bool
    {
        return $user->can('artists.verify');
    }
}
