<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReviewQueueStatus;
use App\Http\Resources\ReviewQueueItemResource;
use App\Models\ReviewQueueItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewQueueAcknowledgeController
{
    public function __invoke(Request $request, ReviewQueueItem $reviewQueueItem): JsonResponse
    {
        $reviewQueueItem->status = ReviewQueueStatus::Acknowledged->value;
        $reviewQueueItem->acted_by_user_id = $request->user()->id;
        $reviewQueueItem->acted_at = now();
        $reviewQueueItem->save();

        return response()->json(['data' => new ReviewQueueItemResource($reviewQueueItem)]);
    }
}
