<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Revision;
use App\Support\Completeness\CitableTypeResolver;
use App\Support\Proposals\RollbackService;
use App\Support\Proposals\UnpublishConfirmationRequired;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RevisionController
{
    public function index(Request $request, string $type, int $id): JsonResponse
    {
        $entry = CitableTypeResolver::forSegment($type);
        abort_if($entry === null, 404);
        abort_unless($request->user()?->can($entry['manage_permission']) ?? false, 403);

        $revisions = Revision::with('appliedBy')
            ->where('citable_type', $entry['model'])->where('citable_id', $id)
            ->orderByDesc('revision_number')->get();

        return response()->json(['data' => $revisions->map(fn (Revision $r) => self::present($r))->values()]);
    }

    public function rollback(Request $request, string $type, int $id, Revision $revision): JsonResponse
    {
        $entry = CitableTypeResolver::forSegment($type);
        abort_if($entry === null, 404);
        abort_unless($request->user()?->can($entry['manage_permission']) ?? false, 403);
        abort_if($revision->citable_type !== $entry['model'] || (int) $revision->citable_id !== $id, 404);
        abort_if($revision->reverted_by_revision_id !== null, 422);

        $record = $entry['model']::query()->find($id);
        abort_if($record === null, 404);

        $request->merge(['edit_summary' => 'Rolled back revision '.$revision->revision_number]);

        try {
            $new = (new RollbackService)->rollback($record, $revision, $request->user(), $request->boolean('confirm_unpublish'));
        } catch (UnpublishConfirmationRequired $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'would_unpublish' => true,
                'blocking' => $e->blocking,
            ], 409);
        }

        return response()->json(['data' => self::present($new->fresh(['appliedBy']))]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(Revision $revision): array
    {
        return [
            'id' => $revision->id,
            'revision_number' => $revision->revision_number,
            'source' => $revision->source,
            'edit_proposal_id' => $revision->edit_proposal_id,
            'field_diffs' => $revision->field_diffs,
            'applied_by' => $revision->relationLoaded('appliedBy') && $revision->appliedBy
                ? ['id' => $revision->appliedBy->id, 'name' => $revision->appliedBy->name] : null,
            'applied_at' => $revision->applied_at->toIso8601String(),
            'reverted_by_revision_id' => $revision->reverted_by_revision_id,
        ];
    }
}
