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
    case PrimaryDocumentation = 'primary_documentation';
    case EventDocumentation = 'event_documentation';
    case AuthorizationLetter = 'authorization_letter';
    case NameVerification = 'name_verification';
}
