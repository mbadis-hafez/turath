<?php

namespace App\Support\Completeness;

use App\Models\Event;
use App\ValueObjects\PartialDate;
use Illuminate\Database\Eloquent\Model;

/**
 * D122: single-gate publishing. The date rule matches the archive items' (D113):
 * an exact date, or an approximate one with a stated reason.
 */
class EventCompletenessRules implements CompletenessRules
{
    public function coreFields(): array
    {
        return [
            'title' => ['requires_citation' => false],
            'event_type' => ['requires_citation' => false],
            'date' => ['requires_citation' => false],
            'venue_name' => ['requires_citation' => false],
            'city' => ['requires_citation' => false],
        ];
    }

    public function importantFields(): array
    {
        return ['holder', 'description', 'participants'];
    }

    public function isFieldPresent(Model $record, string $fieldKey): bool
    {
        /** @var Event $record */
        $start = $record->getAttribute('start');

        return match ($fieldKey) {
            'title' => $record->title_ar !== null || $record->title_en !== null,
            'event_type' => filled($record->event_type),
            'date' => $start instanceof PartialDate && $start->yearFrom !== null
                && ($start->certainty?->value === 'exact' || filled($record->getAttribute('date_note'))),
            'venue_name' => filled($record->venue_name),
            'city' => filled($record->city),
            'holder' => $record->holder_id !== null,
            'description' => $record->description_ar !== null || $record->description_en !== null,
            'participants' => $record->participants()->exists(),
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
            'title' => ['ar' => 'العنوان', 'en' => 'Title'],
            'event_type' => ['ar' => 'نوع الفعالية', 'en' => 'Event type'],
            'date' => ['ar' => 'التاريخ (دقيق أو تقريبي مع مبرر)', 'en' => 'Date (exact, or approximate with a reason)'],
            'venue_name' => ['ar' => 'المكان', 'en' => 'Venue'],
            'city' => ['ar' => 'المدينة', 'en' => 'City'],
            'holder' => ['ar' => 'الجهة المستضيفة', 'en' => 'Host institution'],
            'description' => ['ar' => 'الوصف', 'en' => 'Description'],
            'participants' => ['ar' => 'المشاركون', 'en' => 'Participants'],
            default => ['ar' => $fieldKey, 'en' => $fieldKey],
        };
    }
}
