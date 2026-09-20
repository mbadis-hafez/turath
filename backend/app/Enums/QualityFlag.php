<?php

namespace App\Enums;

enum QualityFlag: string
{
    case High = 'high';
    case Low = 'low';
    case NeedsRescan = 'needs_rescan';
}
