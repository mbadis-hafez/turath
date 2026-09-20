<?php

namespace App\Enums;

enum AttributionCertainty: string
{
    case Confirmed = 'confirmed';
    case Attributed = 'attributed';
    case Disputed = 'disputed';
    case Unattributed = 'unattributed';
}
