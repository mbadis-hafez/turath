<?php

namespace App\Enums;

/**
 * Pending/acknowledged track "seen"; approved/rejected are the terminal
 * outcomes a reviewer records on standalone (non-proposal) entries (FR-005).
 */
enum ReviewQueueStatus: string
{
    case Pending = 'pending';
    case Acknowledged = 'acknowledged';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function isTerminal(): bool
    {
        return $this === self::Approved || $this === self::Rejected;
    }
}
