<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ReviewQueueItemResource;
use App\Models\ReviewQueueItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewQueueIndexController
{
    /**
     * @var array<string, string>
     */
    private const REVIEW_TYPE_PERMISSIONS = [
        'archivist_review' => 'review_queue.archivist_review',
        'data_audit' => 'review_queue.data_audit',
        'second_source_needed' => 'review_queue.second_source_needed',
        'editorial_review' => 'review_queue.editorial_review',
        'material_intake' => 'review_queue.material_intake',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $allowedTypes = array_keys(array_filter(
            self::REVIEW_TYPE_PERMISSIONS,
            fn (string $permission) => $user?->can($permission) ?? false,
        ));

        $query = ReviewQueueItem::query()
            ->with(['submittedBy', 'citable'])
            ->whereIn('review_type', $allowedTypes)
            ->where('status', 'pending')
            ->latest('submitted_at');

        if ($reviewType = $request->input('review_type')) {
            $query->where('review_type', $reviewType);
        }

        $perPage = min((int) $request->input('per_page', 24), 100);

        return ReviewQueueItemResource::collection($query->paginate($perPage))->response();
    }
}
