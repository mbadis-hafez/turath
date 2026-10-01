<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProposalStatus;
use App\Enums\ReviewType;
use App\Models\EditProposal;
use App\Support\Completeness\CitableTypeResolver;
use App\Support\Ocr\ArtistContactProposalPresenter;
use App\Support\Proposals\CreationReviewService;
use App\Support\Proposals\EditorialDraftService;
use App\Support\Proposals\ProposalDiffBuilder;
use App\Support\Proposals\ProposalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EditorialDraftController
{
    /** PUT records/{type}/{id}/draft — create or replace the caller's draft for the record. */
    public function upsert(Request $request, string $type, int $id): JsonResponse
    {
        [$entry, $record] = $this->resolve($type, $id);
        abort_unless($request->user()?->can('proposals.submit') ?? false, 403);

        $data = $request->validate([
            'payload' => ['required', 'array'],
            'rationale' => ['nullable', 'string', 'max:5000'],
        ]);

        if (strlen((string) json_encode($data['payload'])) > 200 * 1024) {
            throw ValidationException::withMessages(['payload' => ['The draft payload may not exceed 200KB.']]);
        }

        $proposal = (new EditorialDraftService)->upsert($record, $request->user(), $data['payload'], $data['rationale'] ?? null);

        return response()->json(['data' => ProposalController::present($proposal->fresh(), $record)]);
    }

    /** GET records/{type}/{id}/draft — the caller's open proposal for the record, or null. */
    public function show(Request $request, string $type, int $id): JsonResponse
    {
        [$entry, $record] = $this->resolve($type, $id);

        /** @var EditProposal|null $proposal */
        $proposal = EditProposal::query()
            ->where('citable_type', $entry['model'])
            ->where('citable_id', $record->getKey())
            ->where('proposed_by_user_id', $request->user()->id)
            ->whereIn('status', [
                ProposalStatus::Draft->value,
                ProposalStatus::Pending->value,
                ProposalStatus::ChangesRequested->value,
            ])
            ->latest('created_at')
            ->first();

        return response()->json(['data' => $proposal ? ProposalController::present($proposal, $record) : null]);
    }

    /** POST records/{type}/{id}/draft/submit — send the caller's draft to the review queue. */
    public function submit(Request $request, string $type, int $id): JsonResponse
    {
        [, $record] = $this->resolve($type, $id);
        abort_unless($request->user()?->can('proposals.submit') ?? false, 403);

        $proposal = (new EditorialDraftService)->submit($record, $request->user());

        return response()->json(['data' => ProposalController::present($proposal->fresh(), $record)]);
    }

    /** POST records/{type}/{id}/creation/submit — send the record's open creation-review item to the queue (005). */
    public function submitCreation(Request $request, string $type, int $id): JsonResponse
    {
        [, $record] = $this->resolve($type, $id);
        $user = $request->user();
        abort_unless($user?->can('proposals.submit') ?? false, 403);

        $service = new CreationReviewService;
        $proposal = $service->openFor($record);
        abort_if($proposal === null, 422, 'This record has already been reviewed, or is already awaiting review.');
        abort_if($proposal->proposed_by_user_id !== $user->id, 403);

        $submitted = $service->submit($proposal, $record, $user);

        return response()->json(['data' => ProposalController::present($submitted->fresh(), $record)]);
    }

    /**
     * GET proposals/{proposal}/diff — per-section diff of an editorial draft against the live record,
     * any drift the reviewer would have to confirm (so it shows before they approve, not after),
     * and the documents a value was read from, when it was proposed from one.
     */
    public function diff(Request $request, EditProposal $proposal): JsonResponse
    {
        abort_if($proposal->proposed_by_user_id !== $request->user()->id && ! $this->mayReview($request, $proposal), 403);

        $record = $proposal->citable_type::query()->find($proposal->citable_id);

        return response()->json(['data' => [
            ...ProposalDiffBuilder::build($proposal),
            'conflicts' => $record !== null && $proposal->status === ProposalStatus::Pending->value
                ? (new ProposalService)->conflicts($proposal, $record)
                : [],
            'evidence' => (new ArtistContactProposalPresenter)->evidence($proposal, $request->user()),
        ]]);
    }

    /** POST proposals/{proposal}/request-changes — reviewer sends a pending draft back to its editor. */
    public function requestChanges(Request $request, EditProposal $proposal): JsonResponse
    {
        $record = $proposal->citable_type::query()->find($proposal->citable_id);
        abort_if($record === null, 404);

        abort_if($proposal->proposed_by_user_id === $request->user()->id, 403);
        abort_unless($this->mayReview($request, $proposal), 403);

        $data = $request->validate(['review_note' => ['required', 'string', 'min:3', 'max:5000']]);
        abort_unless($proposal->status === ProposalStatus::Pending->value, 422);

        (new EditorialDraftService)->requestChanges($proposal, $request->user(), $data['review_note']);

        return response()->json(['data' => ProposalController::present($proposal->refresh(), $record)]);
    }

    /**
     * @return array{0: array{model: class-string<Model>, manage_permission: string}, 1: Model}
     */
    private function resolve(string $type, int $id): array
    {
        $entry = CitableTypeResolver::forSegment($type);
        abort_if($entry === null, 404);

        $record = $entry['model']::query()->findOrFail($id);

        return [$entry, $record];
    }

    /** Reviewing means holding the record-type manage permission or the proposal's own review_queue.* permission. */
    private function mayReview(Request $request, EditProposal $proposal): bool
    {
        $user = $request->user();
        if ($user !== null && $user->can($this->managePermission($proposal))) {
            return true;
        }

        $type = ReviewType::tryFrom($proposal->review_type);

        return $type !== null && ($user?->can($type->permission()) ?? false);
    }

    private function managePermission(EditProposal $proposal): string
    {
        $segment = CitableTypeResolver::segmentFor($proposal->citable_type) ?? '';

        return CitableTypeResolver::forSegment($segment)['manage_permission'] ?? 'artists.manage';
    }
}
