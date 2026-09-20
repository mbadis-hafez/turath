<?php

namespace App\Support\Completeness;

use App\Models\Artwork;
use Illuminate\Database\Eloquent\Model;

/**
 * Representative rule set from the F10 spec (§3). The spec's second core
 * field — an `image_usage_permission` citation "if the image is used
 * publicly" — is omitted here: F2 has no image field and artworks aren't
 * yet linked to F3's files at all, so there is no signal to gate on.
 * Documented in docs/decisions.md; revisit once artwork images exist.
 */
class ArtworkCompletenessRules implements CompletenessRules
{
    public function coreFields(): array
    {
        return [
            'holder' => ['requires_citation' => false],
        ];
    }

    public function importantFields(): array
    {
        return ['dimensions', 'medium'];
    }

    public function isFieldPresent(Model $record, string $fieldKey): bool
    {
        /** @var Artwork $record */
        return match ($fieldKey) {
            'holder' => $record->holder_id !== null,
            'dimensions' => $record->height_cm !== null || $record->width_cm !== null,
            'medium' => $record->medium_ar !== null || $record->medium_en !== null,
            default => false,
        };
    }

    public function citationExempt(Model $record, string $fieldKey): bool
    {
        return true;
    }

    public function fieldLabel(string $fieldKey): array
    {
        return match ($fieldKey) {
            'holder' => ['ar' => 'الجهة الحائزة', 'en' => 'Holder'],
            'dimensions' => ['ar' => 'الأبعاد', 'en' => 'Dimensions'],
            'medium' => ['ar' => 'الخامة', 'en' => 'Medium'],
            default => ['ar' => $fieldKey, 'en' => $fieldKey],
        };
    }
}
