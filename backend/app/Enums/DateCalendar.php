<?php

namespace App\Enums;

enum DateCalendar: string
{
    case Hijri = 'hijri';
    case Gregorian = 'gregorian';
    case Unknown = 'unknown';
}
