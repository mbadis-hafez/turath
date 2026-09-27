<?php

namespace App\Support\Proposals;

use App\Enums\ProposalStatus;
use App\Models\EditProposal;
use App\Models\ReviewQueueItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Closes the gap between creating a record and editing one: today the first
 * is a direct write with no reviewer involved, the second is always staged
 * through EditProposal/ReviewQueueItem. A creation-review item is an
 * EditProposal with is_creation=true, payload/field_diffs left empty since
 * there is nothing to diff against — the live record already holds the
 * proposed content, because its own creator is trusted to edit it directly
 * until a reviewer approves it (005 research.md R1–R4).
 */
class CreationReviewService
{
    /** The open (not yet decided) creation-review item for a record, if any. */
    public function openFor(Model $record): ?EditProposal
    {
        return EditProposal::query()
            ->where('citable_type', $record::class)
            ->where('citable_id', $record->getKey())
            ->where('is_creation', true)
            ->whereIn('status', [ProposalStatus::Draft->value, ProposalStatus::Pending->value, ProposalStatus::ChangesRequested->value])
            ->latest('created_at')
            ->first();
    }

    /**
     * Called once, in the same transaction as the record's own creation.
     * `review_type` is derivable immediately (it depends only on the type's
     * field set, not on which fields are filled — unlike an edit draft, whose
     * payload can start partial) so it's set here rather than left null until
     * submit, satisfying the same NOT NULL constraint every EditProposal row
     * already has.
     */
    public function startFor(Model $record, User $creator): EditProposal
    {
        return EditProposal::create([
            'citable_type' => $record::class,
            'citable_id' => $record->getKey(),
            'proposed_by_user_id' => $creator->id,
            'status' => ProposalStatus::Draft->value,
            'is_creation' => true,
            'field_diffs' => [],
            'payload' => null,
            'rationale' => '',
            'review_type' => ProposableFields::reviewTypeFor(ProposableFields::for($record::class))->value,
        ]);
    }

    /**
     * Sends the record's open creation-review item to the review queue.
     * Unlike an edit draft, there is no "nothing differs" check — the whole
     * record is the content being reviewed, so it is always submittable.
     */
    public function submit(EditProposal $proposal, Model $record, User $user): EditProposal
    {
        abort_unless($proposal->is_creation, 422);
        abort_unless(
            in_array($proposal->status, [ProposalStatus::Draft->value, ProposalStatus::ChangesRequested->value], true),
            422,
        );

        $reviewType = ProposableFields::reviewTypeFor(ProposableFields::for($record::class));

        return DB::transaction(function () use ($proposal, $record, $user, $reviewType) {
            $proposal->update([
                'status' => ProposalStatus::Pending->value,
                'review_type' => $reviewType->value,
                'review_note' => null,
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
            ]);

            ReviewQueueItem::create([
                'citable_type' => $proposal->citable_type,
                'citable_id' => $proposal->citable_id,
                'edit_proposal_id' => $proposal->id,
                'review_type' => $reviewType->value,
                'submitted_by_user_id' => $user->id,
                'note' => 'New '.class_basename($record).' awaiting creation review.',
                'status' => 'pending',
            ]);

            return $proposal;
        });
    }

    /**
     * No field values to apply — they're already on the record (its creator
     * edited it directly). Approval is a pure confirmation: stamp the record
     * real, close the item, log who approved it.
     */
    public function approve(EditProposal $proposal, Model $record, User $reviewer, ?string $note): void
    {
        DB::transaction(function () use ($proposal, $record, $reviewer, $note) {
            $record->setAttribute('creation_approved_at', now());
            $record->save();

            $proposal->update([
                'status' => ProposalStatus::Approved->value,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            ReviewQueueItem::where('edit_proposal_id', $proposal->id)
                ->update(['status' => 'acknowledged', 'acted_by_user_id' => $reviewer->id, 'acted_at' => now()]);

            activity($record->getTable())
                ->performedOn($record)
                ->causedBy($reviewer)
                ->event('creation_approved')
                ->log('creation approved');
        });
    }
}
