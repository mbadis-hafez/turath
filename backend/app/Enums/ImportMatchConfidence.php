<?php

namespace App\Enums;

enum ImportMatchConfidence: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
}
