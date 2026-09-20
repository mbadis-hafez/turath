<?php

namespace App\Enums;

enum ImportRowResolution: string
{
    case Pending = 'pending';
    case CreateNew = 'create_new';
    case LinkExisting = 'link_existing';
    case Skip = 'skip';
}
