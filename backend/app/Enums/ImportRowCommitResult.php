<?php

namespace App\Enums;

enum ImportRowCommitResult: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
