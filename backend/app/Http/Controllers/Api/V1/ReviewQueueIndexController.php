<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReviewQueueStatus;
use App\Enums\ReviewType;
use App\Http\Resources\ReviewQueueItemResource;
use App\Models\ReviewQueueItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewQueueIndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'review_type' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', Rule::enum(ReviewQueueStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $allowedTypes = array_map(
            fn (ReviewType $type) => $type->value,
            array_filter(
                ReviewType::cases(),
                fn (ReviewType $type) => $user?->can($type->permission()) ?? false,
            ),
        );

        $query = ReviewQueueItem::query()
            ->with(['submittedBy', 'actedBy', 'citable'])
            ->whereIn('review_type', $allowedTypes)
            ->where('status', $data['status'] ?? ReviewQueueStatus::Pending->value)
            ->latest('submitted_at');

        if ($reviewType = $data['review_type'] ?? null) {
            $query->where('review_type', $reviewType);
        }

        $perPage = min((int) ($data['per_page'] ?? 24), 100);

        return ReviewQueueItemResource::collection($query->paginate($perPage))->response();
    }
}
