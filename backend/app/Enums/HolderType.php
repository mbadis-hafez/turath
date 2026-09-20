<?php

namespace App\Enums;

enum HolderType: string
{
    case Institution = 'institution';
    case PrivateCollector = 'private_collector';
    case Family = 'family';
    case ArtistEstate = 'artist_estate';
    case Other = 'other';
}
