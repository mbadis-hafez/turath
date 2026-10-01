<?php

namespace App\Support\Ocr;

use App\Models\ArchiveItem;

/** Maps a `file_extracted_fields.field_key` onto the partial "fields" section payload UpdateArchiveItemRequest expects. */
class ExtractedFieldPayloadMapper
{
    /**
     * @return array<string, mixed>|null null when the field key isn't mappable onto an archive item field
     */
    public static function toPayload(string $fieldKey, ?string $value): ?array
    {
        return match ($fieldKey) {
            'title_ar' => ['title' => ['ar' => $value]],
            'title_en' => ['title' => ['en' => $value]],
            'description_ar' => ['description' => ['ar' => $value]],
            'description_en' => ['description' => ['en' => $value]],
            'place_ar' => ['place' => ['ar' => $value]],
            'place_en' => ['place' => ['en' => $value]],
            'rights_holder_ar' => ['rights_holder' => ['ar' => $value]],
            'rights_holder_en' => ['rights_holder' => ['en' => $value]],
            'source_name' => ['source_name' => $value],
            'verification_reference' => ['verification_reference' => $value],
            'date_display' => ['content' => ['display' => $value]],
            default => null,
        };
    }

    /** What the archive item holds now for a field key — the value accepting would replace. */
    public static function currentValue(ArchiveItem $item, string $fieldKey): ?string
    {
        $value = match ($fieldKey) {
            'title_ar', 'title_en', 'description_ar', 'description_en', 'place_ar', 'place_en',
            'rights_holder_ar', 'rights_holder_en', 'source_name', 'verification_reference' => $item->getAttribute($fieldKey),
            'date_display' => $item->getAttribute('content_date_display'),
            default => null,
        };

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * The content date is one group on the record — display text, years,
     * calendar, certainty — and a payload carrying only its display text
     * would reset the rest. Fills them in from what is there now: the
     * reviewer's open draft when it has the group, otherwise the record.
     *
     * @param  array<string, mixed>  $partial  a toPayload() result
     * @param  array<string, mixed>|null  $draftFields  the open draft's "fields" section, if any
     * @return array<string, mixed>
     */
    public static function preservingContent(array $partial, ArchiveItem $item, ?array $draftFields = null): array
    {
        if (! isset($partial['content']) || ! is_array($partial['content'])) {
            return $partial;
        }

        if ($draftFields !== null && array_key_exists('content', $draftFields)) {
            $base = is_array($draftFields['content']) ? $draftFields['content'] : [];
        } else {
            // The columns, not the `content` cast: reading the cast caches it, and saving would write
            // that cached (old) date back over the new display text.
            $base = [
                'year_from' => $item->getAttribute('content_year_from'),
                'year_to' => $item->getAttribute('content_year_to'),
                'calendar' => $item->getAttribute('content_calendar'),
                'certainty' => $item->getAttribute('content_certainty'),
            ];
        }

        $partial['content'] = [...array_intersect_key($base, array_flip(['year_from', 'year_to', 'calendar', 'certainty'])), ...$partial['content']];

        return $partial;
    }
}
