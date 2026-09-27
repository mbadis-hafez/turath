<?php

namespace App\Support\Proposals;

use App\Enums\ReviewType;
use App\Models\User;

/** Shared "is this user a reviewer at all" check, used by policies that grant reviewers read/request access. */
class ReviewerScope
{
    public static function canReview(?User $user): bool
    {
        return ReviewType::reviewableBy($user) !== [];
    }
}
