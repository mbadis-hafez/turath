<?php

namespace App\Enums;

enum ImportBatchStatus: string
{
    case Uploaded = 'uploaded';
    case Validated = 'validated';
    case Committed = 'committed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
