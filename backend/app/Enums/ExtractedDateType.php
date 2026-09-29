<?php

namespace App\Enums;

enum ExtractedDateType: string
{
    case DocumentIssueDate = 'document_issue_date';
    case SignatureDate = 'signature_date';
    case Other = 'other';
}
