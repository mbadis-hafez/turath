<?php

namespace App\Enums;

use App\Support\Ocr\Extraction\DocumentFieldSchema;

/**
 * A first-pass classification of what kind of archival document this file is.
 * Different document types expose different semantic fields — an authorization
 * letter has no artwork dimensions, a condition report has no email address —
 * so each has its own field schema (App\Support\Ocr\Extraction\DocumentFieldSchema)
 * rather than one universal one.
 */
enum DocumentType: string
{
    case ArtistAuthorization = 'artist_authorization';
    case ArtworkConditionReport = 'artwork_condition_report';
    case ArtistBiography = 'artist_biography';
    case ExhibitionDocument = 'exhibition_document';
    case Unknown = 'unknown';

    /**
     * Field key => its labels, in schema order.
     *
     * @return array<string, array{ar: string, en: string}>
     */
    public function fieldSchema(): array
    {
        $labels = [];
        foreach (DocumentFieldSchema::for($this) as $field) {
            $labels[$field->key] = $field->label;
        }

        return $labels;
    }
}
