<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Activity\ActivityFeedRequest;
use App\Http\Resources\ActivityResource;
use Illuminate\Http\JsonResponse;
use Spatie\Activitylog\Models\Activity;

class ActivityFeedController
{
    public function __invoke(ActivityFeedRequest $request): JsonResponse
    {
        $query = Activity::query()->latest('id');

        $filters = $request->validated();

        if (isset($filters['subject_type'])) {
            $type = $filters['subject_type'];

            $query->where(function ($q) use ($type) {
                $q->where('subject_type', $type)
                    ->orWhere('subject_type', '\\'.$type);
            });
        }

        if (isset($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (isset($filters['causer_id'])) {
            $query->where('causer_id', $filters['causer_id']);
        }

        if (isset($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'].' 23:59:59');
        }

        $perPage = (int) ($filters['per_page'] ?? 24);

        return ActivityResource::collection($query->paginate($perPage))->response();
    }
}
