<?php

namespace App\Enums;

enum AuthorizationLetterStatus: string
{
    case NotStarted = 'not_started';
    case Pending = 'pending';
    case Signed = 'signed';
    case NotApplicable = 'not_applicable';
}
