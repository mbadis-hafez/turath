<?php

namespace App\Enums;

enum EventType: string
{
    case Exhibition = 'exhibition';
    case Symposium = 'symposium';
    case Award = 'award';
    case Talk = 'talk';
    case Festival = 'festival';
    case Biennial = 'biennial';
    case Other = 'other';
}
