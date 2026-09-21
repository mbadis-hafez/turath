<?php

namespace App\Enums;

enum SubmitterRole: string
{
    case Artist = 'artist';
    case ArtistFamily = 'artist_family';
    case PrivateCollection = 'private_collection';
    case Association = 'association';
    case Researcher = 'researcher';
    case Other = 'other';
}
