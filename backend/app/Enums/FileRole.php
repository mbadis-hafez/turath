<?php

namespace App\Enums;

enum FileRole: string
{
    case Original = 'original';
    case Derivative = 'derivative';
    case Thumbnail = 'thumbnail';
    case Transcript = 'transcript';
    case Subtitle = 'subtitle';
}
