<?php

namespace App\Enums;

enum ArtworkCategory: string
{
    case Painting = 'painting';
    case Drawing = 'drawing';
    case Printmaking = 'printmaking';
    case Sculpture = 'sculpture';
    case MixedMedia = 'mixed_media';
    case PaperWork = 'paper_work';
    case Photography = 'photography';
    case Installation = 'installation';
    case Other = 'other';
}
