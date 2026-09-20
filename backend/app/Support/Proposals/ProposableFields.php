<?php

namespace App\Support\Proposals;

use App\Enums\ReviewType;
use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Event;

/**
 * Which columns a proposal may touch, per entity.
 *
 * Not in F7's spec, but required: without an allow-list a contributor could
 * propose a change to publication_status, access_level, merged_into_id or a
 * machine-maintained column like search_text, and an approving reviewer would
 * apply it through the normal update path without ever seeing it as anything
 * unusual. Descriptive and factual fields only; status, ownership, identity
 * codes and machine columns stay editor-only.
 */
class ProposableFields
{
    private const DATE_PARTS = ['date_display', 'year_from', 'year_to', 'calendar', 'certainty'];

    /**
     * @return array<int, string>
     */
    public static function for(string $modelClass): array
    {
        return match ($modelClass) {
            Artist::class => [
                'name_ar', 'name_en', 'bio_ar', 'bio_en', 'living_status',
                'birth_place_ar', 'birth_place_en', 'death_place_ar', 'death_place_en',
                'nationality_ar', 'nationality_en', 'classification_ar', 'classification_en',
                ...self::dateColumns('birth'), ...self::dateColumns('death'),
            ],
            Artwork::class => [
                'title_ar', 'title_en', 'is_untitled', 'category', 'medium_ar', 'medium_en',
                'height_cm', 'width_cm', 'depth_cm', 'dimensions_raw',
                'frame_height_cm', 'frame_width_cm', 'frame_depth_cm', 'frame_dimensions_raw',
                'weight_kg', 'signed', 'edition_number', 'edition_size',
                'notes_ar', 'notes_en', 'material_classification', 'holder_inventory_no',
                ...self::dateColumns('creation'),
            ],
            ArchiveItem::class => [
                'title_ar', 'title_en', 'description_ar', 'description_en', 'creator_name',
                'publication_name_ar', 'publication_name_en', 'issue_no', 'page', 'language',
                'original_format', 'place_ar', 'place_en', 'people_names', 'keywords',
                'source_name', 'verification_reference', 'content_date_note',
                'rights_status', 'rights_holder_ar', 'rights_holder_en', 'license',
                ...self::dateColumns('content'),
            ],
            Event::class => [
                'title_ar', 'title_en', 'description_ar', 'description_en',
                'venue_name', 'city', 'date_note', 'event_type',
                ...self::dateColumns('start'), ...self::dateColumns('end'),
            ],
            default => [],
        };
    }

    /**
     * D70: which reviewer queue a proposal lands in, decided by the most
     * sensitive field it touches. Rights outrank factual claims, which outrank
     * measurements; anything else is ordinary editorial review.
     *
     * @param  array<int, string>  $fields
     */
    public static function reviewTypeFor(array $fields): ReviewType
    {
        $matches = fn (array $needles) => array_filter(
            $fields,
            fn (string $f) => array_filter($needles, fn (string $n) => str_contains($f, $n)) !== [],
        ) !== [];

        return match (true) {
            $matches(['rights_', 'license']) => ReviewType::ArchivistReview,
            $matches(['name_', 'title_', 'bio_', 'description_', 'creator_name']) => ReviewType::SecondSourceNeeded,
            $matches(['_cm', 'year_', 'weight', 'edition_', 'calendar', 'certainty']) => ReviewType::DataAudit,
            default => ReviewType::EditorialReview,
        };
    }

    /**
     * @return array<int, string>
     */
    private static function dateColumns(string $prefix): array
    {
        return array_map(fn (string $part) => "{$prefix}_{$part}", self::DATE_PARTS);
    }
}
