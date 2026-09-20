<?php

namespace App\Enums;

enum ConflictStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
}
