<?php

namespace App\Enums;

enum EventParticipantRole: string
{
    case Participant = 'participant';
    case Awardee = 'awardee';
    case Organizer = 'organizer';
    case Juror = 'juror';
    case ExhibitedWork = 'exhibited_work';
}
