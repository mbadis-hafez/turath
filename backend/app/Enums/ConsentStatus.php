<?php

namespace App\Enums;

enum ConsentStatus: string
{
    case Signed = 'signed';
    case Unsigned = 'unsigned';
    case Unknown = 'unknown';
}
