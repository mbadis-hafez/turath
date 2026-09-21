<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProposalStatus;
use App\Models\EditProposal;
use App\Support\Completeness\CitableTypeResolver;
use App\Support\Proposals\ConflictRequired;
use App\Support\Proposals\ProposableFields;
use App\Support\Proposals\ProposalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProposalController
{
    public function store(Request $request, string $type, int $id): JsonResponse
    {
        $entry = CitableTypeResolver::forSegment($type);
        abort_if($entry === null, 404);
        abort_unless($request->user()?->can('proposals.submit') ?? false, 403);

        $record = $entry['model']::query()->find($id);
        abort_if($record === null, 404);

        $allowed = ProposableFields::for($entry['model']);
        $data = $request->validate([
            'changes' => ['required', 'array', 'min:1'],
            'changes.*' => ['nullable'],
            'rationale' => ['required', 'string', 'min:3', 'max:5000'],
            'citations' => ['nullable', 'array', 'max:20'],
            'citations.*.field_key' => ['required', 'string', 'max:60'],
            'citations.*.claimed_value' => ['nullable'],
            'citations.*.source_id' => ['nullable', 'uuid', 'exists:sources,id'],
            'citations.*.new_source' => ['nullable', 'array'],
        ]);

        $unknown = array_diff(array_keys($data['changes']), $allowed);
        if ($unknown !== []) {
            throw ValidationException::withMessages(['changes' => ['These fields cannot be proposed: '.implode(', ', $unknown).'.']]);
        }
        foreach ($data['changes'] as $field => $value) {
            if (! is_scalar($value) && ! is_array($value) && $value !== null) {
                throw ValidationException::withMessages(["changes.{$field}" => ['Unsupported value type.']]);
            }
        }

        $proposal = (new ProposalService)->submit($record, $request->user(), $data['changes'], $data['rationale'], $data['citations'] ?? []);

        return response()->json(['data' => self::present($proposal, $record)], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(ProposalStatus::class)],
            'review_type' => ['nullable', 'string', 'max:30'],
            'mine' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $user = $request->user();
        abort_unless($user !== null, 403);

        $query = EditProposal::query()->with(['proposedBy', 'reviewedBy', 'citable']);

        // A contributor only ever sees their own; reviewers see everything they can act on.
        if ($request->boolean('mine') || ! $this->canReviewAnything($request)) {
            $query->where('proposed_by_user_id', $user->id);
        }
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['review_type'])) {
            $query->where('review_type', $data['review_type']);
        }

        $paginated = $query->latest('created_at')->paginate((int) ($data['per_page'] ?? 24));

        return response()->json([
            'data' => $paginated->getCollection()->map(fn (EditProposal $p) => self::present($p, $p->citable))->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(), 'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(), 'total' => $paginated->total(),
            ],
        ]);
    }

    public function show(Request $request, EditProposal $proposal): JsonResponse
    {
        $record = $this->recordFor($proposal);
        abort_unless($this->mayRead($request, $proposal), 403);

        $data = self::present($proposal->load(['proposedBy', 'reviewedBy']), $record);
        $data['conflicts'] = $record !== null && $proposal->status === ProposalStatus::Pending->value
            ? (new ProposalService)->conflicts($proposal, $record)
            : [];

        return response()->json(['data' => $data]);
    }

    public function approve(Request $request, EditProposal $proposal): JsonResponse
    {
        $record = $this->authorizeReview($request, $proposal);
        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:5000'],
            'confirm_conflict' => ['nullable', 'boolean'],
        ]);
        abort_unless($proposal->status === ProposalStatus::Pending->value, 422);

        // Carries the provenance into the record's own audit entry (D71).
        $request->merge(['edit_summary' => 'Approved edit proposal '.$proposal->id]);

        try {
            $revision = (new ProposalService)->approve(
                $proposal, $record, $request->user(), $data['review_note'] ?? null, $request->boolean('confirm_conflict'),
            );
        } catch (ConflictRequired $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'conflicts' => $e->conflicts,
            ], 409);
        }

        return response()->json(['data' => [
            ...self::present($proposal->refresh(), $record->refresh()),
            'revision' => RevisionController::present($revision),
        ]]);
    }

    public function reject(Request $request, EditProposal $proposal): JsonResponse
    {
        $this->authorizeReview($request, $proposal);
        $data = $request->validate(['review_note' => ['required', 'string', 'min:3', 'max:5000']]);
        abort_unless($proposal->status === ProposalStatus::Pending->value, 422);

        (new ProposalService)->reject($proposal, $request->user(), $data['review_note']);

        return response()->json(['data' => self::present($proposal->refresh(), $this->recordFor($proposal))]);
    }

    private function authorizeReview(Request $request, EditProposal $proposal): Model
    {
        $record = $this->recordFor($proposal);
        abort_if($record === null, 404);
        abort_unless($request->user()?->can($this->managePermission($proposal)) ?? false, 403);

        return $record;
    }

    private function mayRead(Request $request, EditProposal $proposal): bool
    {
        $user = $request->user();

        return $user !== null
            && ($user->id === $proposal->proposed_by_user_id || $user->can($this->managePermission($proposal)));
    }

    private function managePermission(EditProposal $proposal): string
    {
        $segment = CitableTypeResolver::segmentFor($proposal->citable_type) ?? '';

        return CitableTypeResolver::forSegment($segment)['manage_permission'] ?? 'artists.manage';
    }

    private function canReviewAnything(Request $request): bool
    {
        foreach (config('completeness.types', []) as $entry) {
            if ($request->user()?->can($entry['manage_permission'])) {
                return true;
            }
        }

        return false;
    }

    private function recordFor(EditProposal $proposal): ?Model
    {
        return $proposal->citable_type::query()->find($proposal->citable_id);
    }

    /**
     * The same bilingual column labels the activity feed uses, so a diff never
     * shows a raw column name.
     *
     * @param  array<int, string>  $fields
     * @return array<string, array{ar: string, en: string}>
     */
    public static function labelsFor(string $modelClass, array $fields): array
    {
        $all = method_exists($modelClass, 'activityFieldLabels') ? $modelClass::activityFieldLabels() : [];

        return array_intersect_key($all, array_flip($fields));
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(EditProposal $proposal, ?Model $record): array
    {
        return [
            'id' => $proposal->id,
            'record' => [
                'type' => CitableTypeResolver::segmentFor($proposal->citable_type),
                'id' => $proposal->citable_id,
                'label' => $record?->getAttribute('title_en') ?? $record?->getAttribute('title_ar')
                    ?? $record?->getAttribute('name_en') ?? $record?->getAttribute('name_ar'),
            ],
            'status' => $proposal->status,
            'review_type' => $proposal->review_type,
            'field_diffs' => $proposal->field_diffs,
            'field_labels' => self::labelsFor($proposal->citable_type, array_keys($proposal->field_diffs)),
            'rationale' => $proposal->rationale,
            'proposed_citations' => $proposal->proposed_citations ?? [],
            'proposed_by' => $proposal->relationLoaded('proposedBy') && $proposal->proposedBy
                ? ['id' => $proposal->proposedBy->id, 'name' => $proposal->proposedBy->name] : null,
            'reviewed_by' => $proposal->relationLoaded('reviewedBy') && $proposal->reviewedBy
                ? ['id' => $proposal->reviewedBy->id, 'name' => $proposal->reviewedBy->name] : null,
            'reviewed_at' => $proposal->reviewed_at?->toIso8601String(),
            'review_note' => $proposal->review_note,
            'resulting_revision_id' => $proposal->resulting_revision_id,
            'created_at' => $proposal->created_at->toIso8601String(),
        ];
    }
}
