<?php

namespace App\Support\Completeness;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * One shared, type-generic check: a record cannot be published/verified/
 * finalized until a reviewer has approved its creation (005). Called from
 * each record type's own finalization enforcement point — those points are
 * not shared across types today (see specs/005-record-creation-review/
 * data-model.md), so this gate is, but each call site is added individually.
 */
class CreationReviewGate
{
    /**
     * @throws ValidationException when the record's creation hasn't been reviewed yet
     */
    public static function assertApproved(Model $record): void
    {
        if ($record->getAttribute('creation_approved_at') !== null) {
            return;
        }

        throw ValidationException::withMessages([
            'completeness.creation_review' => ["This record hasn't been reviewed yet."],
        ]);
    }
}
