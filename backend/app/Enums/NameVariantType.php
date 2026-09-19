<?php

namespace App\Enums;

enum NameVariantType: string
{
    case Transliteration = 'transliteration';
    case Alias = 'alias';
    case BirthName = 'birth_name';
    case PenName = 'pen_name';
    case Typo = 'typo';
    case Other = 'other';
}
