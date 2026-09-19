<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ActivityResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class SubjectActivityController
{
    public function __invoke(Request $request, string $resource, string $id): JsonResponse
    {
        $class = config("activity.resources.$resource");

        if (! is_string($class) || ! class_exists($class)) {
            abort(404);
        }

        $query = Activity::query()
            ->where('subject_type', $class)
            ->where('subject_id', $id)
            ->latest('id');

        // F1 extension point: also include entries whose subject is a child
        // name variant of this entity (e.g. transliterations).

        $perPage = min((int) $request->input('per_page', 24), 100);

        return ActivityResource::collection($query->paginate($perPage))->response();
    }
}
