<?php

namespace App\Support\Ocr;

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
}
