<?php

namespace App\Enums;

enum ArchiveLinkRole: string
{
    case About = 'about';
    case Depicts = 'depicts';
    case Mentions = 'mentions';
    case AuthoredBy = 'authored_by';
    case Donor = 'donor';
    case Subject = 'subject';
}
