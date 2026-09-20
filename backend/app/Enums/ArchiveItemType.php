<?php

namespace App\Enums;

enum ArchiveItemType: string
{
    case Article = 'article';
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Catalogue = 'catalogue';
    case Certificate = 'certificate';
    case Invitation = 'invitation';
    case Poster = 'poster';
    case Document = 'document';
    case Portfolio = 'portfolio';
    case Other = 'other';
}
