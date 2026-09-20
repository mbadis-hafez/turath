<?php

namespace App\Support\Proposals;

use App\Enums\ProposalStatus;
use App\Enums\RevisionSource;
use App\Models\EditProposal;
use App\Models\FieldCitation;
use App\Models\ReviewQueueItem;
use App\Models\Revision;
use App\Models\Source;
use App\Models\User;
use App\Support\Completeness\ConflictDetector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProposalService
{
    /**
     * The server derives the diff from the live record; a client only ever
     * says what it wants each field to become (§3 step 1).
     *
     * @param  array<string, mixed>  $changes
     * @param  array<int, array<string, mixed>>  $citations
     */
    public function submit(Model $record, User $user, array $changes, string $rationale, array $citations = []): EditProposal
    {
        $diffs = [];
        foreach ($changes as $column => $proposed) {
            $current = $record->getAttribute($column);
            if (FieldValues::differ($current, $proposed)) {
                $diffs[$column] = [
                    'old_value_at_proposal_time' => FieldValues::normalize($current),
                    'proposed_value' => FieldValues::normalize($proposed),
                ];
            }
        }

        if ($diffs === []) {
            throw ValidationException::withMessages(['changes' => ['Nothing in this proposal differs from the current record.']]);
        }

        $reviewType = ProposableFields::reviewTypeFor(array_keys($diffs));

        return DB::transaction(function () use ($record, $user, $diffs, $rationale, $citations, $reviewType) {
            $proposal = EditProposal::create([
                'citable_type' => $record::class,
                'citable_id' => $record->getKey(),
                'proposed_by_user_id' => $user->id,
                'status' => ProposalStatus::Pending->value,
                'field_diffs' => $diffs,
                'rationale' => $rationale,
                'proposed_citations' => $citations === [] ? null : $citations,
                'review_type' => $reviewType->value,
            ]);

            // D76: this is what now populates F10's queue.
            ReviewQueueItem::create([
                'citable_type' => $record::class,
                'citable_id' => $record->getKey(),
                'edit_proposal_id' => $proposal->id,
                'review_type' => $reviewType->value,
                'submitted_by_user_id' => $user->id,
                'note' => mb_substr($rationale, 0, 255),
                'status' => 'pending',
            ]);

            return $proposal;
        });
    }

    /**
     * @return array<int, array{field: string, proposed_against: mixed, current: mixed, proposed_value: mixed}>
     */
    public function conflicts(EditProposal $proposal, Model $record): array
    {
        $out = [];
        foreach ($proposal->field_diffs as $column => $diff) {
            $current = $record->getAttribute($column);
            if (FieldValues::differ($current, $diff['old_value_at_proposal_time'] ?? null)) {
                $out[] = [
                    'field' => $column,
                    'proposed_against' => $diff['old_value_at_proposal_time'] ?? null,
                    'current' => FieldValues::normalize($current),
                    'proposed_value' => $diff['proposed_value'] ?? null,
                ];
            }
        }

        return $out;
    }

    /**
     * @throws ConflictRequired when the record drifted and the reviewer has not confirmed
     */
    public function approve(EditProposal $proposal, Model $record, User $reviewer, ?string $note, bool $confirmConflict): Revision
    {
        $conflicts = $this->conflicts($proposal, $record);
        if ($conflicts !== [] && ! $confirmConflict) {
            throw new ConflictRequired($conflicts);
        }

        unset($conflicts);

        return RevisionTracking::withoutTracking(fn () => DB::transaction(function () use ($proposal, $record, $reviewer, $note) {
            $values = [];
            foreach ($proposal->field_diffs as $column => $diff) {
                $values[$column] = $diff['proposed_value'] ?? null;
            }

            $applied = RecordUpdater::apply($record, $values);
            $revision = RecordUpdater::recordRevision($record, $applied, RevisionSource::ApprovedProposal, $reviewer->id, $proposal->id);

            $this->promoteCitations($proposal, $record, $reviewer);

            $proposal->update([
                'status' => ProposalStatus::Approved->value,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
                'resulting_revision_id' => $revision->id,
            ]);
            $this->closeQueueItem($proposal, 'acknowledged');
            $this->supersedeOverlapping($proposal, array_keys($proposal->field_diffs));

            return $revision;
        }));
    }

    public function reject(EditProposal $proposal, User $reviewer, string $note): void
    {
        DB::transaction(function () use ($proposal, $reviewer, $note) {
            $proposal->update([
                'status' => ProposalStatus::Rejected->value,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);
            $this->closeQueueItem($proposal, 'dismissed');
        });
    }

    /** Only the citations a reviewer actually approved become real, so nothing unreviewed reaches F10. */
    private function promoteCitations(EditProposal $proposal, Model $record, User $reviewer): void
    {
        foreach ($proposal->proposed_citations ?? [] as $citation) {
            $sourceId = $citation['source_id'] ?? null;

            if ($sourceId === null) {
                $new = $citation['new_source'] ?? [];
                $sourceId = Source::create([
                    'source_type' => ($new['linked_archive_item_id'] ?? null) !== null ? 'archive_item' : ($new['source_type'] ?? 'other'),
                    'linked_archive_item_id' => $new['linked_archive_item_id'] ?? null,
                    'title_ar' => $new['title_ar'] ?? null,
                    'title_en' => $new['title_en'] ?? null,
                    'publisher_or_outlet' => $new['publisher_or_outlet'] ?? null,
                    'url' => $new['url'] ?? null,
                    'year' => $new['year'] ?? null,
                    'added_by_user_id' => $reviewer->id,
                ])->id;
            }

            FieldCitation::create([
                'citable_type' => $record::class,
                'citable_id' => $record->getKey(),
                'field_key' => $citation['field_key'],
                'source_id' => $sourceId,
                'claimed_value' => $citation['claimed_value'] ?? null,
                'created_by_user_id' => $reviewer->id,
            ]);

            (new ConflictDetector)->check($record::class, (int) $record->getKey(), $citation['field_key']);
        }
    }

    /**
     * @param  array<int, string>  $fields
     */
    private function supersedeOverlapping(EditProposal $approved, array $fields): void
    {
        EditProposal::where('citable_type', $approved->citable_type)
            ->where('citable_id', $approved->citable_id)
            ->where('status', ProposalStatus::Pending->value)
            ->where('id', '!=', $approved->id)
            ->get()
            ->each(function (EditProposal $other) use ($fields) {
                if (array_intersect($other->fieldKeys(), $fields) === []) {
                    return;
                }
                $other->update(['status' => ProposalStatus::Superseded->value]);
                $this->closeQueueItem($other, 'dismissed');
            });
    }

    private function closeQueueItem(EditProposal $proposal, string $status): void
    {
        ReviewQueueItem::where('edit_proposal_id', $proposal->id)->update(['status' => $status]);
    }
}
