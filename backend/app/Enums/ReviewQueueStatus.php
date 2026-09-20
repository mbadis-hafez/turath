<?php

namespace App\Enums;

/** F10 only tracks "seen," not approve/reject — that's F7's job. */
enum ReviewQueueStatus: string
{
    case Pending = 'pending';
    case Acknowledged = 'acknowledged';
}
