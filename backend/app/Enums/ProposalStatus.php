<?php

namespace App\Enums;

enum ProposalStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
}
