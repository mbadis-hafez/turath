<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReviewQueueStatus;
use App\Http\Resources\ReviewQueueItemResource;
use App\Models\ReviewQueueItem;
use Illuminate\Http\JsonResponse;

class ReviewQueueAcknowledgeController
{
    public function __invoke(ReviewQueueItem $reviewQueueItem): JsonResponse
    {
        $reviewQueueItem->status = ReviewQueueStatus::Acknowledged->value;
        $reviewQueueItem->save();

        return response()->json(['data' => new ReviewQueueItemResource($reviewQueueItem)]);
    }
}
