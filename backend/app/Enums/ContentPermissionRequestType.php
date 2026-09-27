<?php

namespace App\Enums;

enum ContentPermissionRequestType: string
{
    case Edit = 'edit';
    case Delete = 'delete';
}
