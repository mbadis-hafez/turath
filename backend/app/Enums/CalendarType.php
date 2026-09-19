<?php

namespace App\Enums;

enum CalendarType: string
{
    case Gregorian = 'gregorian';
    case Hijri = 'hijri';
}
