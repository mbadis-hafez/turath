<?php

namespace App\Enums;

enum ImportEntityType: string
{
    case Artist = 'artist';
    case Holder = 'holder';
    case Artwork = 'artwork';
    case ArchiveItem = 'archive_item';
}
