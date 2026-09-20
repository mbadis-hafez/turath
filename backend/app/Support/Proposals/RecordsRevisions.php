<?php

namespace App\Support\Proposals;

use App\Enums\RevisionSource;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared observer behavior: a direct edit by an editor is one revision, so
 * both edit paths feed the same history (D67/D73). Creating a record is not a
 * revision of it — revision 1 is its first change.
 */
trait RecordsRevisions
{
    private const REVISION_NOISE = ['updated_at', 'created_at', 'deleted_at', 'search_text'];

    protected function recordDirectEditRevision(Model $record): void
    {
        if ($record->wasRecentlyCreated || RevisionTracking::suppressed()) {
            return;
        }

        $diffs = [];
        foreach ($record->getChanges() as $column => $new) {
            if (in_array($column, self::REVISION_NOISE, true)) {
                continue;
            }
            $diffs[$column] = ['old' => FieldValues::normalize($record->getOriginal($column)), 'new' => FieldValues::normalize($new)];
        }

        if ($diffs === []) {
            return;
        }

        RecordUpdater::recordRevision($record, $diffs, RevisionSource::DirectEdit, auth()->id());
    }
}
