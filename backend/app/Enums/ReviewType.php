<?php

namespace App\Enums;

enum ReviewType: string
{
    case ArchivistReview = 'archivist_review';
    case DataAudit = 'data_audit';
    case SecondSourceNeeded = 'second_source_needed';
    case EditorialReview = 'editorial_review';
    case MaterialIntake = 'material_intake';

    /** The review_queue.* permission that gates working this queue. */
    public function permission(): string
    {
        return 'review_queue.'.$this->value;
    }
}
