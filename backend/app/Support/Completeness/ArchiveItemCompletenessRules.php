<?php

namespace App\Support\Completeness;

use App\Models\ArchiveItem;
use Illuminate\Database\Eloquent\Model;

/**
 * Representative rule set from the F10 spec (§3) — rights are the most
 * commonly missing field, consistent with F3's existing rights model
 * (RightsStatus, rights_holder_ar/en); this just scores it too, rather
 * than only validating it at publish time.
 */
class ArchiveItemCompletenessRules implements CompletenessRules
{
    public function coreFields(): array
    {
        // No citation surface exists for archive items (unlike Artist), so these
        // can never require one — that would make the gap permanently unsatisfiable.
        return [
            'rights_holder' => [
                'requires_citation' => false,
            ],
            'rights_status_known' => [
                'requires_citation' => false,
            ],
        ];
    }

    public function importantFields(): array
    {
        return ['digitized_at'];
    }

    public function isFieldPresent(Model $record, string $fieldKey): bool
    {
        /** @var ArchiveItem $record */
        return match ($fieldKey) {
            'rights_holder' => $record->rights_holder_ar !== null || $record->rights_holder_en !== null,
            'rights_status_known' => $record->rights_status !== 'unknown',
            'digitized_at' => $record->digitized_at !== null,
            default => false,
        };
    }

    public function citationExempt(Model $record, string $fieldKey): bool
    {
        return false;
    }

    public function fieldLabel(string $fieldKey): array
    {
        return match ($fieldKey) {
            'rights_holder' => ['ar' => 'مالك الحقوق', 'en' => 'Rights holder'],
            'rights_status_known' => ['ar' => 'حالة الحقوق', 'en' => 'Rights status'],
            'digitized_at' => ['ar' => 'تاريخ الرقمنة', 'en' => 'Digitized at'],
            default => ['ar' => $fieldKey, 'en' => $fieldKey],
        };
    }
}
