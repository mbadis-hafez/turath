<?php

namespace App\Enums;

enum ExtractedFieldStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Edited = 'edited';

    /** A reviewer looked and can't confirm the value from the source: never applied or bulk-accepted, still open to a decision. */
    case Uncertain = 'uncertain';
}
