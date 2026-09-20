<?php

namespace App\Enums;

enum SignedStatus: string
{
    case Signed = 'signed';
    case Unsigned = 'unsigned';
    case Unknown = 'unknown';
}
