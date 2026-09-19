<?php

namespace App\Enums;

enum VerifiedStatus: string
{
    case Unverified = 'unverified';
    case Verified = 'verified';
    case Disputed = 'disputed';
}
