<?php

namespace App\Enums;

enum SourceType: string
{
    case Book = 'book';
    case JournalArticle = 'journal_article';
    case NewspaperArticle = 'newspaper_article';
    case ExhibitionCatalogue = 'exhibition_catalogue';
    case ArchivalDocument = 'archival_document';
    case InstitutionalRecord = 'institutional_record';
    case OralInterview = 'oral_interview';
    case ArchiveItem = 'archive_item';
    case Other = 'other';
}
