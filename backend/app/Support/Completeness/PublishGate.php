<?php

namespace App\Support\Completeness;

use App\Enums\CompletenessSeverity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * D48: a record with any unmet core-field requirement cannot move to
 * publication_status = published. Extends F3's PublishArchiveItemRequest
 * pattern to Artists and Artworks, which had no equivalent gate before.
 */
class PublishGate
{
    /**
     * @throws ValidationException when the record has a blocking gap
     */
    public static function assertPublishable(Model $record): void
    {
        if (! CompletenessCalculator::supports($record::class)) {
            return;
        }

        $calculator = new CompletenessCalculator;
        $result = $calculator->evaluate($record);

        if ($result['severity'] !== CompletenessSeverity::Blocking) {
            return;
        }

        $rules = $calculator->rulesFor($record::class);

        $errors = [];
        foreach ($result['blocking'] as $fieldKey) {
            $label = $rules->fieldLabel($fieldKey);
            $errors["completeness.{$fieldKey}"] = ["Missing required field: {$label['en']}."];
        }

        throw ValidationException::withMessages($errors);
    }
}
