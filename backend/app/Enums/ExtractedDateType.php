<?php

namespace App\Enums;

/**
 * What a date found in a document is the date *of*. Assigned from the words
 * just before it and the document's type (see App\Support\Ocr\DateExtractor);
 * a date with no telling context keeps Other, which is the "unknown" role.
 */
enum ExtractedDateType: string
{
    case DocumentIssueDate = 'document_issue_date';
    case PublicationDate = 'publication_date';
    case EventDate = 'event_date';
    case ArtworkDate = 'artwork_date';
    case BirthDate = 'birth_date';
    case DeathDate = 'death_date';
    case SignatureDate = 'signature_date';

    /** The role is unknown. */
    case Other = 'other';
}
