<?php

namespace App\Support\Completeness;

use App\Models\Artist;
use Illuminate\Database\Eloquent\Model;

/**
 * The artist profile rule set: 11 uniformly weighted core (blocking)
 * requirements covering identity, biography and media. Nothing here
 * requires a citation — citations moved to the verification stage
 * (ArtistCurationService::verifyErrors).
 */
class ArtistCompletenessRules implements SectionedCompletenessRules
{
    public const SECTIONS = ['identity', 'biography', 'media'];

    public function coreFields(): array
    {
        return [
            'artist_code' => ['requires_citation' => false],
            'name_ar' => ['requires_citation' => false],
            'name_en' => ['requires_citation' => false],
            'birth_city' => ['requires_citation' => false],
            'living_status_known' => ['requires_citation' => false],
            'birth_year' => ['requires_citation' => false],
            'death_year_or_living' => ['requires_citation' => false],
            'nationality' => ['requires_citation' => false],
            'bio_ar' => ['requires_citation' => false],
            'bio_en' => ['requires_citation' => false],
            'portrait_with_clear_rights' => ['requires_citation' => false],
        ];
    }

    public function importantFields(): array
    {
        return [];
    }

    public function isFieldPresent(Model $record, string $fieldKey): bool
    {
        /** @var Artist $record */
        return match ($fieldKey) {
            'artist_code' => $this->filled($record->getAttribute('legacy_code')),
            'name_ar' => $this->filled($record->getAttribute('name_ar')),
            'name_en' => $this->filled($record->getAttribute('name_en')),
            'birth_city' => $this->filled($record->getAttribute('birth_place_ar')) || $this->filled($record->getAttribute('birth_place_en')),
            'living_status_known' => in_array($record->getAttribute('living_status'), ['living', 'deceased'], true),
            'birth_year' => $record->getAttribute('birth_year_from') !== null,
            'death_year_or_living' => $record->getAttribute('living_status') === 'living' || $record->getAttribute('death_year_from') !== null,
            'nationality' => $this->filled($record->getAttribute('nationality_ar')) || $this->filled($record->getAttribute('nationality_en')),
            'bio_ar' => $this->filled($record->getAttribute('bio_ar')),
            'bio_en' => $this->filled($record->getAttribute('bio_en')),
            'portrait_with_clear_rights' => $record->getAttribute('portrait_path') !== null && $record->getAttribute('portrait_rights_status') !== 'unknown',
            default => false,
        };
    }

    public function citationExempt(Model $record, string $fieldKey): bool
    {
        return true;
    }

    public function fieldSection(string $fieldKey): string
    {
        return match ($fieldKey) {
            'bio_ar', 'bio_en' => 'biography',
            'portrait_with_clear_rights' => 'media',
            default => 'identity',
        };
    }

    public function fieldLabel(string $fieldKey): array
    {
        return match ($fieldKey) {
            'artist_code' => ['ar' => 'رمز الفنان', 'en' => 'Artist code'],
            'name_ar' => ['ar' => 'الاسم (عربي)', 'en' => 'Name (Arabic)'],
            'name_en' => ['ar' => 'الاسم (إنجليزي)', 'en' => 'Name (English)'],
            'birth_city' => ['ar' => 'مدينة الميلاد', 'en' => 'Birth city'],
            'living_status_known' => ['ar' => 'حالة الحياة معروفة', 'en' => 'Living status known'],
            'birth_year' => ['ar' => 'سنة الميلاد', 'en' => 'Birth year'],
            'death_year_or_living' => ['ar' => 'سنة الوفاة أو كونه على قيد الحياة', 'en' => 'Death year or living'],
            'nationality' => ['ar' => 'الجنسية', 'en' => 'Nationality'],
            'bio_ar' => ['ar' => 'السيرة الذاتية (عربي)', 'en' => 'Biography (Arabic)'],
            'bio_en' => ['ar' => 'السيرة الذاتية (إنجليزي)', 'en' => 'Biography (English)'],
            'portrait_with_clear_rights' => ['ar' => 'صورة شخصية بحقوق واضحة', 'en' => 'Portrait with clear rights'],
            default => ['ar' => $fieldKey, 'en' => $fieldKey],
        };
    }

    private function filled(mixed $value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }
}
