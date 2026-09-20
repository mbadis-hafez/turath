<?php

namespace App\Enums;

enum MaterialClassification: string
{
    case Movable = 'movable';
    case Immovable = 'immovable';
    case DigitalNative = 'digital_native';
    case Unspecified = 'unspecified';
}
