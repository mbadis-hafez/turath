<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReviewType;
use App\Http\Resources\ReviewQueueItemResource;
use App\Models\ReviewQueueItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewQueueIndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $allowedTypes = array_map(
            fn (ReviewType $type) => $type->value,
            array_filter(
                ReviewType::cases(),
                fn (ReviewType $type) => $user?->can($type->permission()) ?? false,
            ),
        );

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
