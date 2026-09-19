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
        $entry = config("activity.resources.$resource");

        $model = is_string($entry) ? $entry : ($entry['model'] ?? null);
        $children = is_array($entry) ? ($entry['children'] ?? []) : [];

        if (! is_string($model) || ! class_exists($model)) {
            abort(404);
        }

        $query = Activity::query()->latest('id');

        $query->where(function ($or) use ($model, $children, $id) {
            $or->where(fn ($q) => $q->where('subject_type', $model)->where('subject_id', $id));

            foreach ($children as $child) {
                $childClass = $child['class'] ?? null;
                $fk = $child['fk'] ?? null;

                if (! is_string($childClass) || ! class_exists($childClass) || ! is_string($fk)) {
                    continue;
                }

                $table = (new $childClass)->getTable();

                $or->orWhere(fn ($q) => $q->where('subject_type', $childClass)
                    ->whereIn('subject_id', fn ($sub) => $sub->from($table)->where($fk, $id)->select('id')));
            }
        });

        $perPage = min((int) $request->input('per_page', 24), 100);

        return ActivityResource::collection($query->paginate($perPage))->response();
    }
}
