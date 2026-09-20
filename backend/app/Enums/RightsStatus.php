<?php

namespace App\Enums;

enum RightsStatus: string
{
    case PublicDomain = 'public_domain';
    case Licensed = 'licensed';
    case AllRightsReserved = 'all_rights_reserved';
    case Unknown = 'unknown';
}
