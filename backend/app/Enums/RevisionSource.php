<?php

namespace App\Enums;

enum RevisionSource: string
{
    case DirectEdit = 'direct_edit';
    case ApprovedProposal = 'approved_proposal';
    case Rollback = 'rollback';
}
