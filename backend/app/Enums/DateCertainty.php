<?php

namespace App\Enums;

enum DateCertainty: string
{
    case Exact = 'exact';
    case Circa = 'circa';
    case Range = 'range';
    case Unknown = 'unknown';
}
