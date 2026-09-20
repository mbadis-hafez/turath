<?php

namespace App\Support\Completeness;

use App\Models\Artist;
use Illuminate\Database\Eloquent\Model;

/**
 * Representative rule set from the F10 spec (§3) — not a claimed-final
 * taxonomy. `portrait_with_clear_rights` is satisfied by an uploaded
 * portrait whose rights status is not `unknown`.
 */
class ArtistCompletenessRules implements CompletenessRules
{
    public function coreFields(): array
    {
        return [
            'death_year_or_living_confirmed' => [
                'requires_citation' => true,
                'citation_field_key' => 'death_year',
            ],
            'primary_source' => [
                'requires_citation' => true,
                'any_citation' => true,
            ],
        ];
    }

    public function importantFields(): array
    {
        return ['birth_city', 'bio_en', 'portrait_with_clear_rights'];
    }

    public function isFieldPresent(Model $record, string $fieldKey): bool
    {
        /** @var Artist $record */
        return match ($fieldKey) {
            'death_year_or_living_confirmed' => $record->living_status === 'living' || $record->getAttribute('death_year_from') !== null,
            'primary_source' => true, // gated entirely by the any_citation check
            'birth_city' => $record->birth_place_ar !== null || $record->birth_place_en !== null,
            'bio_en' => $record->bio_en !== null && trim($record->bio_en) !== '',
            'portrait_with_clear_rights' => $record->portrait_path !== null && $record->portrait_rights_status !== 'unknown',
            default => false,
        };
    }

    public function citationExempt(Model $record, string $fieldKey): bool
    {
        /** @var Artist $record */
        return $fieldKey === 'death_year_or_living_confirmed' && $record->living_status === 'living';
    }

    public function fieldLabel(string $fieldKey): array
    {
        return match ($fieldKey) {
            'death_year_or_living_confirmed' => ['ar' => 'تاريخ الوفاة أو تأكيد الحياة', 'en' => 'Death year or living status confirmed'],
            'primary_source' => ['ar' => 'مصدر أساسي', 'en' => 'Primary source'],
            'birth_city' => ['ar' => 'مدينة الميلاد', 'en' => 'Birth city'],
            'bio_en' => ['ar' => 'السيرة (إنجليزي)', 'en' => 'Biography (English)'],
            'portrait_with_clear_rights' => ['ar' => 'صورة شخصية بحقوق واضحة', 'en' => 'Portrait with clear rights'],
            default => ['ar' => $fieldKey, 'en' => $fieldKey],
        };
    }
}
