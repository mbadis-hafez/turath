<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReviewQueueStatus;
use App\Enums\ReviewType;
use App\Http\Resources\ReviewQueueItemResource;
use App\Models\ReviewQueueItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * POST review-queue/{item}/outcome — records a review outcome on a standalone
 * (non-proposal) queue entry (FR-004/FR-005). Proposal-backed entries go
 * through the proposal approve/reject routes instead.
 */
class ReviewQueueOutcomeController
{
    public function __invoke(Request $request, ReviewQueueItem $reviewQueueItem): JsonResponse
    {
        $type = ReviewType::tryFrom($reviewQueueItem->review_type);
        abort_unless($type !== null && ($request->user()?->can($type->permission()) ?? false), 403);

        if ($reviewQueueItem->edit_proposal_id !== null) {
            throw ValidationException::withMessages([
                'outcome' => ['This entry is proposal-backed; review it through the proposal routes.'],
            ]);
        }

        $status = ReviewQueueStatus::tryFrom($reviewQueueItem->status);
        abort_if($status?->isTerminal(), 409, 'This entry already has a review outcome.');

        $data = $request->validate([
            'outcome' => ['required', Rule::in([ReviewQueueStatus::Approved->value, ReviewQueueStatus::Rejected->value])],
            'review_note' => [
                Rule::requiredIf($request->input('outcome') === ReviewQueueStatus::Rejected->value),
                'nullable', 'string', 'min:3', 'max:5000',
            ],
        ]);

        $reviewQueueItem->update([
            'status' => $data['outcome'],
            'review_note' => $data['review_note'] ?? null,
            'acted_by_user_id' => $request->user()->id,
            'acted_at' => now(),
        ]);

        return (new ReviewQueueItemResource($reviewQueueItem->refresh()->load(['submittedBy', 'actedBy', 'citable'])))->response();
    }
}
