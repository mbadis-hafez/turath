<?php

namespace App\Support\Ocr\Extraction;

use App\Enums\ExtractedDateType;

/**
 * One field a document type can expose: how to find it, and where a
 * reviewer-verified value can go. Routes, strictest first:
 *
 * - artist_contact: contact details and the identity they belong to. Never
 *   accepted from the extracted-fields list; they go through the artist
 *   contact proposal (ArtistContactProposalService), which needs a confirmed
 *   artist and an archivist's approval.
 * - entity: a name to match against an existing record, or an attribute of
 *   one (target says which, e.g. "artist.birth_place"). Verified here; only
 *   ever applied to a record once that record is confirmed.
 * - evidence: recorded and verifiable, but no record field exists for it.
 * - record: a field of this archive item (see ExtractedFieldPayloadMapper),
 *   applied on accept through the usual draft/edit path.
 */
final class FieldDefinition
{
    /** One value. */
    public const KIND_TEXT = 'text';

    /** Any number of values, one row each (an exhibition history, a list of works). */
    public const KIND_LIST = 'list';

    /** Stored with the document's dates (file_extracted_dates) under $dateRole, never as a field row. */
    public const KIND_DATE = 'date';

    /** The record this document is about, chosen by entity matching — nothing to read off the page. */
    public const KIND_LINK = 'link';

    public const ROUTE_RECORD = 'record';

    public const ROUTE_ENTITY = 'entity';

    public const ROUTE_ARTIST_CONTACT = 'artist_contact';

    public const ROUTE_EVIDENCE = 'evidence';

    /** Record types a value can be matched against (see App\Support\Ocr\Matching\EntityMatcher). "place" matches places already used in records; there is no place table. */
    public const MATCH_TYPES = ['artist', 'artwork', 'event', 'holder', 'source', 'place'];

    /**
     * @param  array{ar: string, en: string}  $label
     * @param  list<string>  $labelKeywords  whole words naming this field in a form label or a "label: value" line
     * @param  list<string>  $sectionKeywords  headings that start a run of this field's items (or text)
     * @param  list<list<string>>  $phrases  a region containing every word of any one group is this field's statement
     * @param  string|null  $matchAs  the record type this value names, when it should be matched against existing records (one of MATCH_TYPES)
     */
    public function __construct(
        public readonly string $key,
        public readonly array $label,
        public readonly string $kind,
        public readonly string $route,
        public readonly ?string $target = null,
        public readonly array $labelKeywords = [],
        public readonly array $sectionKeywords = [],
        public readonly array $phrases = [],
        public readonly ?ExtractedDateType $dateRole = null,
        public readonly ?string $matchAs = null,
    ) {}

    public function isMultiple(): bool
    {
        return $this->kind === self::KIND_LIST;
    }
}
