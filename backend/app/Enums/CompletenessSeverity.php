<?php

namespace App\Enums;

/**
 * D56: fixed precedence when a record has more than one issue type —
 * blocking beats conflict beats minor/pending_review beats clear. A record
 * only ever shows one severity.
 */
enum CompletenessSeverity: string
{
    case Blocking = 'blocking';
    case Conflict = 'conflict';
    case Minor = 'minor';
    case PendingReview = 'pending_review';
    case Clear = 'clear';
}
