<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProposalStatus;
use App\Models\EditProposal;
use App\Support\Completeness\CitableTypeResolver;
use App\Support\Proposals\EditorialDraftService;
use App\Support\Proposals\ProposalDiffBuilder;
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

    /** GET proposals/{proposal}/diff — per-section diff of an editorial draft against the live record. */
    public function diff(Request $request, EditProposal $proposal): JsonResponse
    {
        abort_if($proposal->proposed_by_user_id !== $request->user()->id
            && ! ($request->user()?->can($this->managePermission($proposal)) ?? false), 403);

        return response()->json(['data' => ProposalDiffBuilder::build($proposal)]);
    }

    /** POST proposals/{proposal}/request-changes — reviewer sends a pending draft back to its editor. */
    public function requestChanges(Request $request, EditProposal $proposal): JsonResponse
    {
        $record = $proposal->citable_type::query()->find($proposal->citable_id);
        abort_if($record === null, 404);

        abort_if($proposal->proposed_by_user_id === $request->user()->id, 403);
        abort_unless($request->user()?->can($this->managePermission($proposal)) ?? false, 403);

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

    private function managePermission(EditProposal $proposal): string
    {
        $segment = CitableTypeResolver::segmentFor($proposal->citable_type) ?? '';

        return CitableTypeResolver::forSegment($segment)['manage_permission'] ?? 'artists.manage';
    }
}
