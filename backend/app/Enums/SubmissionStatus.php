<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case Submitted = 'submitted';
    case InitialReview = 'initial_review';
    case Cataloging = 'cataloging';
    case AuthorizationPending = 'authorization_pending';
    case Published = 'published';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    /** D159: a submission is never deleted; terminal states just stop moving. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Published, self::Rejected, self::Withdrawn], true);
    }
}
