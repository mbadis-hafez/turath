<?php

namespace App\Enums;

enum ContentPermissionRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
