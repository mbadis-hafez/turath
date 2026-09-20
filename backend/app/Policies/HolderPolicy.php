<?php

namespace App\Policies;

use App\Models\Holder;
use App\Models\User;

class HolderPolicy
{
    /**
     * There is no public browse/list endpoint for holders in F2 (avoids an
     * accidental directory of private collectors) — only single-record
     * lookups, reached through an artwork.
     */
    public function view(?User $user, Holder $holder): bool
    {
        if ($holder->trashed()) {
            return $user?->can('holders.manage') ?? false;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('holders.manage');
    }

    public function update(User $user, Holder $holder): bool
    {
        return $user->can('holders.manage');
    }

    public function delete(User $user, Holder $holder): bool
    {
        return $user->can('holders.manage');
    }

    public function restore(User $user, ?Holder $holder = null): bool
    {
        return $user->can('holders.manage');
    }
}
