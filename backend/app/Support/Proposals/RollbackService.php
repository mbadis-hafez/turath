<?php

namespace App\Support\Proposals;

use App\Enums\CompletenessSeverity;
use App\Enums\RevisionSource;
use App\Models\Revision;
use App\Models\User;
use App\Support\Completeness\CompletenessCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RollbackService
{
    /**
     * D75: reverting is an active choice, so the actor confirms the
     * consequence rather than discovering it afterwards. The check runs on the
     * actually-applied state inside a transaction, then unwinds it if the
     * reviewer has not confirmed.
     *
     * @throws UnpublishConfirmationRequired
     */
    public function rollback(Model $record, Revision $target, User $actor, bool $confirmUnpublish): Revision
    {
        $wasPublished = $record->getAttribute('publication_status') === 'published';

        return RevisionTracking::withoutTracking(fn () => DB::transaction(function () use ($record, $target, $actor, $confirmUnpublish, $wasPublished) {
            $inverse = [];
            foreach ($target->field_diffs as $column => $diff) {
                $inverse[$column] = $diff['old'] ?? null;
            }

            $applied = RecordUpdater::apply($record, $inverse);

            if ($wasPublished && CompletenessCalculator::supports($record::class)) {
                $evaluation = (new CompletenessCalculator)->evaluate($record);
                if ($evaluation['severity'] === CompletenessSeverity::Blocking) {
                    if (! $confirmUnpublish) {
                        throw new UnpublishConfirmationRequired($evaluation['blocking']);
                    }
                    $record->setAttribute('publication_status', 'draft');
                    $record->save();
                    $applied['publication_status'] = ['old' => 'published', 'new' => 'draft'];
                }
            }

            $revision = RecordUpdater::recordRevision($record, $applied, RevisionSource::Rollback, $actor->id);
            $target->update(['reverted_by_revision_id' => $revision->id]);

            return $revision;
        }));
    }
}
