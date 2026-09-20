<?php

namespace App\Enums;

enum ReviewType: string
{
    case ArchivistReview = 'archivist_review';
    case DataAudit = 'data_audit';
    case SecondSourceNeeded = 'second_source_needed';
    case EditorialReview = 'editorial_review';
}
