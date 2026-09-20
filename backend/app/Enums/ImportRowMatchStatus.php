<?php

namespace App\Enums;

enum ImportRowMatchStatus: string
{
    case New = 'new';
    case MatchedExact = 'matched_exact';
    case MatchedSuggested = 'matched_suggested';
    case Ambiguous = 'ambiguous';
    case Error = 'error';
}
