<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ContentPermissionRequest;
use App\Support\Completeness\CitableTypeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContentPermissionRequestIndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginated = ContentPermissionRequest::query()
            ->with(['requestedBy', 'decidedBy', 'citable'])
            ->where('status', $data['status'] ?? 'pending')
            ->latest('created_at')
            ->paginate((int) ($data['per_page'] ?? 24));

        return response()->json([
            'data' => $paginated->getCollection()->map(fn (ContentPermissionRequest $r) => self::present($r))->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(), 'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(), 'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(ContentPermissionRequest $r): array
    {
        $record = $r->relationLoaded('citable') ? $r->citable : null;

        return [
            'id' => $r->id,
            'record' => [
                'type' => CitableTypeResolver::segmentFor($r->citable_type),
                'id' => $r->citable_id,
                'label' => $record?->getAttribute('title_en') ?? $record?->getAttribute('title_ar')
                    ?? $record?->getAttribute('name_en') ?? $record?->getAttribute('name_ar'),
            ],
            'request_type' => $r->request_type,
            'status' => $r->status,
            'reason' => $r->reason,
            'requested_by' => $r->relationLoaded('requestedBy') && $r->requestedBy
                ? ['id' => $r->requestedBy->id, 'name' => $r->requestedBy->name] : null,
            'decided_by' => $r->relationLoaded('decidedBy') && $r->decidedBy
                ? ['id' => $r->decidedBy->id, 'name' => $r->decidedBy->name] : null,
            'decided_at' => $r->decided_at?->toIso8601String(),
            'decision_note' => $r->decision_note,
            'created_at' => $r->created_at->toIso8601String(),
        ];
    }
}
