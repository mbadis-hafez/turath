<?php

namespace App\Enums;

enum ExtractedFieldStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Edited = 'edited';
}
